<?php
// questions/edit.php — Edit Question
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$question_id = int_param('id', 0);
$test_id     = int_param('test_id', 0);

if ($question_id <= 0 || $test_id <= 0) {
    redirect('index.php');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test Not Found');
}

$stmt = $pdo->prepare("
    SELECT q.*, tq.question_order
    FROM questions q
    INNER JOIN test_questions tq ON tq.question_id = q.id
    WHERE q.id = ? AND tq.test_id = ?
    LIMIT 1
");
$stmt->execute([$question_id, $test_id]);
$question = $stmt->fetch();

if (!$question) {
    abort_html(404, 'Question Not Found');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $question_text  = str_param('question_text', '', 'post');
    $option_a       = str_param('option_a', '', 'post');
    $option_b       = str_param('option_b', '', 'post');
    $option_c       = str_param('option_c', '', 'post');
    $option_d       = str_param('option_d', '', 'post');
    $correct_option = strtoupper(str_param('correct_option', 'A', 'post'));
    $marks          = (float)($_POST['marks'] ?? $question['marks']);
    $negative_marks = (float)($_POST['negative_marks'] ?? $question['negative_marks']);
    $question_order = int_param('question_order', $question['question_order'], 'post');

    if ($question_text === '' || $option_a === '' || $option_b === '' || $option_c === '' || $option_d === '') {
        $error = 'Please fill in the question text and all four options.';
    } elseif (!in_array($correct_option, ['A', 'B', 'C', 'D'], true)) {
        $error = 'Please select a valid correct option.';
    } else {
        $image_path = $question['question_image'];

        // Remove existing image request
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            if ($image_path) {
                safe_unlink($image_path);
                $image_path = null;
            }
        }

        // Upload replacement image
        if (!empty($_FILES['image']['name'])) {
            $upload_err = '';
            $uploaded = upload_question_image($_FILES['image'], $upload_err);
            if ($uploaded === false && $upload_err !== '') {
                $error = $upload_err;
            } else {
                if ($image_path) {
                    safe_unlink($image_path);
                }
                $image_path = $uploaded;
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE questions
                    SET question_text = ?, question_image = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, marks = ?, negative_marks = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $question_text,
                    $image_path,
                    $option_a,
                    $option_b,
                    $option_c,
                    $option_d,
                    $correct_option,
                    $marks,
                    $negative_marks,
                    $question_id
                ]);

                $stmt = $pdo->prepare("UPDATE test_questions SET question_order = ? WHERE test_id = ? AND question_id = ?");
                $stmt->execute([$question_order, $test_id, $question_id]);

                $pdo->commit();
                $success = 'Question updated successfully!';
                
                // Refresh data
                $stmt = $pdo->prepare("SELECT q.*, tq.question_order FROM questions q INNER JOIN test_questions tq ON tq.question_id = q.id WHERE q.id = ? AND tq.test_id = ?");
                $stmt->execute([$question_id, $test_id]);
                $question = $stmt->fetch();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $ref = app_log('error', 'Edit question error: ' . $e->getMessage());
                $error = 'Failed to update question. Ref: ' . $ref;
            }
        }
    }
}

$page_title = 'Edit Question — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'questions');
?>

<div class="page-content" style="max-width: 800px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Question #<?= $question['id'] ?></h1>
            <p class="page-subtitle">Test: <strong><?= e($test['title']) ?></strong></p>
        </div>
        <a href="index.php?test_id=<?= $test['id'] ?>" class="btn btn-secondary">← Back to Questions</a>
    </div>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <?php if ($success): ?>
        <?= alert_box('success', $success) ?>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Question Text</label>
                <textarea name="question_text" class="form-textarea" rows="4" required><?= e($question['question_text']) ?></textarea>
            </div>

            <?php if (!empty($question['question_image'])): ?>
                <div style="margin-bottom: 16px; padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-md);">
                    <label class="form-label">Current Image</label>
                    <img src="../<?= e($question['question_image']) ?>" alt="Question Image" style="max-height: 160px; display: block; margin-bottom: 10px; border-radius: var(--radius-sm);">
                    <label style="font-size: 13px; color: var(--danger); cursor: pointer;">
                        <input type="checkbox" name="remove_image" value="1"> Remove current image
                    </label>
                </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" style="font-weight: 700; margin-bottom: 8px; display: block;"><?= !empty($question['question_image']) ? 'Replace Image (PNG, JPG, WEBP)' : 'Add Image (PNG, JPG, WEBP)' ?></label>
                
                <div class="custom-file-upload">
                    <input type="file" name="image" id="editImageInput" accept="image/*" style="display: none;" onchange="handleEditFile(this)">
                    <div class="file-upload-row">
                        <label for="editImageInput" class="file-upload-btn">
                            <span>📷</span> Choose Image File
                        </label>
                        <span class="file-upload-hint" id="editFileHint">or drag & drop new image</span>
                    </div>
                    <div class="file-preview-wrap" id="editFilePreviewWrap">
                        <img src="" alt="Thumbnail" class="file-preview-thumb" id="editFilePreviewThumb">
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="editFilePreviewName">image.png</div>
                            <div class="file-preview-size" id="editFilePreviewSize">0 KB</div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            function handleEditFile(input) {
                if (input.files && input.files[0]) {
                    var file = input.files[0];
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('editFilePreviewThumb').src = e.target.result;
                        document.getElementById('editFilePreviewName').textContent = file.name;
                        document.getElementById('editFilePreviewSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
                        document.getElementById('editFilePreviewWrap').classList.add('active');
                        document.getElementById('editFileHint').style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                }
            }
            </script>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Option A</label>
                    <input type="text" name="option_a" class="form-input" value="<?= e($question['option_a']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Option B</label>
                    <input type="text" name="option_b" class="form-input" value="<?= e($question['option_b']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Option C</label>
                    <input type="text" name="option_c" class="form-input" value="<?= e($question['option_c']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Option D</label>
                    <input type="text" name="option_d" class="form-input" value="<?= e($question['option_d']) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; margin-top: 8px;">
                <div class="form-group">
                    <label class="form-label">Correct Option</label>
                    <select name="correct_option" class="form-select">
                        <option value="A" <?= $question['correct_option'] === 'A' ? 'selected' : '' ?>>Option A</option>
                        <option value="B" <?= $question['correct_option'] === 'B' ? 'selected' : '' ?>>Option B</option>
                        <option value="C" <?= $question['correct_option'] === 'C' ? 'selected' : '' ?>>Option C</option>
                        <option value="D" <?= $question['correct_option'] === 'D' ? 'selected' : '' ?>>Option D</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Order Position</label>
                    <input type="number" name="question_order" class="form-input" value="<?= (int)$question['question_order'] ?>" min="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Marks (+)</label>
                    <input type="number" step="0.25" name="marks" class="form-input" value="<?= (float)$question['marks'] ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Negative Marks (-)</label>
                    <input type="number" step="0.25" name="negative_marks" class="form-input" value="<?= (float)$question['negative_marks'] ?>" required>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="index.php?test_id=<?= $test['id'] ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php layout_footer(); ?>
