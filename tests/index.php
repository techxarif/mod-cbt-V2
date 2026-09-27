<?php
// tests/index.php — Tests Management
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$message = '';
$error   = '';

try {
    $stmt = $pdo->query("
        SELECT 
            t.id, t.title, t.duration_minutes, t.total_marks, t.negative_marks,
            t.status, t.start_time, t.end_time, t.created_at,
            (SELECT COUNT(*) FROM test_questions tq WHERE tq.test_id = t.id) AS question_count,
            (SELECT COUNT(*) FROM student_tests st WHERE st.test_id = t.id) AS student_count
        FROM tests t
        ORDER BY t.id DESC
    ");
    $tests = $stmt->fetchAll();
} catch (Throwable $e) {
    $ref = app_log('error', 'Tests list error: ' . $e->getMessage());
    $error = 'Unable to load tests. Ref: ' . $ref;
}

$page_title = 'Tests Management — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'tests');
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Tests</h1>
            <p class="page-subtitle">Create, configure, and monitor computer-based examinations</p>
        </div>
        <div>
            <a href="create.php" class="btn btn-primary">+ Create New Test</a>
        </div>
    </div>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="data-table-wrapper" style="border: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Test Title</th>
                        <th>Duration</th>
                        <th>Marks (+ / -)</th>
                        <th>Questions</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tests)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                No tests found. Click <strong>+ Create New Test</strong> to get started.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tests as $test): ?>
                            <tr>
                                <td style="color: var(--text-muted);">#<?= $test['id'] ?></td>
                                <td>
                                    <strong><?= e($test['title']) ?></strong>
                                </td>
                                <td><?= (int)$test['duration_minutes'] ?> mins</td>
                                <td>+<?= (float)$test['total_marks'] ?> / -<?= (float)$test['negative_marks'] ?></td>
                                <td>
                                    <a href="../questions/index.php?test_id=<?= $test['id'] ?>">
                                        <?= (int)$test['question_count'] ?> questions
                                    </a>
                                </td>
                                <td>
                                    <a href="../assignments/index.php?test_id=<?= $test['id'] ?>">
                                        <?= (int)$test['student_count'] ?> assigned
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-<?= e($test['status']) ?>">
                                        <?= e(status_label($test['status'])) ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="edit.php?id=<?= $test['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <a href="../exam_control/index.php?test_id=<?= $test['id'] ?>" class="btn btn-primary btn-sm">Control</a>
                                    <form method="POST" action="delete.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this test?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php layout_footer(); ?>
