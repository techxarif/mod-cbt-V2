<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];
$studentUid = $_SESSION['student_uid'];


/*
|--------------------------------------------------------------------------
| Find Current Exam
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
        status
    FROM tests
    WHERE status IN ('waiting', 'active')
    ORDER BY id DESC
    LIMIT 1
");

$test = $stmt->fetch();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Examination Waiting Room - MODUS CBT
</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;

    width: 100%;
    min-height: 100%;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f2f2f2;

    color: #222;

    overflow-x: hidden;
}

button,
a {
    -webkit-tap-highlight-color: transparent;
}


/* =========================================================
   NTA-STYLE TOP HEADER
========================================================= */

.top-header {

    height: 64px;

    background: #063b68;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 22px;

    box-shadow:
        0 1px 4px rgba(0,0,0,.25);

    position: relative;

    z-index: 20;
}

.logo-area {

    display: flex;

    align-items: center;

    gap: 12px;

    min-width: 0;
}

.logo-box {

    width: 42px;
    height: 42px;

    min-width: 42px;

    background: #ffffff;

    color: #063b68;

    border-radius: 4px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;

    font-weight: 800;
}

.logo-text {

    display: flex;

    flex-direction: column;

    min-width: 0;
}

.logo-title {

    font-size: 21px;

    font-weight: 800;

    white-space: nowrap;
}

.logo-subtitle {

    font-size: 11px;

    opacity: .85;

    white-space: nowrap;
}

.candidate-area {

    display: flex;

    align-items: center;

    gap: 12px;

    min-width: 0;
}

.candidate-photo {

    width: 36px;
    height: 36px;

    border-radius: 3px;

    background: #fff;

    color: #063b68;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;

    flex-shrink: 0;
}

.candidate-info {

    text-align: right;

    min-width: 0;
}

.candidate-name {

    font-size: 14px;

    font-weight: 700;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.candidate-uid {

    font-size: 11px;

    opacity: .8;

    white-space: nowrap;
}

.logout {

    color: white;

    text-decoration: none;

    font-size: 13px;

    padding-left: 12px;

    border-left: 1px solid rgba(255,255,255,.3);

    white-space: nowrap;
}

.logout:hover {

    text-decoration: underline;
}


/* =========================================================
   ORANGE SYSTEM BAR
========================================================= */

.system-bar {

    height: 34px;

    background: #f28c00;

    color: white;

    display: flex;

    align-items: center;

    padding: 0 22px;

    font-size: 13px;

    font-weight: 700;

    border-bottom: 1px solid #d67600;
}


/* =========================================================
   MAIN AREA
========================================================= */

.page {

    min-height:
        calc(100vh - 98px);

    padding:
        24px 25px 40px;
}


/* =========================================================
   PAGE TITLE
========================================================= */

.page-title {

    max-width: 1250px;

    margin:
        0 auto 15px;

    background: #fff;

    border: 1px solid #d4d8dc;

    padding: 15px 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.page-title h1 {

    margin: 0;

    font-size: 21px;

    color: #333;
}

.page-title p {

    margin: 4px 0 0;

    font-size: 12px;

    color: #777;
}

.system-status {

    background: #fff3cd;

    color: #856404;

    border: 1px solid #ffe69c;

    padding: 8px 13px;

    border-radius: 3px;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   MAIN EXAM CARD
========================================================= */

.exam-card {

    max-width: 1250px;

    margin: 0 auto;

    background: white;

    border: 1px solid #cfd4d9;

    box-shadow:
        0 2px 7px rgba(0,0,0,.08);
}


/* =========================================================
   CARD HEADER
========================================================= */

.exam-card-header {

    background: #f7f7f7;

    border-bottom: 1px solid #d5d9dd;

    padding: 15px 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.exam-card-title {

    font-size: 18px;

    font-weight: 700;

    color: #333;
}

.exam-card-code {

    font-size: 12px;

    color: #777;
}


/* =========================================================
   EXAM CONTENT
========================================================= */

.exam-content {

    padding: 22px;
}


/* =========================================================
   WAITING STATUS
========================================================= */

.waiting-panel {

    border: 1px solid #cbd5df;

    background: #f8fafc;

    display: flex;

    align-items: center;

    gap: 18px;

    padding: 20px;

    margin-bottom: 20px;
}

.waiting-icon {

    width: 58px;
    height: 58px;

    min-width: 58px;

    background: #e7f1fb;

    border: 1px solid #a9c9e7;

    color: #0d5c99;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 27px;
}

.waiting-text {

    min-width: 0;
}

.waiting-text h2 {

    margin: 0 0 5px;

    color: #164d75;

    font-size: 20px;
}

.waiting-text p {

    margin: 0;

    color: #555;

    font-size: 14px;

    line-height: 1.5;
}


/* =========================================================
   EXAM INFORMATION GRID
========================================================= */

.section-title {

    font-size: 16px;

    font-weight: 700;

    color: #333;

    margin:
        0 0 10px;
}

.exam-information {

    border: 1px solid #d5d9dd;

    margin-bottom: 20px;
}

.exam-name-row {

    padding: 15px 17px;

    background: #063b68;

    color: white;
}

.exam-name-label {

    font-size: 11px;

    opacity: .8;

    margin-bottom: 4px;
}

.exam-name {

    font-size: 19px;

    font-weight: 700;

    overflow-wrap: anywhere;
}

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);
}

.info-box {

    padding: 15px;

    border-right: 1px solid #ddd;

    min-width: 0;
}

.info-box:last-child {

    border-right: none;
}

.info-label {

    font-size: 12px;

    color: #777;

    margin-bottom: 6px;
}

.info-value {

    font-size: 16px;

    font-weight: 700;

    color: #222;

    overflow-wrap: anywhere;
}


/* =========================================================
   INSTRUCTIONS
========================================================= */

.instructions {

    border: 1px solid #d7d7d7;

    margin-bottom: 20px;
}

.instructions-header {

    background: #eeeeee;

    border-bottom: 1px solid #d4d4d4;

    padding: 12px 15px;

    font-size: 15px;

    font-weight: 700;

    color: #333;
}

.instructions-body {

    padding: 16px 18px;

    font-size: 13px;

    line-height: 1.55;

    color: #444;
}

.instructions-body ol {

    margin:
        0 0 0 20px;

    padding: 0;
}

.instructions-body li {

    margin-bottom: 8px;
}


/* =========================================================
   IMPORTANT NOTICE
========================================================= */

.notice {

    background: #fff8e6;

    border: 1px solid #f1d28a;

    padding: 14px 16px;

    margin-bottom: 20px;

    color: #654f18;

    font-size: 13px;

    line-height: 1.5;
}

.notice strong {

    color: #4f3d0d;
}


/* =========================================================
   BOTTOM STATUS
========================================================= */

.bottom-status {

    border-top: 1px solid #ddd;

    padding-top: 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}

.auto-message {

    color: #666;

    font-size: 13px;

    line-height: 1.5;
}

.auto-message strong {

    color: #333;
}

.loading-indicator {

    display: flex;

    align-items: center;

    gap: 9px;

    color: #0b65a3;

    font-size: 13px;

    font-weight: 700;

    white-space: nowrap;
}

.spinner {

    width: 17px;
    height: 17px;

    border:
        2px solid #c5dcec;

    border-top-color:
        #0b65a3;

    border-radius: 50%;

    animation:
        spin .8s linear infinite;
}

@keyframes spin {

    to {
        transform: rotate(360deg);
    }
}


/* =========================================================
   NO EXAM STATE
========================================================= */

.no-exam {

    text-align: center;

    padding:
        45px 20px;
}

.no-exam-icon {

    width: 65px;
    height: 65px;

    margin:
        0 auto 15px;

    border-radius: 50%;

    background: #f1f3f5;

    border: 1px solid #d5d9dd;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 28px;
}

.no-exam h2 {

    margin:
        0 0 8px;

    font-size: 20px;

    color: #333;
}

.no-exam p {

    margin: 0;

    color: #777;

    font-size: 14px;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    max-width: 1250px;

    margin:
        15px auto 0;

    color: #777;

    font-size: 11px;

    display: flex;

    justify-content: space-between;

    gap: 15px;
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 900px) {

    .info-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .info-box:nth-child(2) {

        border-right: none;
    }

    .info-box:nth-child(-n+2) {

        border-bottom: 1px solid #ddd;
    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 650px) {

    .top-header {

        height: 58px;

        padding:
            7px 10px;
    }

    .logo-box {

        width: 36px;
        height: 36px;

        min-width: 36px;

        font-size: 19px;
    }

    .logo-title {

        font-size: 16px;
    }

    .logo-subtitle {

        display: none;
    }

    .candidate-area {

        gap: 7px;
    }

    .candidate-photo {

        width: 29px;
        height: 29px;
    }

    .candidate-info {

        display: none;
    }

    .logout {

        font-size: 11px;

        padding-left: 8px;
    }

    .system-bar {

        height: 30px;

        padding:
            0 10px;

        font-size: 11px;
    }

    .page {

        padding:
            10px 7px 25px;
    }

    .page-title {

        margin-bottom: 8px;

        padding:
            12px;

        align-items: flex-start;

        flex-direction: column;
    }

    .page-title h1 {

        font-size: 17px;
    }

    .system-status {

        width: 100%;

        text-align: center;
    }

    .exam-card-header {

        padding:
            12px;

        flex-direction: column;

        align-items: flex-start;

        gap: 5px;
    }

    .exam-card-title {

        font-size: 16px;
    }

    .exam-content {

        padding:
            11px;
    }

    .waiting-panel {

        padding:
            14px;

        gap: 12px;

        align-items: flex-start;
    }

    .waiting-icon {

        width: 45px;
        height: 45px;

        min-width: 45px;

        font-size: 21px;
    }

    .waiting-text h2 {

        font-size: 17px;
    }

    .waiting-text p {

        font-size: 12px;
    }

    .exam-name-row {

        padding:
            12px;
    }

    .exam-name {

        font-size: 16px;
    }

    .info-grid {

        grid-template-columns:
            1fr 1fr;
    }

    .info-box {

        padding:
            12px;
    }

    .info-label {

        font-size: 10px;
    }

    .info-value {

        font-size: 14px;
    }

    .instructions-body {

        padding:
            13px;

        font-size: 12px;
    }

    .instructions-body ol {

        margin-left: 18px;
    }

    .notice {

        padding:
            12px;

        font-size: 12px;
    }

    .bottom-status {

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;
    }

    .loading-indicator {

        width: 100%;

        justify-content: center;

        padding:
            10px;

        background: #f4f8fb;

        border: 1px solid #dce8f0;
    }

    .footer {

        padding:
            0 4px;

        flex-direction: column;

        text-align: center;
    }
}


/* =========================================================
   VERY SMALL PHONES
========================================================= */

@media (max-width: 380px) {

    .logo-title {

        font-size: 14px;
    }

    .logout {

        font-size: 10px;
    }

    .page {

        padding:
            7px 5px 20px;
    }

    .exam-content {

        padding:
            8px;
    }

    .info-box {

        padding:
            10px 8px;
    }

    .info-value {

        font-size: 13px;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     TOP HEADER
===================================================== -->

<header class="top-header">

    <div class="logo-area">

        <div class="logo-box">
            M
        </div>

        <div class="logo-text">

            <div class="logo-title">
                MODUS CBT
            </div>

            <div class="logo-subtitle">
                Computer Based Test System
            </div>

        </div>

    </div>


    <div class="candidate-area">

        <div class="candidate-photo">

            <?= strtoupper(
                substr(
                    $studentUid,
                    0,
                    1
                )
            ) ?>

        </div>


        <div class="candidate-info">

            <div class="candidate-name">
                Candidate
            </div>

            <div class="candidate-uid">

                UID:
                <?= htmlspecialchars($studentUid) ?>

            </div>

        </div>


        <a
            href="logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</header>


<!-- =====================================================
     SYSTEM BAR
===================================================== -->

<div class="system-bar">

    EXAMINATION SYSTEM &nbsp; | &nbsp;
    CANDIDATE WAITING AREA

</div>


<!-- =====================================================
     PAGE
===================================================== -->

<main class="page">


    <!-- PAGE TITLE -->

    <div class="page-title">

        <div>

            <h1>
                Examination Waiting Room
            </h1>

            <p>
                Please remain on this page until the examination is started.
            </p>

        </div>


        <div class="system-status">

            ● SYSTEM ACTIVE

        </div>

    </div>


    <!-- =================================================
         EXAM CARD
    ================================================= -->

    <section class="exam-card">


        <div class="exam-card-header">

            <div class="exam-card-title">

                Examination Details

            </div>


            <div class="exam-card-code">

                MODUS CBT

            </div>

        </div>


        <div class="exam-content">


        <?php if (!$test): ?>


            <!-- =========================================
                 NO EXAM
            ========================================== -->

            <div class="no-exam">

                <div class="no-exam-icon">
                    ⏳
                </div>

                <h2>
                    Waiting for Examination
                </h2>

                <p>
                    No active examination is currently available.
                </p>

                <p style="margin-top:8px;">

                    Please keep this page open.
                    The examination will appear automatically
                    when it is scheduled.

                </p>

            </div>


        <?php else: ?>


            <!-- =========================================
                 WAITING STATUS
            ========================================== -->

            <div class="waiting-panel">

                <div class="waiting-icon">
                    📝
                </div>


                <div class="waiting-text">

                    <?php if ($test['status'] === 'waiting'): ?>

                        <h2>
                            Examination is Ready
                        </h2>

                        <p>

                            Your examination has been prepared.
                            Please wait for the invigilator/teacher
                            to start the examination.

                        </p>

                    <?php else: ?>

                        <h2>
                            Examination Starting
                        </h2>

                        <p>

                            The examination has been started.
                            You will be redirected to the
                            examination screen automatically.

                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =========================================
                 EXAM INFORMATION
            ========================================== -->

            <div class="section-title">

                Examination Information

            </div>


            <div class="exam-information">


                <div class="exam-name-row">

                    <div class="exam-name-label">

                        EXAMINATION

                    </div>

                    <div class="exam-name">

                        <?= htmlspecialchars(
                            $test['title']
                        ) ?>

                    </div>

                </div>


                <div class="info-grid">


                    <div class="info-box">

                        <div class="info-label">
                            Duration
                        </div>

                        <div class="info-value">

                            <?= htmlspecialchars(
                                $test['duration_minutes']
                            ) ?>

                            Minutes

                        </div>

                    </div>


                    <div class="info-box">

                        <div class="info-label">
                            Total Marks
                        </div>

                        <div class="info-value">

                            <?= htmlspecialchars(
                                $test['total_marks']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-box">

                        <div class="info-label">
                            Negative Marking
                        </div>

                        <div class="info-value">

                            <?= htmlspecialchars(
                                $test['negative_marks']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-box">

                        <div class="info-label">
                            Examination Status
                        </div>

                        <div class="info-value">

                            <?php if ($test['status'] === 'waiting'): ?>

                                Waiting

                            <?php else: ?>

                                Starting

                            <?php endif; ?>

                        </div>

                    </div>


                </div>

            </div>


            <!-- =========================================
                 INSTRUCTIONS
            ========================================== -->

            <div class="instructions">


                <div class="instructions-header">

                    General Examination Instructions

                </div>


                <div class="instructions-body">

                    <ol>

                        <li>
                            Please ensure that you are seated
                            at your assigned computer before
                            the examination begins.
                        </li>

                        <li>
                            Do not close this browser window
                            while waiting for the examination.
                        </li>

                        <li>
                            Once the examination begins,
                            carefully read the instructions
                            displayed on the examination screen.
                        </li>

                        <li>
                            The examination timer will begin
                            when your examination session starts.
                        </li>

                        <li>
                            You will be able to navigate between
                            questions using the question palette.
                        </li>

                        <li>
                            Make sure that you submit the examination
                            before the allotted time expires.
                        </li>

                    </ol>

                </div>

            </div>


            <!-- =========================================
                 IMPORTANT NOTICE
            ========================================== -->

            <div class="notice">

                <strong>Important:</strong>

                Do not refresh this page repeatedly.
                The system automatically checks the examination
                status and will open the examination screen
                as soon as the teacher starts the test.

            </div>


            <!-- =========================================
                 AUTO STATUS
            ========================================== -->

            <div class="bottom-status">


                <div class="auto-message">

                    <strong>
                        Automatic Examination Detection
                    </strong>

                    <br>

                    This page checks the examination server
                    automatically every few seconds.

                </div>


                <div class="loading-indicator">

                    <span class="spinner"></span>

                    Waiting for examination to start...

                </div>

            </div>


        <?php endif; ?>


        </div>

    </section>


    <!-- FOOTER -->

    <div class="footer">

        <span>
            MODUS CBT Examination System
        </span>

        <span>

            Candidate UID:
            <?= htmlspecialchars($studentUid) ?>

        </span>

    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| Check Exam Status Every 2 Seconds
|--------------------------------------------------------------------------
*/

setInterval(function () {

    fetch('../api/exam_status.php', {
        cache: 'no-store'
    })

    .then(response => response.json())

    .then(data => {

        if (data.status === 'active') {

            window.location.href =
                'exam.php';

        }

    })

    .catch(error => {

        console.log(
            'Exam status check failed'
        );

    });

}, 2000);

</script>


</body>

</html>