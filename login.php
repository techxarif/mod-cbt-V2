<?php

session_start();

require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['teacher_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter username and password.';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                password_hash,
                name
            FROM teachers
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $teacher = $stmt->fetch();

        if (
            $teacher &&
            password_verify(
                $password,
                $teacher['password_hash']
            )
        ) {

            session_regenerate_id(true);

            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['teacher_username'] = $teacher['username'];

            header('Location: admin/dashboard.php');
            exit;

        } else {

            $error = 'Invalid username or password.';
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
        content="width=device-width, initial-scale=1.0"
    >

    <title>MODUS CBT - Teacher Login</title>


    <style>

        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                radial-gradient(
                    circle at 15% 20%,
                    rgba(34,197,94,0.09),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 85% 15%,
                    rgba(59,130,246,0.09),
                    transparent 32%
                ),
                #05080b;

            color: #f8fafc;

            overflow: hidden;
        }


        /* =====================================================
           BACKGROUND
           ===================================================== */

        .background {

            position: fixed;

            inset: 0;

            overflow: hidden;

            pointer-events: none;

            z-index: 0;
        }


        .grid {

            position: absolute;

            inset: 0;

            background-image:
                linear-gradient(
                    rgba(255,255,255,0.025) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(255,255,255,0.025) 1px,
                    transparent 1px
                );

            background-size: 55px 55px;

            mask-image:
                linear-gradient(
                    to bottom,
                    black,
                    transparent 90%
                );
        }


        .orb {

            position: absolute;

            border-radius: 50%;

            filter: blur(90px);

            opacity: 0.16;

            animation:
                float 12s ease-in-out infinite;
        }


        .orb-one {

            width: 330px;

            height: 330px;

            background: #22c55e;

            top: -130px;

            left: -110px;
        }


        .orb-two {

            width: 360px;

            height: 360px;

            background: #2563eb;

            right: -150px;

            top: 15%;

            animation-delay: -4s;
        }


        .orb-three {

            width: 280px;

            height: 280px;

            background: #8b5cf6;

            bottom: -130px;

            left: 35%;

            animation-delay: -8s;
        }


        @keyframes float {

            0%,
            100% {

                transform:
                    translate(0, 0)
                    scale(1);
            }

            50% {

                transform:
                    translate(30px, -25px)
                    scale(1.08);
            }
        }


        /* =====================================================
           TOP BRAND
           ===================================================== */

        .topbar {

            position: fixed;

            top: 0;

            left: 0;

            right: 0;

            z-index: 5;

            height: 82px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0
                clamp(20px, 5vw, 70px);
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 11px;
        }


        .brand-mark {

            width: 38px;

            height: 38px;

            border-radius: 11px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #22c55e,
                    #16a34a
                );

            color: #031109;

            font-size: 17px;

            font-weight: 900;

            box-shadow:
                0 0 30px rgba(34,197,94,0.25);
        }


        .brand-name {

            font-size: 18px;

            font-weight: 750;

            letter-spacing: -0.4px;
        }


        .brand-sub {

            color: #64748b;

            font-size: 11px;

            margin-left: 4px;
        }


        .online {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 8px 12px;

            border:
                1px solid rgba(255,255,255,0.08);

            background:
                rgba(255,255,255,0.025);

            border-radius: 999px;

            color: #94a3b8;

            font-size: 11px;
        }


        .online-dot {

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 12px #22c55e;

            animation: pulse 2s infinite;
        }


        @keyframes pulse {

            0% {

                box-shadow:
                    0 0 0 0
                    rgba(34,197,94,.45);
            }

            70% {

                box-shadow:
                    0 0 0 8px
                    rgba(34,197,94,0);
            }

            100% {

                box-shadow:
                    0 0 0 0
                    rgba(34,197,94,0);
            }
        }


        /* =====================================================
           LOGIN AREA
           ===================================================== */

        .page {

            position: relative;

            z-index: 2;

            width: min(1120px, calc(100% - 40px));

            min-height: 100vh;

            margin: auto;

            display: grid;

            grid-template-columns:
                1fr
                430px;

            align-items: center;

            gap: 90px;

            padding-top: 45px;
        }


        /* =====================================================
           LEFT INFORMATION
           ===================================================== */

        .intro {

            animation:
                appear .8s ease both;
        }


        .eyebrow {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 7px 11px;

            border:
                1px solid rgba(34,197,94,.18);

            background:
                rgba(34,197,94,.06);

            border-radius: 999px;

            color: #86efac;

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .6px;
        }


        .eyebrow-dot {

            width: 5px;

            height: 5px;

            border-radius: 50%;

            background: #22c55e;
        }


        h1 {

            margin: 22px 0 0;

            max-width: 650px;

            font-size:
                clamp(48px, 6vw, 74px);

            line-height: .98;

            letter-spacing: -4px;

            font-weight: 800;
        }


        .gradient {

            background:
                linear-gradient(
                    100deg,
                    #ffffff 15%,
                    #94a3b8 52%,
                    #22c55e 95%
                );

            -webkit-background-clip: text;

            background-clip: text;

            color: transparent;
        }


        .description {

            max-width: 560px;

            margin-top: 24px;

            color: #8190a3;

            font-size: 15px;

            line-height: 1.75;
        }


        .features {

            display: flex;

            gap: 28px;

            margin-top: 30px;
        }


        .feature {

            display: flex;

            align-items: center;

            gap: 8px;

            color: #64748b;

            font-size: 11px;
        }


        .feature-icon {

            width: 26px;

            height: 26px;

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                rgba(255,255,255,.035);

            border:
                1px solid rgba(255,255,255,.07);

            color: #4ade80;
        }


        /* =====================================================
           LOGIN CARD
           ===================================================== */

        .card-wrapper {

            animation:
                appear .8s .12s ease both;
        }


        .login-card {

            position: relative;

            padding: 31px;

            border-radius: 22px;

            border:
                1px solid rgba(255,255,255,.09);

            background:
                linear-gradient(
                    145deg,
                    rgba(19,27,34,.90),
                    rgba(7,11,15,.96)
                );

            backdrop-filter: blur(22px);

            box-shadow:
                0 35px 100px rgba(0,0,0,.50),
                inset 0 1px 0
                rgba(255,255,255,.04);

            overflow: hidden;

            transition:
                transform .35s ease,
                border-color .35s ease;
        }


        .login-card:hover {

            transform: translateY(-4px);

            border-color:
                rgba(34,197,94,.20);
        }


        .card-glow {

            position: absolute;

            width: 190px;

            height: 190px;

            right: -80px;

            top: -80px;

            border-radius: 50%;

            background: #22c55e;

            filter: blur(75px);

            opacity: .07;

            pointer-events: none;
        }


        .card-header {

            position: relative;

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 28px;
        }


        .title {

            font-size: 20px;

            font-weight: 750;

            letter-spacing: -.3px;
        }


        .subtitle {

            margin-top: 6px;

            color: #64748b;

            font-size: 11px;
        }


        .login-icon {

            width: 42px;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 12px;

            background:
                rgba(34,197,94,.09);

            border:
                1px solid rgba(34,197,94,.16);

            color: #4ade80;

            font-size: 18px;
        }


        /* =====================================================
           ERROR
           ===================================================== */

        .error {

            position: relative;

            background:
                rgba(239,68,68,.08);

            border:
                1px solid rgba(239,68,68,.20);

            color: #fca5a5;

            padding: 11px 13px;

            border-radius: 10px;

            margin-bottom: 18px;

            font-size: 12px;

            animation:
                shake .35s ease;
        }


        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }


        /* =====================================================
           FORM
           ===================================================== */

        form {

            position: relative;
        }


        .field {

            margin-bottom: 18px;
        }


        label {

            display: block;

            margin-bottom: 8px;

            color: #cbd5e1;

            font-size: 12px;

            font-weight: 650;
        }


        .input-wrap {

            position: relative;
        }


        .input-icon {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #475569;

            font-size: 13px;

            pointer-events: none;
        }


        input {

            width: 100%;

            height: 47px;

            padding:
                0
                13px
                0
                38px;

            border:
                1px solid #263241;

            border-radius: 10px;

            background:
                rgba(4,8,12,.75);

            color: #f8fafc;

            font-size: 13px;

            outline: none;

            transition:
                border-color .25s ease,
                box-shadow .25s ease,
                background .25s ease;
        }


        input::placeholder {

            color: #475569;
        }


        input:focus {

            border-color:
                rgba(34,197,94,.55);

            background:
                rgba(4,8,12,.95);

            box-shadow:
                0 0 0 3px
                rgba(34,197,94,.07);
        }


        /* =====================================================
           BUTTON
           ===================================================== */

        .login-button {

            position: relative;

            width: 100%;

            height: 48px;

            margin-top: 4px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #22c55e,
                    #16a34a
                );

            color: #031109;

            font-size: 13px;

            font-weight: 800;

            cursor: pointer;

            overflow: hidden;

            box-shadow:
                0 10px 30px
                rgba(34,197,94,.14);

            transition:
                transform .25s ease,
                box-shadow .25s ease;
        }


        .login-button::before {

            content: "";

            position: absolute;

            top: 0;

            left: -100%;

            width: 70%;

            height: 100%;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255,255,255,.30),
                    transparent
                );

            transform: skewX(-20deg);

            transition:
                left .55s ease;
        }


        .login-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 14px 35px
                rgba(34,197,94,.22);
        }


        .login-button:hover::before {

            left: 140%;
        }


        .login-button:active {

            transform: translateY(0);
        }


        /* =====================================================
           FOOTER LINKS
           ===================================================== */

        .divider {

            display: flex;

            align-items: center;

            gap: 10px;

            margin:
                22px 0
                18px;

            color: #475569;

            font-size: 10px;
        }


        .divider::before,
        .divider::after {

            content: "";

            flex: 1;

            height: 1px;

            background:
                rgba(255,255,255,.06);
        }


        .signup {

            text-align: center;

            color: #64748b;

            font-size: 11px;
        }


        .signup a {

            color: #cbd5e1;

            text-decoration: none;

            font-weight: 700;

            margin-left: 3px;

            transition: color .2s ease;
        }


        .signup a:hover {

            color: #4ade80;
        }


        .secure {

            display: flex;

            justify-content: center;

            align-items: center;

            gap: 7px;

            margin-top: 19px;

            color: #475569;

            font-size: 9px;
        }


        .secure-dot {

            width: 5px;

            height: 5px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 8px #22c55e;
        }


        /* =====================================================
           BACK LINK
           ===================================================== */

        .back {

            position: fixed;

            left: 25px;

            bottom: 22px;

            z-index: 5;

            color: #475569;

            text-decoration: none;

            font-size: 11px;

            transition: color .2s ease;
        }


        .back:hover {

            color: #94a3b8;
        }


        /* =====================================================
           ANIMATION
           ===================================================== */

        @keyframes appear {

            from {

                opacity: 0;

                transform:
                    translateY(22px);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0);
            }
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 850px) {

            body {

                overflow-y: auto;
            }


            .page {

                grid-template-columns: 1fr;

                gap: 35px;

                padding:
                    110px 0
                    60px;
            }


            .intro {

                text-align: center;
            }


            .eyebrow {

                margin: auto;
            }


            h1 {

                font-size: 52px;

                letter-spacing: -3px;
            }


            .description {

                margin-left: auto;

                margin-right: auto;
            }


            .features {

                justify-content: center;
            }


            .card-wrapper {

                width: min(430px, 100%);

                margin: auto;
            }

        }


        @media (max-width: 520px) {

            .topbar {

                padding:
                    0
                    18px;
            }


            .online {

                display: none;
            }


            .page {

                width:
                    calc(100% - 28px);
            }


            h1 {

                font-size: 43px;

                letter-spacing: -2.5px;
            }


            .description {

                font-size: 13px;
            }


            .features {

                gap: 14px;

                flex-wrap: wrap;
            }


            .login-card {

                padding: 23px;
            }


            .back {

                display: none;
            }

        }

    </style>

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
                    ✓
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

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <form method="POST">


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

                <a href="teacher_signup.php">
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
    href="index.php"
    class="back"
>
    ← Back to MODUS
</a>


</body>

</html>