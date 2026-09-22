<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Handle assignment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $test_id = isset($_POST['test_id'])
        ? (int) $_POST['test_id']
        : 0;

    if ($test_id <= 0) {

        $error = "Please select a test.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check test
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    title,
                    status
                FROM tests
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$test_id]);

            $test = $stmt->fetch();

            if (!$test) {

                throw new Exception(
                    "Selected test does not exist."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Get all active students
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->query("
                SELECT id
                FROM students
                WHERE status = 'active'
                ORDER BY id ASC
            ");

            $students = $stmt->fetchAll();


            if (!$students) {

                throw new Exception(
                    "No active students found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Assign students
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();

            $insert = $pdo->prepare("
                INSERT IGNORE INTO student_tests (
                    student_id,
                    test_id,
                    status
                )
                VALUES (
                    ?,
                    ?,
                    'assigned'
                )
            ");

            $assigned = 0;

            foreach ($students as $student) {

                $insert->execute([
                    $student['id'],
                    $test_id
                ]);

                if ($insert->rowCount() > 0) {
                    $assigned++;
                }
            }

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $message =
                $assigned .
                " student(s) assigned to \"" .
                $test['title'] .
                "\" successfully.";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load tests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        duration_minutes,
        total_marks,
        status
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Assignment counts
|--------------------------------------------------------------------------
*/

$assignment_counts = [];

$stmt = $pdo->query("
    SELECT
        test_id,
        COUNT(*) AS total_students
    FROM student_tests
    GROUP BY test_id
");

foreach ($stmt->fetchAll() as $row) {

    $assignment_counts[$row['test_id']] =
        (int)$row['total_students'];
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

<title>Assign Students - MODUS CBT</title>

<style>

/* =========================================================
   RESET
   ========================================================= */

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
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    color: #e8eef7;

    background:
        radial-gradient(
            circle at 15% 10%,
            rgba(37, 99, 235, 0.16),
            transparent 32%
        ),
        radial-gradient(
            circle at 85% 20%,
            rgba(124, 58, 237, 0.13),
            transparent 30%
        ),
        #070b12;

    overflow-x: hidden;
}


/* =========================================================
   CINEMATIC BACKGROUND
   ========================================================= */

body::before {

    content: "";

    position: fixed;

    width: 520px;
    height: 520px;

    top: -180px;
    left: -180px;

    background:
        radial-gradient(
            circle,
            rgba(37, 99, 235, 0.13),
            transparent 68%
        );

    filter: blur(20px);

    pointer-events: none;

    animation:
        floatingGlow 12s ease-in-out infinite alternate;

    z-index: -2;
}


body::after {

    content: "";

    position: fixed;

    width: 600px;
    height: 600px;

    right: -250px;
    bottom: -250px;

    background:
        radial-gradient(
            circle,
            rgba(124, 58, 237, 0.12),
            transparent 68%
        );

    filter: blur(25px);

    pointer-events: none;

    animation:
        floatingGlow2 15s ease-in-out infinite alternate;

    z-index: -2;
}


@keyframes floatingGlow {

    from {
        transform: translate3d(0, 0, 0) scale(1);
    }

    to {
        transform: translate3d(90px, 70px, 0) scale(1.15);
    }
}


@keyframes floatingGlow2 {

    from {
        transform: translate3d(0, 0, 0) scale(1);
    }

    to {
        transform: translate3d(-80px, -60px, 0) scale(1.12);
    }
}


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {

    height: 72px;

    padding: 0 34px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    position: sticky;

    top: 0;

    z-index: 50;

    background:
        rgba(7, 11, 18, 0.82);

    border-bottom:
        1px solid rgba(255,255,255,0.07);

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);

    animation:
        topbarEnter 0.7s ease both;
}


@keyframes topbarEnter {

    from {
        opacity: 0;
        transform: translateY(-15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


.logo-wrap {

    display: flex;

    align-items: center;

    gap: 12px;
}


.logo-mark {

    width: 38px;
    height: 38px;

    border-radius: 11px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 15px;

    font-weight: 900;

    color: white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    box-shadow:
        0 0 25px rgba(37,99,235,0.25);

    animation:
        logoPulse 4s ease-in-out infinite;
}


@keyframes logoPulse {

    0%,
    100% {
        box-shadow:
            0 0 18px rgba(37,99,235,0.18);
    }

    50% {
        box-shadow:
            0 0 32px rgba(124,58,237,0.32);
    }
}


.logo {

    font-size: 19px;

    font-weight: 800;

    letter-spacing: -0.4px;
}


.logo-sub {

    color: #667085;

    font-size: 12px;

    margin-left: 2px;
}


.back {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    color: #aeb9c9;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    padding: 10px 14px;

    border-radius: 9px;

    border:
        1px solid rgba(255,255,255,0.07);

    background:
        rgba(255,255,255,0.025);

    transition:
        0.25s ease;
}


.back:hover {

    color: white;

    background:
        rgba(255,255,255,0.07);

    border-color:
        rgba(255,255,255,0.12);

    transform: translateX(-3px);
}


/* =========================================================
   MAIN CONTAINER
   ========================================================= */

.container {

    width: min(1180px, calc(100% - 40px));

    margin: 0 auto;

    padding: 48px 0 70px;

    animation:
        pageEnter 0.8s ease both;
}


@keyframes pageEnter {

    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {

    margin-bottom: 32px;
}


.eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    color: #7ea6ff;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1.5px;

    text-transform: uppercase;

    margin-bottom: 12px;
}


.eyebrow-dot {

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #3b82f6;

    box-shadow:
        0 0 12px #3b82f6;

    animation:
        dotPulse 2s infinite;
}


@keyframes dotPulse {

    0%,
    100% {
        opacity: 0.55;
        transform: scale(0.8);
    }

    50% {
        opacity: 1;
        transform: scale(1.15);
    }
}


h1 {

    margin: 0;

    font-size: clamp(30px, 4vw, 42px);

    line-height: 1.05;

    letter-spacing: -1.5px;

    color: #f7faff;
}


.subtitle {

    margin-top: 12px;

    color: #7d899b;

    font-size: 14px;

    line-height: 1.6;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {

    padding: 15px 17px;

    border-radius: 12px;

    margin-bottom: 22px;

    font-size: 13px;

    animation:
        alertEnter 0.5s ease both;
}


@keyframes alertEnter {

    from {
        opacity: 0;
        transform: translateY(-8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


.success {

    color: #7ff0b0;

    background:
        rgba(16,185,129,0.08);

    border:
        1px solid rgba(16,185,129,0.18);
}


.error {

    color: #ff9b9b;

    background:
        rgba(239,68,68,0.08);

    border:
        1px solid rgba(239,68,68,0.18);
}


/* =========================================================
   PANELS
   ========================================================= */

.panel {

    position: relative;

    background:
        linear-gradient(
            145deg,
            rgba(18,25,38,0.92),
            rgba(10,15,24,0.92)
        );

    border:
        1px solid rgba(255,255,255,0.07);

    border-radius: 18px;

    padding: 28px;

    margin-bottom: 22px;

    box-shadow:
        0 20px 60px rgba(0,0,0,0.22);

    overflow: hidden;

    animation:
        panelEnter 0.7s ease both;
}


.panel:nth-of-type(2) {
    animation-delay: 0.08s;
}


@keyframes panelEnter {

    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


.panel::before {

    content: "";

    position: absolute;

    top: 0;
    left: 0;

    width: 100%;
    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(59,130,246,0.5),
            transparent
        );

    opacity: 0.8;
}


/* =========================================================
   PANEL HEADER
   ========================================================= */

.panel-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 24px;
}


.panel-title {

    margin: 0;

    font-size: 17px;

    color: #f1f5f9;

    letter-spacing: -0.3px;
}


.panel-description {

    margin: 5px 0 0;

    font-size: 12px;

    color: #667085;
}


/* =========================================================
   FORM
   ========================================================= */

label {

    display: block;

    margin-bottom: 9px;

    color: #b8c2d1;

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.7px;
}


.select-wrap {

    position: relative;
}


select {

    width: 100%;

    appearance: none;

    -webkit-appearance: none;

    padding: 14px 45px 14px 15px;

    border:
        1px solid rgba(255,255,255,0.08);

    border-radius: 10px;

    font-size: 14px;

    color: #e8eef7;

    background:
        rgba(255,255,255,0.035);

    outline: none;

    cursor: pointer;

    transition:
        border-color 0.25s ease,
        background 0.25s ease,
        box-shadow 0.25s ease;
}


select:hover {

    background:
        rgba(255,255,255,0.055);
}


select:focus {

    border-color:
        rgba(59,130,246,0.65);

    box-shadow:
        0 0 0 3px rgba(59,130,246,0.09);
}


select option {

    background: #101722;

    color: white;
}


.select-arrow {

    position: absolute;

    right: 16px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #718096;

    pointer-events: none;

    font-size: 12px;
}


/* =========================================================
   ASSIGN BUTTON
   ========================================================= */

button {

    margin-top: 18px;

    border: none;

    color: white;

    padding: 13px 21px;

    border-radius: 10px;

    font-weight: 750;

    font-size: 13px;

    cursor: pointer;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 8px 24px rgba(37,99,235,0.18);

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        filter 0.25s ease;

    position: relative;

    overflow: hidden;
}


button::before {

    content: "";

    position: absolute;

    top: 0;
    left: -120%;

    width: 80%;
    height: 100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,0.18),
            transparent
        );

    transform: skewX(-20deg);

    transition:
        left 0.6s ease;
}


button:hover::before {

    left: 140%;
}


button:hover {

    transform: translateY(-2px);

    box-shadow:
        0 12px 30px rgba(37,99,235,0.28);

    filter: brightness(1.08);
}


button:active {

    transform: translateY(0);
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {

    overflow-x: auto;

    margin: 0 -5px;
}


table {

    width: 100%;

    border-collapse: separate;

    border-spacing: 0;

    min-width: 700px;
}


th {

    padding: 13px 15px;

    text-align: left;

    color: #657185;

    background:
        rgba(255,255,255,0.025);

    border-bottom:
        1px solid rgba(255,255,255,0.06);

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 0.9px;

    font-weight: 800;
}


th:first-child {

    border-radius: 9px 0 0 0;
}


th:last-child {

    border-radius: 0 9px 0 0;
}


td {

    padding: 16px 15px;

    color: #c4cedc;

    border-bottom:
        1px solid rgba(255,255,255,0.055);

    font-size: 13px;

    transition:
        background 0.2s ease;
}


tbody tr {

    transition:
        transform 0.2s ease;
}


tbody tr:hover {

    transform:
        translateX(3px);
}


tbody tr:hover td {

    background:
        rgba(59,130,246,0.035);
}


tbody tr:last-child td {

    border-bottom: none;
}


/* =========================================================
   TEST NAME
   ========================================================= */

.test-name {

    color: #f1f5f9;

    font-weight: 650;
}


/* =========================================================
   BADGES
   ========================================================= */

.badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 6px 10px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: 0.5px;
}


.badge::before {

    content: "";

    width: 5px;
    height: 5px;

    border-radius: 50%;

    background: currentColor;
}


.draft {

    color: #9aa5b5;

    background:
        rgba(148,163,184,0.09);
}


.waiting {

    color: #f5c86b;

    background:
        rgba(245,158,11,0.09);
}


.active {

    color: #6ee7a0;

    background:
        rgba(16,185,129,0.09);
}


.completed {

    color: #7ea6ff;

    background:
        rgba(59,130,246,0.09);
}


/* =========================================================
   COUNT
   ========================================================= */

.student-count {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 32px;

    height: 28px;

    padding: 0 9px;

    border-radius: 7px;

    color: #d9e5ff;

    background:
        rgba(59,130,246,0.09);

    border:
        1px solid rgba(59,130,246,0.12);

    font-weight: 750;
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.empty {

    text-align: center;

    padding: 45px 20px;

    color: #697588;
}


.empty-icon {

    width: 46px;
    height: 46px;

    margin: 0 auto 14px;

    border-radius: 13px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        rgba(255,255,255,0.035);

    border:
        1px solid rgba(255,255,255,0.07);

    font-size: 20px;
}


.empty-title {

    color: #b8c2d1;

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 5px;
}


.empty-text {

    font-size: 12px;

    color: #647084;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 700px) {

    .topbar {

        padding: 0 18px;

        height: 65px;
    }


    .logo-sub {

        display: none;
    }


    .container {

        width: min(
            calc(100% - 24px),
            1180px
        );

        padding-top: 32px;
    }


    .panel {

        padding: 20px;

        border-radius: 15px;
    }


    h1 {

        font-size: 32px;
    }


    .back {

        padding: 8px 10px;

        font-size: 12px;
    }


    .logo {

        font-size: 17px;
    }

}


/* =========================================================
   REDUCED MOTION
   ========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation-duration: 0.01ms !important;

        animation-iteration-count: 1 !important;

        transition-duration: 0.01ms !important;
    }

}

</style>

</head>


<body>


<!-- =======================================================
     TOP BAR
     ======================================================= -->

<div class="topbar">

    <div class="logo-wrap">

        <div class="logo-mark">
            M
        </div>

        <div>

            <div class="logo">
                MODUS CBT
            </div>

            <div class="logo-sub">
                Examination System
            </div>

        </div>

    </div>


    <a
        href="dashboard.php"
        class="back"
    >
        ← Dashboard
    </a>

</div>



<!-- =======================================================
     MAIN
     ======================================================= -->

<div class="container">


    <div class="page-header">

        <div class="eyebrow">

            <span class="eyebrow-dot"></span>

            EXAM MANAGEMENT

        </div>


        <h1>
            Assign Students
        </h1>


        <div class="subtitle">

            Assign all active students to an examination
            and prepare the test for deployment.

        </div>

    </div>



    <!-- =====================================================
         ALERTS
         ===================================================== -->

    <?php if ($message): ?>

        <div class="alert success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         ASSIGN PANEL
         ===================================================== -->

    <div class="panel">

        <div class="panel-header">

            <div>

                <h2 class="panel-title">
                    Assign Examination
                </h2>

                <p class="panel-description">
                    Select an examination and assign it to
                    every active student.
                </p>

            </div>

        </div>


        <form method="POST">

            <label for="test_id">
                Examination
            </label>


            <div class="select-wrap">

                <select
                    name="test_id"
                    id="test_id"
                    required
                >

                    <option value="">
                        Select an examination...
                    </option>


                    <?php foreach ($tests as $test): ?>

                        <option
                            value="<?= (int)$test['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $test['title']
                            ) ?>

                            —

                            <?= htmlspecialchars(
                                strtoupper(
                                    $test['status']
                                )
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <span class="select-arrow">
                    ▼
                </span>

            </div>


            <button type="submit">

                Assign All Active Students

            </button>

        </form>

    </div>



    <!-- =====================================================
         OVERVIEW
         ===================================================== -->

    <div class="panel">

        <div class="panel-header">

            <div>

                <h2 class="panel-title">
                    Assignment Overview
                </h2>

                <p class="panel-description">
                    Current student assignment status across
                    all examinations.
                </p>

            </div>

        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            Examination
                        </th>

                        <th>
                            Duration
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Assigned Students
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if ($tests): ?>


                    <?php foreach ($tests as $test): ?>

                        <tr>


                            <td>

                                <div class="test-name">

                                    <?= htmlspecialchars(
                                        $test['title']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <?= (int)
                                    $test['duration_minutes']
                                ?>

                                min

                            </td>


                            <td>

                                <span
                                    class="badge <?= htmlspecialchars(
                                        $test['status']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        strtoupper(
                                            $test['status']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span class="student-count">

                                    <?= $assignment_counts[
                                        $test['id']
                                    ] ?? 0 ?>

                                </span>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td colspan="4">

                            <div class="empty">

                                <div class="empty-icon">
                                    +
                                </div>

                                <div class="empty-title">
                                    No examinations found
                                </div>

                                <div class="empty-text">
                                    Create an examination first
                                    before assigning students.
                                </div>

                            </div>

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


</body>

</html>