<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Handle actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $test_id = isset($_POST['test_id']) ? (int) $_POST['test_id'] : 0;
    $action  = $_POST['action'] ?? '';

    if ($test_id <= 0) {

        $error = "Please select a test.";

    } else {

        try {

            /*
            |------------------------------------------------------------------
            | Prepare Exam
            | draft → waiting
            |------------------------------------------------------------------
            */
            if ($action === 'prepare') {

                $stmt = $pdo->prepare("
                    UPDATE tests
                    SET status = 'waiting'
                    WHERE id = ?
                      AND status = 'draft'
                ");

                $stmt->execute([$test_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Exam prepared successfully. Students can now enter the waiting room.";
                } else {
                    $error = "This test cannot be prepared. Make sure it is currently in draft status.";
                }
            }

            /*
            |------------------------------------------------------------------
            | Start Exam
            | waiting → active
            |------------------------------------------------------------------
            */
            elseif ($action === 'start') {

                $stmt = $pdo->prepare("
                    UPDATE tests
                    SET
                        status = 'active',
                        start_time = NOW()
                    WHERE id = ?
                      AND status = 'waiting'
                ");

                $stmt->execute([$test_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Exam started successfully. Students will enter the CBT automatically.";
                } else {
                    $error = "This test cannot be started. Prepare the exam first.";
                }
            }

            /*
            |------------------------------------------------------------------
            | End Exam
            | active → completed
            |------------------------------------------------------------------
            */
            elseif ($action === 'end') {

                $stmt = $pdo->prepare("
                    UPDATE tests
                    SET
                        status = 'completed',
                        end_time = NOW()
                    WHERE id = ?
                      AND status = 'active'
                ");

                $stmt->execute([$test_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Exam ended successfully.";
                } else {
                    $error = "This test is not currently active.";
                }
            }

        } catch (PDOException $e) {

            $error = "Database error: " . $e->getMessage();
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
        negative_marks,
        start_time,
        end_time,
        status
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Selected test
|--------------------------------------------------------------------------
*/

$selected_test_id = isset($_GET['test_id'])
    ? (int) $_GET['test_id']
    : (isset($_POST['test_id']) ? (int) $_POST['test_id'] : 0);

$selected_test = null;

if ($selected_test_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            duration_minutes,
            total_marks,
            negative_marks,
            start_time,
            end_time,
            status
        FROM tests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$selected_test_id]);

    $selected_test = $stmt->fetch();
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

<title>Exam Control — MODUS CBT</title>


<style>

/* =========================================================
   MODUS CINEMATIC EXAM CONTROL
   ========================================================= */

:root {

    --bg: #05070b;

    --panel: rgba(14,18,28,.84);

    --panel-strong: rgba(18,23,35,.95);

    --border: rgba(255,255,255,.085);

    --text: #f4f7fb;

    --muted: #8d98aa;

    --muted-2: #667185;

    --blue: #5b8cff;

    --purple: #8b5cf6;

    --green: #45d483;

    --yellow: #f5b942;

    --red: #ff7187;

}


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
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    color: var(--text);

    background:

        radial-gradient(
            circle at 10% 5%,
            rgba(91,140,255,.13),
            transparent 28%
        ),

        radial-gradient(
            circle at 90% 15%,
            rgba(139,92,246,.12),
            transparent 28%
        ),

        radial-gradient(
            circle at 50% 100%,
            rgba(40,100,255,.07),
            transparent 38%
        ),

        var(--bg);

    overflow-x: hidden;
}


/* =========================================================
   AMBIENT LIGHT
   ========================================================= */

body::before,
body::after {

    content: "";

    position: fixed;

    width: 430px;
    height: 430px;

    border-radius: 50%;

    filter: blur(115px);

    opacity: .15;

    pointer-events: none;

    z-index: -1;

    animation:
        ambientFloat
        13s
        ease-in-out
        infinite
        alternate;
}


body::before {

    top: -190px;
    left: -140px;

    background: #356cff;
}


body::after {

    right: -170px;
    bottom: -190px;

    background: #8b5cf6;

    animation-delay: -5s;
}


@keyframes ambientFloat {

    from {
        transform: translate3d(0,0,0) scale(1);
    }

    to {
        transform: translate3d(30px,25px,0) scale(1.08);
    }

}


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {

    height: 72px;

    position: sticky;

    top: 0;

    z-index: 100;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 32px;

    background:
        rgba(5,7,11,.78);

    border-bottom:
        1px solid var(--border);

    backdrop-filter:
        blur(22px);

    -webkit-backdrop-filter:
        blur(22px);
}


.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    font-weight: 800;

    letter-spacing: .4px;
}


.brand-mark {

    width: 35px;
    height: 35px;

    display: grid;

    place-items: center;

    border-radius: 10px;

    color: white;

    font-size: 15px;

    font-weight: 900;

    background:
        linear-gradient(
            135deg,
            var(--blue),
            var(--purple)
        );

    box-shadow:
        0 0 30px rgba(91,140,255,.24);
}


.brand-name {

    font-size: 16px;
}


.brand-name span {

    color: #8d98aa;

    font-weight: 500;

    margin-left: 4px;
}


.back {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 9px 13px;

    border-radius: 9px;

    border:
        1px solid rgba(255,255,255,.08);

    background:
        rgba(255,255,255,.025);

    color: #c7cfdd;

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

    transition: .2s ease;
}


.back:hover {

    color: white;

    background:
        rgba(255,255,255,.06);

    border-color:
        rgba(255,255,255,.14);

    transform:
        translateY(-1px);
}


/* =========================================================
   MAIN CONTAINER
   ========================================================= */

.container {

    width:
        min(100%, 1120px);

    margin:
        0 auto;

    padding:
        45px 24px 80px;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {

    margin-bottom: 28px;

    animation:
        fadeUp
        .55s
        ease
        both;
}


.eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 12px;

    color: #82a6ff;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1.8px;

    text-transform: uppercase;
}


.eyebrow-dot {

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--blue);

    box-shadow:
        0 0 0 4px rgba(91,140,255,.08),
        0 0 15px rgba(91,140,255,.7);

    animation:
        pulse
        2s
        ease-in-out
        infinite;
}


@keyframes pulse {

    0%,
    100% {
        opacity: .55;
        transform: scale(.9);
    }

    50% {
        opacity: 1;
        transform: scale(1.1);
    }

}


.page-header h1 {

    margin: 0;

    font-size:
        clamp(30px,5vw,44px);

    line-height: 1.05;

    letter-spacing: -1.8px;

    font-weight: 800;
}


.subtitle {

    margin:
        13px 0 0;

    color: var(--muted);

    font-size: 14px;

    line-height: 1.7;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {

    margin-bottom: 20px;

    padding: 13px 15px;

    border-radius: 11px;

    font-size: 13px;

    line-height: 1.5;

    animation:
        fadeUp
        .4s
        ease
        both;
}


.success {

    color: #7de3a7;

    background:
        rgba(69,212,131,.07);

    border:
        1px solid rgba(69,212,131,.17);
}


.error {

    color: #ff9baa;

    background:
        rgba(255,113,135,.07);

    border:
        1px solid rgba(255,113,135,.17);
}


/* =========================================================
   PANEL
   ========================================================= */

.panel {

    position: relative;

    margin-bottom: 20px;

    padding: 27px;

    background:
        linear-gradient(
            145deg,
            rgba(20,25,38,.88),
            rgba(9,12,19,.94)
        );

    border:
        1px solid var(--border);

    border-radius: 20px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.28),
        inset 0 1px 0 rgba(255,255,255,.025);

    overflow: hidden;

    animation:
        fadeUp
        .6s
        .07s
        ease
        both;
}


.panel::before {

    content: "";

    position: absolute;

    top: 0;
    left: 10%;

    width: 80%;
    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(91,140,255,.6),
            rgba(139,92,246,.6),
            transparent
        );
}


.panel::after {

    content: "";

    position: absolute;

    width: 280px;
    height: 280px;

    right: -160px;
    top: -160px;

    border-radius: 50%;

    background:
        rgba(91,140,255,.065);

    filter: blur(70px);

    pointer-events: none;
}


/* =========================================================
   SELECT TEST
   ========================================================= */

.panel-heading {

    position: relative;

    z-index: 2;

    margin-bottom: 18px;
}


.panel-heading h2 {

    margin: 0;

    font-size: 18px;

    letter-spacing: -.3px;
}


.panel-heading p {

    margin:
        7px 0 0;

    color: var(--muted);

    font-size: 12px;
}


label {

    display: block;

    position: relative;

    z-index: 2;

    margin-bottom: 9px;

    color: #dce3ee;

    font-size: 12px;

    font-weight: 750;

    letter-spacing: .2px;
}


select {

    position: relative;

    z-index: 2;

    width: 100%;

    height: 50px;

    padding:
        0 15px;

    border:
        1px solid rgba(255,255,255,.09);

    border-radius: 11px;

    outline: none;

    background:
        rgba(4,7,12,.78);

    color: #edf2f8;

    font-family: inherit;

    font-size: 14px;

    cursor: pointer;

    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}


select:hover {

    border-color:
        rgba(255,255,255,.15);
}


select:focus {

    border-color:
        rgba(91,140,255,.7);

    box-shadow:
        0 0 0 3px rgba(91,140,255,.08);
}


select option {

    background: #111722;

    color: white;
}


/* =========================================================
   TEST HEADER
   ========================================================= */

.test-header {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 23px;
}


.test-title {

    margin: 0;

    font-size: 21px;

    letter-spacing: -.5px;

    font-weight: 800;
}


.test-description {

    margin:
        7px 0 0;

    color: var(--muted);

    font-size: 12px;
}


/* =========================================================
   STATUS
   ========================================================= */

.status {

    flex-shrink: 0;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        7px 11px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 850;

    letter-spacing: .8px;

    text-transform: uppercase;
}


.status::before {

    content: "";

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: currentColor;
}


.status-draft {

    color: #a8b0bd;

    background:
        rgba(156,163,175,.08);

    border:
        1px solid rgba(156,163,175,.13);
}


.status-waiting {

    color: #f4c765;

    background:
        rgba(245,185,66,.08);

    border:
        1px solid rgba(245,185,66,.15);
}


.status-active {

    color: #6fe39a;

    background:
        rgba(69,212,131,.08);

    border:
        1px solid rgba(69,212,131,.16);

    animation:
        activeGlow
        2s
        ease-in-out
        infinite;
}


.status-completed {

    color: #8eb2ff;

    background:
        rgba(91,140,255,.08);

    border:
        1px solid rgba(91,140,255,.15);
}


@keyframes activeGlow {

    0%,
    100% {
        box-shadow: 0 0 0 rgba(69,212,131,0);
    }

    50% {
        box-shadow:
            0 0 20px rgba(69,212,131,.08);
    }

}


/* =========================================================
   TEST INFO
   ========================================================= */

.test-info {

    position: relative;

    z-index: 2;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;
}


.info-box {

    padding:
        17px;

    border-radius: 13px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid rgba(255,255,255,.055);

    transition:
        border-color .2s ease,
        transform .2s ease;
}


.info-box:hover {

    border-color:
        rgba(255,255,255,.1);

    transform:
        translateY(-2px);
}


.info-label {

    margin-bottom: 7px;

    color: #697487;

    font-size: 10px;

    font-weight: 750;

    letter-spacing: .7px;

    text-transform: uppercase;
}


.info-value {

    color: #e8edf5;

    font-size: 17px;

    font-weight: 800;

    line-height: 1.3;

    word-break: break-word;
}


/* =========================================================
   ACTION AREA
   ========================================================= */

.actions {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    gap: 10px;

    flex-wrap: wrap;

    margin-top: 26px;

    padding-top: 23px;

    border-top:
        1px solid rgba(255,255,255,.06);
}


.actions form {

    margin: 0;
}


button {

    position: relative;

    min-height: 45px;

    padding:
        0 17px;

    border:
        1px solid transparent;

    border-radius: 10px;

    font-family: inherit;

    font-size: 12px;

    font-weight: 800;

    cursor: pointer;

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        opacity .2s ease;
}


button:not(:disabled):hover {

    transform:
        translateY(-2px);
}


.prepare {

    color: #ffd87d;

    background:
        rgba(245,185,66,.09);

    border-color:
        rgba(245,185,66,.18);
}


.prepare:hover {

    box-shadow:
        0 10px 28px rgba(245,185,66,.1);
}


.start {

    color: white;

    background:
        linear-gradient(
            135deg,
            #35b96e,
            #159957
        );

    box-shadow:
        0 10px 25px rgba(69,212,131,.14);
}


.start:hover {

    box-shadow:
        0 14px 32px rgba(69,212,131,.22);
}


.end {

    color: white;

    background:
        linear-gradient(
            135deg,
            #e65369,
            #c93750
        );

    box-shadow:
        0 10px 25px rgba(255,113,135,.12);
}


.end:hover {

    box-shadow:
        0 14px 32px rgba(255,113,135,.2);
}


button:disabled {

    opacity: .35;

    cursor: not-allowed;

    transform: none !important;

    box-shadow: none !important;
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.empty {

    min-height: 220px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-direction: column;

    gap: 10px;

    text-align: center;

    color: var(--muted);

    font-size: 13px;
}


.empty-icon {

    width: 48px;
    height: 48px;

    display: grid;

    place-items: center;

    border-radius: 14px;

    color: #7ea4ff;

    background:
        rgba(91,140,255,.07);

    border:
        1px solid rgba(91,140,255,.13);

    font-size: 20px;
}


/* =========================================================
   ANIMATION
   ========================================================= */

@keyframes fadeUp {

    from {

        opacity: 0;

        transform:
            translateY(16px);

    }

    to {

        opacity: 1;

        transform:
            translateY(0);

    }

}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 800px) {

    .test-info {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 650px) {

    .topbar {

        height: auto;

        min-height: 68px;

        padding:
            12px 16px;
    }


    .container {

        padding:
            32px 15px 60px;
    }


    .panel {

        padding:
            21px 18px;

        border-radius:
            17px;
    }


    .test-header {

        flex-direction:
            column;

        gap: 13px;
    }


    .test-info {

        grid-template-columns:
            1fr 1fr;
    }


    .actions {

        flex-direction:
            column;

        align-items:
            stretch;
    }


    .actions form {

        width: 100%;
    }


    button {

        width: 100%;
    }

}


@media (max-width: 430px) {

    .brand-name {

        display: none;
    }


    .test-info {

        grid-template-columns:
            1fr;
    }


    .page-header h1 {

        font-size:
            32px;
    }

}


@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation-duration:
            .01ms !important;

        animation-iteration-count:
            1 !important;

        transition-duration:
            .01ms !important;

        scroll-behavior:
            auto !important;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     TOPBAR
     ========================================================= -->

<div class="topbar">

    <div class="brand">

        <div class="brand-mark">
            M
        </div>

        <div class="brand-name">
            MODUS <span>CBT</span>
        </div>

    </div>


    <a
        href="dashboard.php"
        class="back"
    >
        ← Dashboard
    </a>

</div>



<!-- =========================================================
     MAIN
     ========================================================= -->

<div class="container">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="eyebrow">

            <span class="eyebrow-dot"></span>

            EXAM MANAGEMENT

        </div>


        <h1>
            Exam Control
        </h1>


        <div class="subtitle">
            Prepare, start and end your examination from one control center.
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
         TEST SELECTOR
         ===================================================== -->

    <div class="panel">

        <div class="panel-heading">

            <h2>
                Select Examination
            </h2>

            <p>
                Choose the test you want to control.
            </p>

        </div>


        <form method="GET">

            <label for="test_id">
                Examination
            </label>


            <select
                name="test_id"
                id="test_id"
                onchange="this.form.submit()"
            >

                <option value="">
                    -- Select a test --
                </option>


                <?php foreach ($tests as $test): ?>

                    <option
                        value="<?= (int)$test['id'] ?>"
                        <?= $selected_test_id == $test['id']
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars($test['title']) ?>

                        —

                        <?= htmlspecialchars(
                            strtoupper($test['status'])
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </div>



    <!-- =====================================================
         SELECTED TEST
         ===================================================== -->

    <?php if ($selected_test): ?>


        <div class="panel">


            <div class="test-header">

                <div>

                    <h2 class="test-title">

                        <?= htmlspecialchars(
                            $selected_test['title']
                        ) ?>

                    </h2>


                    <p class="test-description">
                        Current examination configuration and controls.
                    </p>

                </div>


                <?php

                $status =
                    $selected_test['status'];

                ?>


                <span
                    class="status status-<?= htmlspecialchars($status) ?>"
                >

                    <?= htmlspecialchars($status) ?>

                </span>

            </div>



            <!-- TEST INFORMATION -->

            <div class="test-info">


                <div class="info-box">

                    <div class="info-label">
                        Duration
                    </div>

                    <div class="info-value">

                        <?= (int)$selected_test['duration_minutes'] ?>

                        min

                    </div>

                </div>



                <div class="info-box">

                    <div class="info-label">
                        Total Marks
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $selected_test['total_marks']
                        ) ?>

                    </div>

                </div>



                <div class="info-box">

                    <div class="info-label">
                        Negative Marks
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $selected_test['negative_marks']
                        ) ?>

                    </div>

                </div>



                <div class="info-box">

                    <div class="info-label">
                        Start Time
                    </div>

                    <div class="info-value">

                        <?php if (
                            $selected_test['start_time']
                        ): ?>

                            <?= htmlspecialchars(
                                $selected_test['start_time']
                            ) ?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </div>

                </div>


            </div>



            <!-- =================================================
                 ACTIONS
                 ================================================= -->

            <div class="actions">


                <!-- PREPARE -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="test_id"
                        value="<?= (int)$selected_test['id'] ?>"
                    >


                    <input
                        type="hidden"
                        name="action"
                        value="prepare"
                    >


                    <button
                        type="submit"
                        class="prepare"
                        <?= $status !== 'draft'
                            ? 'disabled'
                            : ''
                        ?>
                    >

                        Prepare Exam

                    </button>

                </form>



                <!-- START -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="test_id"
                        value="<?= (int)$selected_test['id'] ?>"
                    >


                    <input
                        type="hidden"
                        name="action"
                        value="start"
                    >


                    <button
                        type="submit"
                        class="start"
                        <?= $status !== 'waiting'
                            ? 'disabled'
                            : ''
                        ?>
                    >

                        Start Exam

                    </button>

                </form>



                <!-- END -->

                <form
                    method="POST"
                    onsubmit="return confirm(
                        'Are you sure you want to end this exam?'
                    );"
                >

                    <input
                        type="hidden"
                        name="test_id"
                        value="<?= (int)$selected_test['id'] ?>"
                    >


                    <input
                        type="hidden"
                        name="action"
                        value="end"
                    >


                    <button
                        type="submit"
                        class="end"
                        <?= $status !== 'active'
                            ? 'disabled'
                            : ''
                        ?>
                    >

                        End Exam

                    </button>

                </form>


            </div>


        </div>


    <?php else: ?>


        <!-- =================================================
             EMPTY STATE
             ================================================= -->

        <div class="panel empty">

            <div class="empty-icon">
                ◈
            </div>

            <strong>
                No examination selected
            </strong>

            <span>
                Select a test above to access its controls.
            </span>

        </div>


    <?php endif; ?>


</div>


</body>

</html>