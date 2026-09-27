<?php

// ============================================================
// MODUS CBT — Teacher Login
// Was: login.php (root)
// Now: auth/login.php
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

// Already logged in → redirect to dashboard
if (teacherLoggedIn()) {
    redirect('../dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Rate limit: 5 attempts per 15 seconds
    $rl = enforce_rate_limit('teacher_login', 5, 15);

    $username = str_param('username', '', 'post');
    $password = str_param('password', '', 'post');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, name
                FROM teachers
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $teacher = $stmt->fetch();

            if ($teacher && password_verify($password, $teacher['password_hash'])) {

                // Regenerate session ID on privilege escalation
                session_regenerate_id(true);

                $_SESSION['teacher_id']   = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['name'] ?? $teacher['username'];
                $_SESSION['username']     = $teacher['username'];

                // Clear rate limit on success
                clear_rate_limit('teacher_login');

                redirect('../dashboard/index.php');

            } else {

                // Record failed attempt
                record_failed_attempt($rl);

                // Generic message — don't reveal whether username or password is wrong
                $error = 'Invalid username or password.';
            }

        } catch (Throwable $e) {
            $ref   = app_log('error', 'Teacher login error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again. (Ref: ' . $ref . ')';
        }
    }
}

$page_title = 'Teacher Login — ' . APP_NAME;

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
<link rel="stylesheet" href="../assets/css/responsive.css">

</head>

<body>


<!-- =========================================================
     BACKGROUND
     ========================================================= -->

<div class="background">

    <div class="grid"></div>

    <div class="orb orb-one"></div>

    <div class="orb orb-two"></div>

    <div class="orb orb-three"></div>

</div>


<!-- =========================================================
     TOP BAR
     ========================================================= -->

<header class="topbar">

    <div class="brand">

        <div class="brand-mark">
            M
        </div>

        <div class="brand-name">

            MODUS

            <span class="brand-sub">
                CBT
            </span>

        </div>

    </div>


    <div class="online">

        <span class="online-dot"></span>

        System Online

    </div>

</header>


<!-- =========================================================
     MAIN
     ========================================================= -->

<main class="page">


    <!-- =====================================================
         INTRO
         ===================================================== -->

    <section class="intro">


        <div class="eyebrow">

            <span class="eyebrow-dot"></span>

            Teacher Portal

        </div>


        <h1>

            <span class="gradient">
                Run.
            </span>

            <br>

            Manage.

            <br>

            <span class="gradient">
                Analyze.
            </span>

        </h1>


        <p class="description">

            Access your MODUS CBT teacher workspace.
            Create examinations, manage students,
            conduct computer-based tests and review
            results from one powerful platform.

        </p>


        <div class="features">


            <div class="feature">

                <div class="feature-icon">
                    ✔
                </div>

                Fast CBT

            </div>


            <div class="feature">

                <div class="feature-icon">
                    ◇
                </div>

                Student Management

            </div>


            <div class="feature">

                <div class="feature-icon">
                    ↗
                </div>

                Live Results

            </div>


        </div>


    </section>


    <!-- =====================================================
         LOGIN CARD
         ===================================================== -->

    <section class="card-wrapper">


        <div class="login-card">


            <div class="card-glow"></div>


            <div class="card-header">


                <div>

                    <div class="title">
                        Welcome back
                    </div>

                    <div class="subtitle">
                        Sign in to your teacher account
                    </div>

                </div>


                <div class="login-icon">
                    ◉
                </div>


            </div>


            <?php if ($error): ?>

                <div class="error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <form method="POST">

                <?= csrf_field() ?>

                <div class="field">

                    <label>
                        Username
                    </label>

                    <div class="input-wrap">

                        <span class="input-icon">
                            @
                        </span>

                        <input
                            type="text"
                            name="username"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>

                </div>


                <div class="field">

                    <label>
                        Password
                    </label>

                    <div class="input-wrap">

                        <span class="input-icon">
                            •
                        </span>

                        <input
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="login-button"
                >

                    Sign in to MODUS

                </button>


            </form>


            <div class="divider">
                OR
            </div>


            <div class="signup">

                Don't have a teacher account?

                <a href="signup.php">
                    Create one
                </a>

            </div>


            <div class="secure">

                <span class="secure-dot"></span>

                Secure MODUS examination environment

            </div>


        </div>


    </section>


</main>


<a
    href="../index.php"
    class="back"
>
    ← Back to MODUS
</a>

<script src="../assets/js/ui.js"></script>
</body>
</html>
