<?php
// tests/create.php — Create Test
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title         = str_param('title', '', 'post');
    $duration      = int_param('duration_minutes', 0, 'post');
    $totalMarks    = (float)($_POST['total_marks'] ?? 0);
    $negativeMarks = (float)($_POST['negative_marks'] ?? 0);

    if ($title === '') {
        $error = 'Please enter a test title.';
    } elseif ($duration <= 0) {
        $error = 'Duration must be greater than 0.';
    } elseif ($totalMarks < 0) {
        $error = 'Total marks cannot be negative.';
    } elseif ($negativeMarks < 0) {
        $error = 'Negative marks cannot be negative.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tests (title, duration_minutes, total_marks, negative_marks, status)
                VALUES (?, ?, ?, ?, 'draft')
            ");
            $stmt->execute([$title, $duration, $totalMarks, $negativeMarks]);
            redirect('index.php');
        } catch (Throwable $e) {
            $ref = app_log('error', 'Create test error: ' . $e->getMessage());
            $error = 'Failed to create test. Ref: ' . $ref;
        }
    }
}

$page_title = 'Create Test — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'tests');
?>

<div class="page-content" style="max-width: 600px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Create Test</h1>
            <p class="page-subtitle">Configure examination rules and metadata</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Back to Tests</a>
    </div>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Test Title</label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Physics Midterm Examination 2026" value="<?= e($_POST['title'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Duration (Minutes)</label>
                <input type="number" name="duration_minutes" class="form-input" placeholder="e.g. 60" min="1" max="600" value="<?= e($_POST['duration_minutes'] ?? '60') ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Marks per Correct Answer</label>
                    <input type="number" step="0.25" name="total_marks" class="form-input" value="<?= e($_POST['total_marks'] ?? '4.0') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Negative Marks per Wrong</label>
                    <input type="number" step="0.25" name="negative_marks" class="form-input" value="<?= e($_POST['negative_marks'] ?? '1.0') ?>" required>
                </div>
            </div>

            <div style="margin-top: 12px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Test</button>
            </div>
        </form>
    </div>
</div>

<?php layout_footer(); ?>
