<?php
// students/add.php — Add Student
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name          = str_param('name', '', 'post');
    $mobile        = str_param('mobile', '', 'post');
    $date_of_birth = str_param('date_of_birth', '', 'post');
    $password      = $_POST['password'] ?? '';

    if ($name === '') {
        $error = 'Please enter the student\'s name.';
    } elseif ($password === '') {
        $error = 'Please enter a password for the student.';
    } else {
        try {
            // Generate unique UID (e.g. STU + 6 digit random number)
            $uid = 'STU' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO students (name, uid, mobile, date_of_birth, password_hash, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $dobVal = $date_of_birth !== '' ? $date_of_birth : '2000-01-01';
            $stmt->execute([
                $name,
                $uid,
                $mobile,
                $dobVal,
                $password_hash
            ]);

            redirect('index.php');
        } catch (Throwable $e) {
            $ref = app_log('error', 'Add student error: ' . $e->getMessage());
            $error = 'Failed to add student. Ref: ' . $ref;
        }
    }
}

$page_title = 'Add Student — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'students');
?>

<div class="page-content" style="max-width: 600px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Add Student</h1>
            <p class="page-subtitle">Register a candidate manually</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Back to Students</a>
    </div>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. John Doe" value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Mobile Number (Optional)</label>
                <input type="text" name="mobile" class="form-input" placeholder="e.g. 9876543210" value="<?= e($_POST['mobile'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Date of Birth (Optional)</label>
                <input type="date" name="date_of_birth" class="form-input" value="<?= e($_POST['date_of_birth'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Login Password</label>
                <input type="password" name="password" class="form-input" placeholder="Initial password for student" required>
            </div>

            <div style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Student</button>
            </div>
        </form>
    </div>
</div>

<?php layout_footer(); ?>
