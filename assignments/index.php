<?php
// assignments/index.php — Student Test Assignments
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$test_id = int_param('test_id', 0);
$tests   = get_all_tests();

if ($test_id <= 0 && !empty($tests)) {
    $test_id = (int)$tests[0]['id'];
}

$selected_test = $test_id > 0 ? get_test($test_id) : null;
$assigned_students = [];

if ($selected_test) {
    $stmt = $pdo->prepare("
        SELECT st.id AS assignment_id, st.status AS exam_status, st.started_at, st.submitted_at, st.score,
               s.id AS student_id, s.name, s.uid, s.mobile, s.status AS student_status
        FROM student_tests st
        INNER JOIN students s ON s.id = st.student_id
        WHERE st.test_id = ?
        ORDER BY s.name ASC
    ");
    $stmt->execute([$test_id]);
    $assigned_students = $stmt->fetchAll();
}

// Active unassigned students for dropdown
$unassigned_students = [];
if ($selected_test) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.uid
        FROM students s
        WHERE s.status = 'active'
          AND s.id NOT IN (SELECT student_id FROM student_tests WHERE test_id = ?)
        ORDER BY s.name ASC
    ");
    $stmt->execute([$test_id]);
    $unassigned_students = $stmt->fetchAll();
}

$page_title = 'Assignments — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'assignments');
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Candidate Assignments</h1>
            <p class="page-subtitle">Assign students to examinations and manage test seatings</p>
        </div>
        <?php if ($selected_test): ?>
            <div style="display: flex; gap: 10px;">
                <form method="POST" action="assign.php" style="display: inline-block;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="test_id" value="<?= $selected_test['id'] ?>">
                    <input type="hidden" name="mode" value="all">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Assign all active students to this test?');">
                        ⚡ Assign All Active Students
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Test Selector Filter -->
    <div class="card" style="padding: 16px 20px;">
        <form method="GET" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <label class="form-label" style="margin: 0; white-space: nowrap;">Select Test:</label>
            <select name="test_id" class="form-select" style="max-width: 400px;" onchange="this.form.submit()">
                <?php foreach ($tests as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $t['id'] == $test_id ? 'selected' : '' ?>>
                        <?= e($t['title']) ?> (<?= (int)$t['duration_minutes'] ?>m) — [<?= e(status_label($t['status'])) ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_test && !empty($unassigned_students)): ?>
        <div class="card" style="padding: 18px 24px;">
            <h3 style="font-size: 15px; margin-bottom: 12px;">Assign Individual Student</h3>
            <form method="POST" action="assign.php" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="test_id" value="<?= $selected_test['id'] ?>">
                <input type="hidden" name="mode" value="single">

                <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
                    <select name="student_id" class="form-select" required>
                        <option value="">— Select an unassigned student —</option>
                        <?php foreach ($unassigned_students as $us): ?>
                            <option value="<?= $us['id'] ?>"><?= e($us['name']) ?> (<?= e($us['uid']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">Assign Student</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="data-table-wrapper" style="border: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>Student Name</th>
                        <th>Mobile</th>
                        <th>Exam Progress</th>
                        <th>Started At</th>
                        <th>Submitted At</th>
                        <th>Score</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assigned_students)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                No candidates assigned to this test yet. Click <strong>⚡ Assign All Active Students</strong> to assign.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assigned_students as $row): ?>
                            <tr>
                                <td><code><?= e($row['uid']) ?></code></td>
                                <td><strong><?= e($row['name']) ?></strong></td>
                                <td><?= e($row['mobile'] ?? '—') ?></td>
                                <td>
                                    <span class="badge badge-<?= $row['exam_status'] === 'submitted' ? 'completed' : ($row['exam_status'] === 'started' ? 'active' : 'draft') ?>">
                                        <?= e(status_label($row['exam_status'])) ?>
                                    </span>
                                </td>
                                <td><?= $row['started_at'] ? date('H:i:s', strtotime($row['started_at'])) : '—' ?></td>
                                <td><?= $row['submitted_at'] ? date('H:i:s', strtotime($row['submitted_at'])) : '—' ?></td>
                                <td><?= $row['score'] !== null ? format_score($row['score']) : '—' ?></td>
                                <td>
                                    <?php if ($row['exam_status'] !== 'submitted'): ?>
                                        <form method="POST" action="remove.php" style="display: inline-block;" onsubmit="return confirm('Remove this student from the exam?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="assignment_id" value="<?= $row['assignment_id'] ?>">
                                            <input type="hidden" name="test_id" value="<?= $selected_test['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--text-muted);">Locked (Submitted)</span>
                                    <?php endif; ?>
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
