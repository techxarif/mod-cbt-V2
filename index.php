<?php

session_start();

if (isset($_SESSION['teacher_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

if (isset($_SESSION['student_id'])) {
    header('Location: student/waiting.php');
    exit;
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

    <title>MODUS CBT</title>

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
                    circle at 20% 20%,
                    rgba(34, 197, 94, 0.08),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 80% 10%,
                    rgba(59, 130, 246, 0.08),
                    transparent 30%
                ),
                #05080b;

            color: #f8fafc;

            overflow-x: hidden;
        }


        /* =====================================================
           BACKGROUND
           ===================================================== */

        .background {

            position: fixed;

            inset: 0;

            pointer-events: none;

            overflow: hidden;

            z-index: 0;
        }


        .orb {

            position: absolute;

            border-radius: 50%;

            filter: blur(80px);

            opacity: 0.18;

            animation: float 12s ease-in-out infinite;
        }


        .orb-one {

            width: 320px;
            height: 320px;

            background: #22c55e;

            top: -120px;
            left: -100px;
        }


        .orb-two {

            width: 360px;
            height: 360px;

            background: #2563eb;

            right: -150px;
            top: 10%;

            animation-delay: -4s;
        }


        .orb-three {

            width: 280px;
            height: 280px;

            background: #8b5cf6;

            bottom: -120px;
            left: 40%;

            animation-delay: -8s;
        }


        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(30px, -25px) scale(1.08);
            }
        }


        /* =====================================================
           GRID
           ===================================================== */

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


        /* =====================================================
           NAVBAR
           ===================================================== */

        .navbar {

            position: relative;

            z-index: 5;

            width: min(1180px, calc(100% - 40px));

            margin: 0 auto;

            padding: 26px 0;

            display: flex;

            align-items: center;

            justify-content: space-between;
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

            box-shadow:
                0 0 30px rgba(34,197,94,0.25);

            font-size: 17px;

            font-weight: 900;

            color: #031109;
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

            font-weight: 500;
        }


        .status {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 8px 12px;

            border: 1px solid rgba(255,255,255,0.08);

            background: rgba(255,255,255,0.025);

            border-radius: 999px;

            color: #94a3b8;

            font-size: 12px;
        }


        .status-dot {

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
                box-shadow: 0 0 0 0 rgba(34,197,94,.5);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(34,197,94,0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(34,197,94,0);
            }
        }


        /* =====================================================
           MAIN
           ===================================================== */

        .main {

            position: relative;

            z-index: 2;

            width: min(1180px, calc(100% - 40px));

            margin: 0 auto;

            min-height: calc(100vh - 91px);

            display: grid;

            grid-template-columns:
                1.05fr
                0.95fr;

            align-items: center;

            gap: 80px;

            padding: 50px 0 80px;
        }


        /* =====================================================
           LEFT SIDE
           ===================================================== */

        .eyebrow {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 7px 11px;

            border: 1px solid rgba(34,197,94,0.18);

            background:
                rgba(34,197,94,0.06);

            border-radius: 999px;

            color: #86efac;

            font-size: 11px;

            font-weight: 700;

            letter-spacing: 0.5px;

            text-transform: uppercase;

            animation: appear .8s ease both;
        }


        .eyebrow span {

            width: 5px;

            height: 5px;

            border-radius: 50%;

            background: #22c55e;
        }


        h1 {

            margin: 22px 0 0;

            font-size:
                clamp(48px, 7vw, 82px);

            line-height: 0.98;

            letter-spacing: -4px;

            font-weight: 800;

            max-width: 680px;

            animation:
                appear .8s .1s ease both;
        }


        .gradient-text {

            background:
                linear-gradient(
                    100deg,
                    #ffffff 15%,
                    #94a3b8 45%,
                    #22c55e 90%
                );

            -webkit-background-clip: text;

            background-clip: text;

            color: transparent;
        }


        .description {

            margin-top: 25px;

            max-width: 570px;

            color: #8b98aa;

            font-size: 16px;

            line-height: 1.75;

            animation:
                appear .8s .2s ease both;
        }


        .metrics {

            display: flex;

            gap: 35px;

            margin-top: 34px;

            animation:
                appear .8s .3s ease both;
        }


        .metric strong {

            display: block;

            font-size: 19px;

            color: #e2e8f0;
        }


        .metric span {

            display: block;

            margin-top: 4px;

            color: #64748b;

            font-size: 11px;
        }


        /* =====================================================
           LOGIN PANEL
           ===================================================== */

        .panel-wrapper {

            animation:
                appear .9s .25s ease both;
        }


        .login-panel {

            position: relative;

            padding: 30px;

            border-radius: 22px;

            border: 1px solid rgba(255,255,255,0.09);

            background:
                linear-gradient(
                    145deg,
                    rgba(20,27,34,0.88),
                    rgba(8,12,16,0.94)
                );

            backdrop-filter: blur(22px);

            box-shadow:
                0 35px 100px rgba(0,0,0,0.45),
                inset 0 1px 0 rgba(255,255,255,0.04);

            overflow: hidden;

            transition:
                transform .35s ease,
                border-color .35s ease;
        }


        .login-panel:hover {

            transform: translateY(-5px);

            border-color:
                rgba(34,197,94,0.20);
        }


        .panel-glow {

            position: absolute;

            width: 180px;

            height: 180px;

            background: #22c55e;

            opacity: .07;

            filter: blur(70px);

            right: -60px;

            top: -60px;

            pointer-events: none;
        }


        .panel-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 27px;
        }


        .panel-title {

            font-size: 18px;

            font-weight: 700;
        }


        .panel-subtitle {

            margin-top: 5px;

            color: #64748b;

            font-size: 12px;
        }


        .lock {

            width: 38px;

            height: 38px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            border: 1px solid rgba(255,255,255,.08);

            background: rgba(255,255,255,.035);

            font-size: 16px;
        }


        /* =====================================================
           ACCESS CARDS
           ===================================================== */

        .access-card {

            position: relative;

            display: block;

            text-decoration: none;

            color: white;

            padding: 20px;

            border: 1px solid rgba(255,255,255,0.08);

            background:
                rgba(255,255,255,0.025);

            border-radius: 15px;

            margin-bottom: 12px;

            overflow: hidden;

            transition:
                transform .3s ease,
                background .3s ease,
                border-color .3s ease,
                box-shadow .3s ease;
        }


        .access-card::before {

            content: "";

            position: absolute;

            width: 0;

            height: 100%;

            left: 0;

            top: 0;

            background:
                linear-gradient(
                    90deg,
                    rgba(34,197,94,.13),
                    transparent
                );

            transition: width .35s ease;
        }


        .access-card:hover {

            transform: translateX(5px);

            border-color:
                rgba(34,197,94,0.28);

            background:
                rgba(255,255,255,0.045);

            box-shadow:
                0 15px 35px rgba(0,0,0,.25);
        }


        .access-card:hover::before {

            width: 100%;
        }


        .access-content {

            position: relative;

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .access-icon {

            flex-shrink: 0;

            width: 45px;

            height: 45px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 12px;

            background:
                rgba(34,197,94,0.10);

            border:
                1px solid rgba(34,197,94,0.16);

            color: #4ade80;

            font-size: 18px;
        }


        .student .access-icon {

            background:
                rgba(59,130,246,0.10);

            border-color:
                rgba(59,130,246,0.16);

            color: #60a5fa;
        }


        .access-info {

            flex: 1;
        }


        .access-title {

            font-size: 14px;

            font-weight: 700;
        }


        .access-description {

            margin-top: 4px;

            color: #64748b;

            font-size: 11px;

            line-height: 1.4;
        }


        .arrow {

            position: relative;

            color: #64748b;

            font-size: 20px;

            transition:
                transform .3s ease,
                color .3s ease;
        }


        .access-card:hover .arrow {

            transform: translateX(5px);

            color: #4ade80;
        }


        .student:hover .arrow {

            color: #60a5fa;
        }


        /* =====================================================
           SIGNUP
           ===================================================== */

        .signup {

            margin-top: 22px;

            padding-top: 21px;

            border-top:
                1px solid rgba(255,255,255,.06);

            text-align: center;

            color: #64748b;

            font-size: 11px;
        }


        .signup a {

            color: #cbd5e1;

            text-decoration: none;

            font-weight: 700;

            margin-left: 4px;
        }


        .signup a:hover {

            color: #4ade80;
        }


        /* =====================================================
           SECURITY
           ===================================================== */

        .secure {

            display: flex;

            justify-content: center;

            align-items: center;

            gap: 7px;

            margin-top: 17px;

            color: #475569;

            font-size: 10px;
        }


        .secure-dot {

            width: 5px;

            height: 5px;

            background: #22c55e;

            border-radius: 50%;
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

            .main {

                grid-template-columns: 1fr;

                gap: 45px;

                padding-top: 35px;
            }

            h1 {

                font-size: 52px;

                letter-spacing: -3px;
            }

            .description {

                font-size: 14px;
            }

            .status {

                display: none;
            }

        }


        @media (max-width: 500px) {

            .navbar {

                width:
                    calc(100% - 28px);

                padding: 18px 0;
            }

            .main {

                width:
                    calc(100% - 28px);

                padding:
                    30px 0 50px;
            }

            h1 {

                font-size: 43px;

                letter-spacing: -2.5px;
            }

            .metrics {

                gap: 22px;
            }

            .login-panel {

                padding: 22px;
            }

            .brand-sub {

                display: none;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     ANIMATED BACKGROUND
     ========================================================= -->

<div class="background">

    <div class="grid"></div>

    <div class="orb orb-one"></div>

    <div class="orb orb-two"></div>

    <div class="orb orb-three"></div>

</div>


<!-- =========================================================
     NAVIGATION
     ========================================================= -->

<header class="navbar">

    <div class="brand">

        <div class="brand-mark">
            M
        </div>

        <div>

            <div class="brand-name">
                MODUS
                <span class="brand-sub">
                    CBT
                </span>
            </div>

        </div>

    </div>


    <div class="status">

        <span class="status-dot"></span>

        System Online

    </div>

</header>


<!-- =========================================================
     MAIN
     ========================================================= -->

<main class="main">


    <!-- LEFT -->

    <section>

        <div class="eyebrow">

            <span></span>

            Computer Based Testing

        </div>


        <h1>

            <span class="gradient-text">
                Testing.
            </span>

            <br>

            <span>
                Simplified.
            </span>

        </h1>


        <p class="description">

            A fast, reliable examination platform for
            coaching centres, teachers and students.
            Create tests, conduct examinations and
            manage results from one unified system.

        </p>


        <div class="metrics">

            <div class="metric">

                <strong>CBT</strong>

                <span>
                    Examination
                </span>

            </div>


            <div class="metric">

                <strong>24/7</strong>

                <span>
                    Local Access
                </span>

            </div>


            <div class="metric">

                <strong>Fast</strong>

                <span>
                    Results
                </span>

            </div>

        </div>

    </section>


    <!-- RIGHT -->

    <section class="panel-wrapper">

        <div class="login-panel">

            <div class="panel-glow"></div>


            <div class="panel-header">

                <div>

                    <div class="panel-title">
                        Access MODUS CBT
                    </div>

                    <div class="panel-subtitle">
                        Select your account type
                    </div>

                </div>


                <div class="lock">
                    ◈
                </div>

            </div>


            <!-- TEACHER -->

            <a
                href="login.php"
                class="access-card"
            >

                <div class="access-content">

                    <div class="access-icon">
                        ◉
                    </div>


                    <div class="access-info">

                        <div class="access-title">
                            Teacher Portal
                        </div>

                        <div class="access-description">
                            Create and manage tests,
                            students and examination results.
                        </div>

                    </div>


                    <div class="arrow">
                        →
                    </div>

                </div>

            </a>


            <!-- STUDENT -->

            <a
                href="student/login.php"
                class="access-card student"
            >

                <div class="access-content">

                    <div class="access-icon">
                        ◆
                    </div>


                    <div class="access-info">

                        <div class="access-title">
                            Student Portal
                        </div>

                        <div class="access-description">
                            Enter your examination and
                            complete assigned tests.
                        </div>

                    </div>


                    <div class="arrow">
                        →
                    </div>

                </div>

            </a>


            <!-- SIGNUP -->

            <div class="signup">

                New teacher?

                <a href="teacher_signup.php">
                    Create an account
                </a>

            </div>


            <div class="secure">

                <span class="secure-dot"></span>

                Secure MODUS examination environment

            </div>


        </div>

    </section>


</main>


</body>

</html>