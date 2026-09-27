<?php
// students/edit.php — Edit Student
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$student_id = int_param('id', 0);
if ($student_id <= 0) {
    redirect('index.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    abort_html(404, 'Student Not Found');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name          = str_param('name', '', 'post');
    $mobile        = str_param('mobile', '', 'post');
    $date_of_birth = str_param('date_of_birth', '', 'post');
    $status        = str_param('status', 'active', 'post');
    $new_password  = $_POST['password'] ?? '';

    if ($name === '') {
        $error = 'Student name cannot be empty.';
    } else {
        try {
            if ($new_password !== '') {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET name = ?, mobile = ?, date_of_birth = ?, status = ?, password_hash = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $mobile !== '' ? $mobile : null,
                    $date_of_birth !== '' ? $date_of_birth : null,
                    $status,
                    $password_hash,
                    $student_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET name = ?, mobile = ?, date_of_birth = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $mobile !== '' ? $mobile : null,
                    $date_of_birth !== '' ? $date_of_birth : null,
                    $status,
                    $student_id
                ]);
            }

            $success = 'Student details updated successfully!';
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
        } catch (Throwable $e) {
            $ref = app_log('error', 'Edit student error: ' . $e->getMessage());
            $error = 'Failed to update student. Ref: ' . $ref;
        }
    }
}

$page_title = 'Edit Student — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'students');
?>

<div class="page-content" style="max-width: 600px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Student</h1>
            <p class="page-subtitle">UID: <strong><?= e($student['uid']) ?></strong></p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Back to Students</a>
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
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-input" value="<?= e($student['name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Mobile Number</label>
                <input type="text" name="mobile" class="form-input" value="<?= e($student['mobile'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-input" value="<?= e($student['date_of_birth'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $student['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $student['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="form-group" style="padding-top: 12px; border-top: 1px solid var(--border-subtle);">
                <label class="form-label">Reset Password (Leave blank to keep unchanged)</label>
                <input type="password" name="password" class="form-input" placeholder="New password">
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php layout_footer(); ?>
