<?php

// ============================================================
// MODUS CBT — Student Login
// Was: student/login.php
// Now: exam_session/login.php
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

// Already logged in → redirect to waiting room
if (studentLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Rate limit: 5 attempts per 15 seconds per IP
    $rl = enforce_rate_limit('student_login', 5, 15);

    $uid      = str_param('uid',      '', 'post');
    $password = str_param('password', '', 'post');

    if ($uid === '' || $password === '') {
        $error = 'Please enter your UID and password.';
    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id, uid, name, password_hash, status
                FROM students
                WHERE uid = ?
                LIMIT 1
            ");
            $stmt->execute([$uid]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password_hash'])) {

                if ($student['status'] !== 'active') {
                    $error = 'Your account is not active. Please contact your examination centre.';
                } else {

                    session_regenerate_id(true);

                    $_SESSION['student_id']   = $student['id'];
                    $_SESSION['student_name'] = $student['name'];
                    $_SESSION['student_uid']  = $student['uid'];

                    clear_rate_limit('student_login');

                    redirect('index.php');
                }

            } else {

                record_failed_attempt($rl);

                // Generic message — don't reveal which field is wrong
                $error = 'Invalid UID or password.';
            }

        } catch (Throwable $e) {
            $ref   = app_log('error', 'Student login error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again. (Ref: ' . $ref . ')';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>
<title>Student Login — <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="../assets/img/logo.png">
<link rel="shortcut icon" type="image/png" href="../assets/img/logo.png">
<link rel="apple-touch-icon" href="../assets/img/logo.png">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/exam.css">
<link rel="stylesheet" href="../assets/css/responsive.css">

</head>

<body>

<div class="login-page">

    <!-- =================================================
         HEADER
    ================================================= -->

    <header class="header">

        <div class="brand">
            <div class="brand-logo-wrap">
                <img src="../assets/img/logo.png" alt="<?= e(APP_NAME) ?> Logo" class="brand-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                <div class="logo logo-fallback" style="display:none;">M</div>
            </div>
            <div class="brand-text">
                <div class="brand-name"><?= e(APP_NAME) ?></div>
                <div class="brand-subtitle">EXCELLENCE IN COMPUTER BASED TESTING</div>
            </div>
        </div>

        <div class="candidate-area">
            <div class="candidate-photo" title="Candidate Identity">👤</div>
            <div class="candidate-info">
                <div>
                    <span class="candidate-label">Candidate Name</span> :
                    <span class="candidate-value">Student Login</span>
                </div>
                <div>
                    <span class="candidate-label">Subject Name</span> :
                    <span class="candidate-value">CBT Examination</span>
                </div>
            </div>
        </div>

    </header>

    <!-- =================================================
         SYSTEM BAR
    ================================================= -->

    <div class="system-bar">
        <div class="system-name">
            <strong>System:</strong> <?= e(APP_NAME) ?> &nbsp;&bull;&nbsp; Candidate Authentication Portal
        </div>
        <div class="system-warning">
            Please use your assigned UID and password to continue.
        </div>
    </div>

    <!-- =================================================
         LOGIN AREA
    ================================================= -->

    <main class="login-area">

        <div class="login-box">

            <div class="login-box-header">
                <div class="login-badge">
                    <span class="badge-dot"></span>
                    <span>SECURE CANDIDATE PORTAL</span>
                </div>
                <h1 class="login-title">Sign In to Exam</h1>
                <p class="login-subtitle">Enter your registered credentials to access your scheduled test</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <!-- UID -->
                <div class="form-group">
                    <label class="form-label" for="studentUid">Student UID / Roll Number</label>
                    <div class="input-wrapper">
                        <span class="input-prefix-icon" aria-hidden="true">👤</span>
                        <input
                            type="text"
                            id="studentUid"
                            name="uid"
                            class="input input-with-prefix"
                            placeholder="e.g. STU5B6FC1"
                            maxlength="50"
                            autocomplete="username"
                            value="<?= isset($uid) ? e($uid) : '' ?>"
                            required
                        >
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="form-group">
                    <label class="form-label" for="studentPassword">Password (Default: Date of Birth DDMMYYYY)</label>
                    <div class="input-wrapper">
                        <span class="input-prefix-icon" aria-hidden="true">🔒</span>
                        <input
                            type="password"
                            id="studentPassword"
                            name="password"
                            class="input input-with-prefix input-with-suffix"
                            placeholder="Enter password (e.g. 14051992)"
                            maxlength="100"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="input-suffix-btn"
                            id="btnTogglePassword"
                            onclick="togglePasswordVisibility()"
                            title="Show password"
                            aria-label="Toggle password visibility"
                        >
                            <span id="pwdEyeIcon">👁️</span>
                        </button>
                    </div>
                </div>

                <!-- LOGIN -->
                <button type="submit" class="login-button">
                    <span>LOGIN TO EXAM</span>
                    <span class="btn-arrow" aria-hidden="true">➔</span>
                </button>

            </form>

            <div class="login-notice-box">
                <span class="notice-icon">ℹ️</span>
                <div class="notice-text">
                    <strong>Need help?</strong> Default password is your Date of Birth in <code>DDMMYYYY</code> format (e.g. <code>14051992</code>). Contact your exam invigilator if you cannot sign in.
                </div>
            </div>

        </div>

    </main>


    <!-- =================================================
         FOOTER
    ================================================= -->

    <footer class="footer">

        © <?= date('Y') ?> <?= e(APP_NAME) ?> &nbsp; | &nbsp;
        Computer Based Testing Platform

    </footer>


</div>


<script src="../assets/js/ui.js"></script>
<script>
function togglePasswordVisibility() {
    var pwdInput = document.getElementById('studentPassword');
    var eyeIcon = document.getElementById('pwdEyeIcon');
    var btn = document.getElementById('btnTogglePassword');
    if (!pwdInput) return;

    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        if (eyeIcon) eyeIcon.textContent = '🙈';
        if (btn) {
            btn.setAttribute('title', 'Hide password');
            btn.setAttribute('aria-label', 'Hide password');
        }
    } else {
        pwdInput.type = 'password';
        if (eyeIcon) eyeIcon.textContent = '👁️';
        if (btn) {
            btn.setAttribute('title', 'Show password');
            btn.setAttribute('aria-label', 'Show password');
        }
    }
}
</script>
</body>

</html>
