<?php
// tests/edit.php — Edit Test
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$test_id = int_param('id', 0, 'get');
if ($test_id <= 0) {
    redirect('index.php');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test Not Found');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title         = str_param('title', '', 'post');
    $duration      = int_param('duration_minutes', 0, 'post');
    $totalMarks    = (float)($_POST['total_marks'] ?? 0);
    $negativeMarks = (float)($_POST['negative_marks'] ?? 0);
    $status        = str_param('status', $test['status'], 'post');

    if ($title === '') {
        $error = 'Please enter a test title.';
    } elseif ($duration <= 0) {
        $error = 'Duration must be greater than 0.';
    } elseif ($totalMarks < 0 || $negativeMarks < 0) {
        $error = 'Marks values cannot be negative.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE tests
                SET title = ?, duration_minutes = ?, total_marks = ?, negative_marks = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $duration, $totalMarks, $negativeMarks, $status, $test_id]);
            $test = get_test($test_id);
            $success = 'Test updated successfully!';
        } catch (Throwable $e) {
            $ref = app_log('error', 'Edit test error: ' . $e->getMessage());
            $error = 'Failed to update test. Ref: ' . $ref;
        }
    }
}

$page_title = 'Edit Test — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'tests');
?>

<div class="page-content" style="max-width: 600px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Test #<?= $test['id'] ?></h1>
            <p class="page-subtitle"><?= e($test['title']) ?></p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Back to Tests</a>
    </div>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <?php if ($success): ?>
        <?= alert_box('success', $success) ?>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Test Title</label>
                <input type="text" name="title" class="form-input" value="<?= e($test['title']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Duration (Minutes)</label>
                <input type="number" name="duration_minutes" class="form-input" value="<?= (int)$test['duration_minutes'] ?>" min="1" max="600" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Marks per Correct</label>
                    <input type="number" step="0.25" name="total_marks" class="form-input" value="<?= (float)$test['total_marks'] ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Negative Marks</label>
                    <input type="number" step="0.25" name="negative_marks" class="form-input" value="<?= (float)$test['negative_marks'] ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="draft" <?= $test['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="waiting" <?= $test['status'] === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                    <option value="active" <?= $test['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $test['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php layout_footer(); ?>
