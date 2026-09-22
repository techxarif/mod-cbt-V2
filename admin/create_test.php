<?php

require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

require_once __DIR__ . '/../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');

    $duration = (int)($_POST['duration_minutes'] ?? 0);

    $totalMarks = (float)($_POST['total_marks'] ?? 0);

    $negativeMarks = (float)($_POST['negative_marks'] ?? 0);


    if ($title === '') {

        $error = 'Please enter a test name.';

    } elseif ($duration <= 0) {

        $error = 'Duration must be greater than 0.';

    } elseif ($totalMarks < 0) {

        $error = 'Total marks cannot be negative.';

    } elseif ($negativeMarks < 0) {

        $error = 'Negative marks cannot be negative.';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO tests
            (
                title,
                duration_minutes,
                total_marks,
                negative_marks,
                status
            )
            VALUES (?, ?, ?, ?, 'draft')
        ");

        $stmt->execute([
            $title,
            $duration,
            $totalMarks,
            $negativeMarks
        ]);

        header('Location: tests.php');
        exit;
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

    <title>Create Test — MODUS CBT</title>

    <style>

        :root {
            --bg: #05070b;
            --panel: rgba(14, 18, 28, 0.82);
            --panel-strong: rgba(18, 23, 35, 0.96);
            --border: rgba(255,255,255,0.09);
            --text: #f4f7fb;
            --muted: #8e98aa;
            --blue: #5b8cff;
            --purple: #8b5cf6;
            --danger: #ff6b81;
        }

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
                    circle at 15% 15%,
                    rgba(91,140,255,0.14),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 85% 20%,
                    rgba(139,92,246,0.12),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 50% 100%,
                    rgba(42,111,255,0.08),
                    transparent 35%
                ),
                var(--bg);

            color: var(--text);

            overflow-x: hidden;
        }

        /* Ambient background */

        body::before,
        body::after {
            content: "";
            position: fixed;

            width: 420px;
            height: 420px;

            border-radius: 50%;

            filter: blur(110px);

            opacity: 0.18;

            pointer-events: none;

            z-index: -1;

            animation: floatGlow 12s ease-in-out infinite alternate;
        }

        body::before {
            top: -160px;
            left: -120px;

            background: #316cff;
        }

        body::after {
            right: -150px;
            bottom: -180px;

            background: #8b5cf6;

            animation-delay: -5s;
        }

        @keyframes floatGlow {

            from {
                transform: translate3d(0, 0, 0) scale(1);
            }

            to {
                transform: translate3d(30px, 25px, 0) scale(1.08);
            }

        }

        /* Topbar */

        .topbar {

            height: 72px;

            position: sticky;
            top: 0;

            z-index: 50;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 32px;

            background:
                rgba(5, 7, 11, 0.76);

            border-bottom: 1px solid var(--border);

            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
        }

        .brand {

            display: flex;
            align-items: center;
            gap: 12px;

            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .brand-mark {

            width: 34px;
            height: 34px;

            border-radius: 10px;

            display: grid;
            place-items: center;

            background:
                linear-gradient(
                    135deg,
                    var(--blue),
                    var(--purple)
                );

            box-shadow:
                0 0 30px rgba(91,140,255,0.25);

            font-size: 15px;
            font-weight: 900;
        }

        .brand-text {

            font-size: 16px;
        }

        .brand-text span {

            color: #8f9bb0;
            font-weight: 500;
            margin-left: 4px;
        }

        .top-actions {

            display: flex;
            align-items: center;
            gap: 10px;
        }

        .top-link {

            color: #c7cfdd;

            text-decoration: none;

            font-size: 13px;
            font-weight: 600;

            padding: 9px 13px;

            border-radius: 9px;

            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .top-link:hover {

            color: white;

            background: rgba(255,255,255,0.06);

            transform: translateY(-1px);
        }

        .logout {

            color: #ff9baa;
        }

        .logout:hover {

            color: #ffb7c2;

            background: rgba(255,91,113,0.08);
        }

        /* Main */

        .page {

            width: min(100%, 920px);

            margin: 0 auto;

            padding: 52px 24px 80px;
        }

        /* Header */

        .page-header {

            margin-bottom: 30px;

            animation: fadeUp 0.55s ease both;
        }

        .eyebrow {

            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: #7ea4ff;

            font-size: 11px;
            font-weight: 800;

            letter-spacing: 1.8px;

            text-transform: uppercase;

            margin-bottom: 12px;
        }

        .eyebrow-dot {

            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #5b8cff;

            box-shadow:
                0 0 0 4px rgba(91,140,255,0.08),
                0 0 15px rgba(91,140,255,0.7);

            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%, 100% {
                opacity: 0.55;
                transform: scale(0.9);
            }

            50% {
                opacity: 1;
                transform: scale(1.1);
            }

        }

        .page-header h1 {

            margin: 0;

            font-size: clamp(30px, 5vw, 44px);

            line-height: 1.05;

            letter-spacing: -1.8px;

            font-weight: 800;
        }

        .page-header p {

            margin: 13px 0 0;

            color: var(--muted);

            font-size: 14px;

            line-height: 1.7;

            max-width: 620px;
        }

        /* Main card */

        .form-card {

            position: relative;

            background:
                linear-gradient(
                    145deg,
                    rgba(20,25,38,0.88),
                    rgba(9,12,19,0.92)
                );

            border: 1px solid var(--border);

            border-radius: 22px;

            padding: 32px;

            box-shadow:
                0 30px 90px rgba(0,0,0,0.38),
                inset 0 1px 0 rgba(255,255,255,0.025);

            overflow: hidden;

            animation: fadeUp 0.65s 0.08s ease both;
        }

        .form-card::before {

            content: "";

            position: absolute;

            top: 0;
            left: 12%;

            width: 76%;
            height: 1px;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(91,140,255,0.65),
                    rgba(139,92,246,0.65),
                    transparent
                );
        }

        .form-card::after {

            content: "";

            position: absolute;

            width: 280px;
            height: 280px;

            right: -150px;
            top: -150px;

            border-radius: 50%;

            background: rgba(91,140,255,0.08);

            filter: blur(70px);

            pointer-events: none;
        }

        /* Card heading */

        .card-heading {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 30px;

            position: relative;
            z-index: 1;
        }

        .card-heading h2 {

            margin: 0;

            font-size: 20px;

            letter-spacing: -0.4px;
        }

        .card-heading p {

            margin: 7px 0 0;

            color: var(--muted);

            font-size: 13px;

            line-height: 1.6;
        }

        .step-badge {

            flex-shrink: 0;

            padding: 7px 11px;

            border-radius: 999px;

            background: rgba(91,140,255,0.08);

            border: 1px solid rgba(91,140,255,0.16);

            color: #8eafff;

            font-size: 11px;

            font-weight: 800;

            letter-spacing: 0.8px;

            text-transform: uppercase;
        }

        /* Error */

        .error {

            position: relative;
            z-index: 2;

            margin-bottom: 22px;

            padding: 13px 15px;

            border-radius: 12px;

            border: 1px solid rgba(255,107,129,0.18);

            background: rgba(255,107,129,0.07);

            color: #ff9baa;

            font-size: 13px;

            line-height: 1.5;
        }

        /* Form */

        form {

            position: relative;
            z-index: 2;
        }

        .field {

            margin-bottom: 22px;
        }

        .field-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 18px;
        }

        label {

            display: block;

            margin-bottom: 9px;

            color: #dce3ee;

            font-size: 12px;

            font-weight: 700;

            letter-spacing: 0.2px;
        }

        .label-note {

            color: #707b8e;

            font-size: 11px;

            font-weight: 500;

            margin-left: 5px;
        }

        input {

            width: 100%;

            height: 50px;

            padding: 0 15px;

            border-radius: 11px;

            border: 1px solid rgba(255,255,255,0.09);

            outline: none;

            background: rgba(4,7,12,0.72);

            color: white;

            font-family: inherit;

            font-size: 14px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        input::placeholder {

            color: #586375;
        }

        input:hover {

            border-color: rgba(255,255,255,0.14);
        }

        input:focus {

            border-color: rgba(91,140,255,0.7);

            background: rgba(7,10,17,0.95);

            box-shadow:
                0 0 0 3px rgba(91,140,255,0.09),
                0 0 25px rgba(91,140,255,0.06);
        }

        .field-help {

            margin-top: 7px;

            color: #687386;

            font-size: 11px;

            line-height: 1.5;
        }

        /* Divider */

        .divider {

            height: 1px;

            margin: 28px 0;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255,255,255,0.08),
                    transparent
                );
        }

        /* Buttons */

        .buttons {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 14px;

            margin-top: 30px;
        }

        .back {

            display: inline-flex;

            align-items: center;
            justify-content: center;

            min-height: 48px;

            padding: 0 18px;

            border-radius: 11px;

            border: 1px solid rgba(255,255,255,0.09);

            background: rgba(255,255,255,0.025);

            color: #b8c1cf;

            text-decoration: none;

            font-size: 13px;
            font-weight: 700;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .back:hover {

            color: white;

            background: rgba(255,255,255,0.055);

            border-color: rgba(255,255,255,0.15);

            transform: translateY(-1px);
        }

        .create-btn {

            position: relative;

            min-height: 48px;

            padding: 0 23px;

            border: 0;

            border-radius: 11px;

            overflow: hidden;

            cursor: pointer;

            color: white;

            font-family: inherit;

            font-size: 13px;

            font-weight: 800;

            letter-spacing: 0.2px;

            background:
                linear-gradient(
                    135deg,
                    #4d7fff,
                    #7457ed,
                    #8b5cf6
                );

            box-shadow:
                0 12px 30px rgba(91,140,255,0.22);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .create-btn::before {

            content: "";

            position: absolute;

            top: 0;
            bottom: 0;

            left: -80px;

            width: 55px;

            transform: skewX(-18deg);

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255,255,255,0.35),
                    transparent
                );

            transition: left 0.55s ease;
        }

        .create-btn:hover {

            transform: translateY(-2px);

            box-shadow:
                0 17px 38px rgba(91,140,255,0.3);
        }

        .create-btn:hover::before {

            left: calc(100% + 50px);
        }

        .create-btn:active {

            transform: translateY(0);
        }

        /* Bottom info */

        .info-strip {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 10px;

            margin-top: 24px;

            position: relative;
            z-index: 2;
        }

        .info-item {

            padding: 13px 14px;

            border-radius: 11px;

            background: rgba(255,255,255,0.025);

            border: 1px solid rgba(255,255,255,0.055);
        }

        .info-item strong {

            display: block;

            color: #dce3ee;

            font-size: 11px;

            margin-bottom: 4px;
        }

        .info-item span {

            color: #687386;

            font-size: 10px;

            line-height: 1.4;
        }

        /* Animation */

        @keyframes fadeUp {

            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }

        }

        /* Responsive */

        @media (max-width: 700px) {

            .topbar {

                height: auto;

                min-height: 68px;

                padding: 12px 17px;

            }

            .top-actions {

                gap: 2px;
            }

            .top-link {

                padding: 8px;
                font-size: 11px;
            }

            .page {

                padding:
                    34px
                    15px
                    60px;
            }

            .form-card {

                padding: 22px 18px;

                border-radius: 17px;
            }

            .card-heading {

                flex-direction: column;

                gap: 13px;
            }

            .field-grid {

                grid-template-columns: 1fr;
            }

            .buttons {

                flex-direction: column-reverse;

                align-items: stretch;
            }

            .back,
            .create-btn {

                width: 100%;
            }

            .info-strip {

                grid-template-columns: 1fr;
            }

        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                animation-duration: 0.01ms !important;

                animation-iteration-count: 1 !important;

                scroll-behavior: auto !important;

                transition-duration: 0.01ms !important;
            }

        }

    </style>

</head>


<body>


<!-- TOPBAR -->

<header class="topbar">

    <div class="brand">

        <div class="brand-mark">
            M
        </div>

        <div class="brand-text">
            MODUS <span>CBT</span>
        </div>

    </div>


    <nav class="top-actions">

        <a
            href="dashboard.php"
            class="top-link"
        >
            Dashboard
        </a>

        <a
            href="../logout.php"
            class="top-link logout"
        >
            Logout
        </a>

    </nav>

</header>



<main class="page">


    <!-- PAGE HEADER -->

    <section class="page-header">

        <div class="eyebrow">

            <span class="eyebrow-dot"></span>

            EXAM MANAGEMENT

        </div>


        <h1>
            Create Test
        </h1>


        <p>
            Configure the core settings of your examination.
            Questions, student assignment and exam controls
            can be managed after the test is created.
        </p>

    </section>



    <!-- FORM CARD -->

    <section class="form-card">


        <div class="card-heading">

            <div>

                <h2>
                    Test Configuration
                </h2>

                <p>
                    Define the basic rules and scoring system
                    for this examination.
                </p>

            </div>


            <div class="step-badge">
                Step 01
            </div>

        </div>



        <?php if ($error): ?>

            <div class="error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>



        <form method="POST">


            <!-- TEST NAME -->

            <div class="field">

                <label for="title">
                    Test Name
                </label>

                <input
                    id="title"
                    type="text"
                    name="title"
                    placeholder="Example: NEET Biology Minor Test 01"
                    autocomplete="off"
                    required
                >

                <div class="field-help">
                    Use a clear name so students and teachers can
                    identify the test easily.
                </div>

            </div>



            <!-- DURATION + MARKS -->

            <div class="field-grid">


                <div class="field">

                    <label for="duration_minutes">
                        Duration
                        <span class="label-note">
                            in minutes
                        </span>
                    </label>

                    <input
                        id="duration_minutes"
                        type="number"
                        name="duration_minutes"
                        value="60"
                        min="1"
                        required
                    >

                    <div class="field-help">
                        The exam timer will use this duration.
                    </div>

                </div>



                <div class="field">

                    <label for="total_marks">
                        Total Marks
                    </label>

                    <input
                        id="total_marks"
                        type="number"
                        name="total_marks"
                        value="100"
                        min="0"
                        step="0.01"
                        required
                    >

                    <div class="field-help">
                        Maximum marks available in the test.
                    </div>

                </div>


            </div>



            <!-- NEGATIVE MARKING -->

            <div class="field">

                <label for="negative_marks">
                    Negative Marks
                    <span class="label-note">
                        per wrong answer
                    </span>
                </label>

                <input
                    id="negative_marks"
                    type="number"
                    name="negative_marks"
                    value="0"
                    min="0"
                    step="0.01"
                    required
                >

                <div class="field-help">
                    Enter 0 for no negative marking. For example,
                    enter 1 for −1 mark on every incorrect answer.
                </div>

            </div>



            <div class="divider"></div>



            <!-- BUTTONS -->

            <div class="buttons">

                <a
                    href="tests.php"
                    class="back"
                >
                    ← Back to Tests
                </a>


                <button
                    type="submit"
                    class="create-btn"
                >
                    Create Test
                </button>

            </div>


        </form>



        <!-- INFO STRIP -->

        <div class="info-strip">

            <div class="info-item">

                <strong>
                    Questions
                </strong>

                <span>
                    Add MCQs after creating the test.
                </span>

            </div>


            <div class="info-item">

                <strong>
                    Students
                </strong>

                <span>
                    Assign students from the test management page.
                </span>

            </div>


            <div class="info-item">

                <strong>
                    Exam Control
                </strong>

                <span>
                    Start and complete the examination separately.
                </span>

            </div>

        </div>


    </section>


</main>


</body>

</html>