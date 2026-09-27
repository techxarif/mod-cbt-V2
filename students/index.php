<?php
// students/index.php — Student Management
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$page = max(1, int_param('page', 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$search = str_param('q', '');

try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE name LIKE ? OR uid LIKE ? OR mobile LIKE ?");
        $term = "%$search%";
        $stmt->execute([$term, $term, $term]);
        $total_students = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id, name, uid, mobile, date_of_birth, status, created_at
            FROM students
            WHERE name LIKE ? OR uid LIKE ? OR mobile LIKE ?
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $term, PDO::PARAM_STR);
        $stmt->bindValue(2, $term, PDO::PARAM_STR);
        $stmt->bindValue(3, $term, PDO::PARAM_STR);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll();
    } else {
        $total_students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id, name, uid, mobile, date_of_birth, status, created_at
            FROM students
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $ref = app_log('error', 'Students list error: ' . $e->getMessage());
    abort_html(500, 'Error Loading Students', 'Ref: ' . $ref);
}

$total_pages = ceil($total_students / $limit);

$page_title = 'Students Management — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'students');
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Students</h1>
            <p class="page-subtitle">Manage candidate rosters, enrollments, and examination credentials</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="import.php" class="btn btn-secondary">📥 Import CSV</a>
            <a href="add.php" class="btn btn-primary">+ Add Student</a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card" style="padding: 16px 20px;">
        <form method="GET" style="display: flex; gap: 12px; align-items: center;">
            <input type="text" name="q" class="form-input" placeholder="Search by student name, UID, or mobile..." value="<?= e($search) ?>" style="max-width: 400px;">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
            <span style="margin-left: auto; font-size: 13px; color: var(--text-secondary);">
                Total: <strong><?= $total_students ?></strong> students
            </span>
        </form>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="data-table-wrapper" style="border: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>Student Name</th>
                        <th>Mobile</th>
                        <th>Date of Birth</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                No students found. Use <strong>+ Add Student</strong> or <strong>Import CSV</strong>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td>
                                    <code style="background: var(--bg-surface); padding: 2px 6px; border-radius: var(--radius-sm); font-weight: 600; color: var(--primary);">
                                        <?= e($s['uid']) ?>
                                    </code>
                                </td>
                                <td><strong><?= e($s['name']) ?></strong></td>
                                <td><?= e($s['mobile'] ?? '—') ?></td>
                                <td><?= (!empty($s['date_of_birth']) && $s['date_of_birth'] !== '0000-00-00') ? date('d M Y', strtotime($s['date_of_birth'])) : '—' ?></td>
                                <td>
                                    <span class="badge badge-<?= $s['status'] === 'active' ? 'active' : 'draft' ?>">
                                        <?= e(ucfirst($s['status'])) ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                                <td style="white-space: nowrap;">
                                    <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="delete.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this student?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?page=<?= $p ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php layout_footer(); ?>
