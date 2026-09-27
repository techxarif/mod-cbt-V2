<?php
// auth/signup.php — Teacher Registration (Guarded: Single-Administrator Policy)
require_once __DIR__ . '/../includes/bootstrap.php';

if (teacherLoggedIn()) {
    redirect('../dashboard/index.php');
}

// Check if an admin account already exists
$existing_teacher = null;
try {
    $chk_stmt = $pdo->query("SELECT id, username, name FROM teachers LIMIT 1");
    $existing_teacher = $chk_stmt->fetch();
} catch (Throwable $e) {
    // Database fallback
}

$signup_disabled = !empty($existing_teacher);
$error   = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    
    if ($signup_disabled) {
        $error = "Administrator signup is disabled. An account already exists with username '" . ($existing_teacher['username'] ?? '') . "'. Only one administrator is permitted. To make any changes, you must first delete that admin account.";
    } else {
        $rl = enforce_rate_limit('teacher_signup', 5, 15);

        $name             = str_param('name', '', 'post');
        $username         = str_param('username', '', 'post');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($name === '' || $username === '' || $password === '' || $confirm_password === '') {
            $error = 'Please fill in all fields.';
        } elseif (strlen($name) < 2) {
            $error = 'Please enter a valid name.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must contain at least 3 characters.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must contain at least 6 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM teachers WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);

                if ($stmt->fetch()) {
                    $error = 'This username is already registered.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO teachers (username, password_hash, name) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $password_hash, $name]);

                    $success = 'Account created successfully! You can now log in.';
                }
            } catch (Throwable $e) {
                $ref = app_log('error', 'Teacher signup error: ' . $e->getMessage());
                $error = 'A system error occurred. Ref: ' . $ref;
            }
        }
    }
}

$page_title = 'Admin Registration — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo.png">
    <link rel="apple-touch-icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            background: var(--bg);
        }
        .signup-card {
            width: 100%;
            max-width: 460px;
            background: var(--panel);
            border: 1px solid var(--line2);
            border-radius: var(--radius-lg);
            padding: 34px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
        }
        .signup-header {
            margin-bottom: 24px;
            text-align: center;
        }
        .disabled-box {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 12px;
            padding: 22px;
            text-align: center;
            margin-bottom: 20px;
        }
        .lock-icon-badge {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 16px auto;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body>

<div class="signup-card">
    <?php if ($signup_disabled): ?>
        <!-- Sign Up Disabled Notice (One admin already registered) -->
        <div class="signup-header">
            <div class="lock-icon-badge">🔒</div>
            <h1 class="page-title" style="font-size: 22px; color: #f8fafc; margin-bottom: 6px;">Sign Up Disabled</h1>
            <p class="page-subtitle" style="color: var(--muted); font-size: 13px;">Single-Administrator Policy Enforced</p>
        </div>

        <div class="disabled-box">
            <p style="font-size: 14px; color: #f1f5f9; line-height: 1.6; margin-bottom: 12px;">
                An administrator account already exists with username:
            </p>
            <div style="font-family: 'JetBrains Mono', monospace; font-size: 17px; font-weight: 800; color: var(--green); background: rgba(0, 0, 0, 0.4); padding: 8px 14px; border-radius: 8px; border: 1px solid var(--line); display: inline-block; margin-bottom: 14px;">
                <?= e($existing_teacher['username']) ?>
            </div>
            <p style="font-size: 12.5px; color: #94a3b8; line-height: 1.6;">
                Only one administrator account is permitted in Modus CBT. To make any changes or create a new account, you must first log in and delete or reset the existing admin account.
            </p>
        </div>

        <a href="login.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
            ← Proceed to Administrator Login
        </a>

    <?php else: ?>
        <!-- Normal Sign Up Form (Only when 0 teachers exist) -->
        <div class="signup-header">
            <h1 class="page-title" style="font-size: 22px;">Create Admin Account</h1>
            <p class="page-subtitle">Register your initial administrator workspace</p>
        </div>

        <?php if ($error): ?>
            <?= alert_box('error', $error) ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <?= alert_box('success', $success) ?>
            <p style="text-align: center; margin-top: 16px;"><a href="login.php" class="btn btn-primary">Proceed to Login</a></p>
        <?php else: ?>
            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Full Name</label>
                    <input type="text" name="name" class="form-input" placeholder="e.g. Dr. Jane Smith" value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Username</label>
                    <input type="text" name="username" class="form-input" placeholder="e.g. janesmith" value="<?= e($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="At least 6 characters" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="Re-enter password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Administrator Account</button>
            </form>

            <div style="text-align: center; margin-top: 20px; font-size: 13px; color: var(--muted);">
                Already have an account? <a href="login.php" style="color: var(--green); text-decoration: none; font-weight: 600;">Log in</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="../assets/js/ui.js"></script>
</body>
</html>
