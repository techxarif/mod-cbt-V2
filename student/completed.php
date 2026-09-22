<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$studentUid = $_SESSION['student_uid'] ?? '';


$test_id = isset($_GET['test_id'])
    ? (int)$_GET['test_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Load Result Directly From Database
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        st.score,
        st.correct_answers,
        st.wrong_answers,
        st.unanswered,
        st.submitted_at,

        t.title,
        t.total_marks,
        t.duration_minutes

    FROM student_tests st

    INNER JOIN tests t
        ON t.id = st.test_id

    WHERE st.student_id = ?
      AND st.test_id = ?
      AND st.status = 'submitted'

    LIMIT 1
");

$stmt->execute([
    $student_id,
    $test_id
]);

$result = $stmt->fetch();


if (!$result) {
    die("Result not found.");
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

<title>
    Examination Submitted - MODUS CBT
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

    min-height: 100%;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f2f2f2;

    color: #222;

    overflow-x: hidden;
}


/* =========================================================
   TOP HEADER
========================================================= */

.top-header {

    height: 64px;

    background: #063b68;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 22px;

    box-shadow:
        0 1px 4px rgba(0,0,0,.25);
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

    background: white;

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

    gap: 10px;

    min-width: 0;
}

.candidate-photo {

    width: 36px;
    height: 36px;

    min-width: 36px;

    background: white;

    color: #063b68;

    border-radius: 3px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;
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

    border-left:
        1px solid rgba(255,255,255,.3);

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

    padding:
        0 22px;

    font-size: 13px;

    font-weight: 700;

    border-bottom:
        1px solid #d67600;
}


/* =========================================================
   PAGE
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

    max-width: 1050px;

    margin:
        0 auto 15px;

    background: white;

    border:
        1px solid #d4d8dc;

    padding:
        15px 18px;

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

    margin:
        4px 0 0;

    font-size: 12px;

    color: #777;
}

.completed-status {

    background: #e8f5e9;

    color: #1b6e2a;

    border:
        1px solid #b7dfbd;

    padding:
        8px 13px;

    border-radius: 3px;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   RESULT CARD
========================================================= */

.result-card {

    max-width: 1050px;

    margin: 0 auto;

    background: white;

    border:
        1px solid #cfd4d9;

    box-shadow:
        0 2px 7px rgba(0,0,0,.08);
}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {

    background: #f7f7f7;

    border-bottom:
        1px solid #d5d9dd;

    padding:
        15px 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.card-header-title {

    font-size: 18px;

    font-weight: 700;

    color: #333;
}

.card-header-code {

    font-size: 12px;

    color: #777;
}


/* =========================================================
   RESULT CONTENT
========================================================= */

.result-content {

    padding: 22px;
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

.submission-panel {

    background: #f0f8f2;

    border:
        1px solid #b9dbbf;

    padding:
        20px;

    display: flex;

    align-items: center;

    gap: 18px;

    margin-bottom: 22px;
}

.success-icon {

    width: 58px;
    height: 58px;

    min-width: 58px;

    border-radius: 50%;

    background: #1c9b43;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

    font-weight: 800;
}

.submission-text h2 {

    margin:
        0 0 5px;

    color: #17662d;

    font-size: 21px;
}

.submission-text p {

    margin: 0;

    color: #4c6652;

    font-size: 14px;

    line-height: 1.5;
}


/* =========================================================
   EXAM NAME
========================================================= */

.exam-heading {

    border:
        1px solid #d5d9dd;

    margin-bottom: 20px;
}

.exam-heading-top {

    background: #063b68;

    color: white;

    padding:
        13px 17px;

    font-size: 11px;

    font-weight: 700;

    letter-spacing: .3px;
}

.exam-heading-name {

    padding:
        15px 17px;

    font-size: 19px;

    font-weight: 700;

    color: #222;

    overflow-wrap: anywhere;
}


/* =========================================================
   SCORE SECTION
========================================================= */

.score-section {

    border:
        1px solid #d5d9dd;

    margin-bottom: 20px;
}

.section-heading {

    background: #eeeeee;

    border-bottom:
        1px solid #d4d4d4;

    padding:
        12px 15px;

    font-size: 15px;

    font-weight: 700;

    color: #333;
}

.score-body {

    padding: 22px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;
}

.score-value {

    font-size: 46px;

    line-height: 1;

    font-weight: 800;

    color: #063b68;
}

.score-divider {

    margin:
        10px 0;

    color: #999;

    font-size: 13px;
}

.score-total {

    font-size: 17px;

    font-weight: 700;

    color: #555;
}

.score-label {

    margin-top: 8px;

    font-size: 12px;

    color: #777;
}


/* =========================================================
   PERFORMANCE GRID
========================================================= */

.performance-section {

    border:
        1px solid #d5d9dd;

    margin-bottom: 20px;
}

.performance-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);
}

.performance-box {

    padding:
        20px;

    text-align: center;

    border-right:
        1px solid #ddd;
}

.performance-box:last-child {

    border-right: none;
}

.performance-number {

    font-size: 27px;

    font-weight: 800;

    margin-bottom: 5px;
}

.performance-label {

    font-size: 12px;

    color: #777;
}

.correct {

    color: #168238;
}

.wrong {

    color: #d13b2f;
}

.unanswered {

    color: #666;
}


/* =========================================================
   SUBMISSION INFORMATION
========================================================= */

.submission-info {

    border:
        1px solid #d5d9dd;

    margin-bottom: 20px;
}

.info-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding:
        13px 15px;

    border-bottom:
        1px solid #e2e2e2;

    font-size: 13px;
}

.info-row:last-child {

    border-bottom: none;
}

.info-label {

    color: #777;
}

.info-value {

    font-weight: 700;

    color: #333;

    text-align: right;

    overflow-wrap: anywhere;
}


/* =========================================================
   FINAL NOTICE
========================================================= */

.final-notice {

    background: #fff8e6;

    border:
        1px solid #f1d28a;

    padding:
        14px 16px;

    color: #654f18;

    font-size: 13px;

    line-height: 1.5;

    margin-bottom: 20px;
}


/* =========================================================
   FOOTER ACTIONS
========================================================= */

.actions {

    display: flex;

    justify-content: flex-end;

    align-items: center;

    gap: 10px;

    border-top:
        1px solid #ddd;

    padding-top: 18px;
}

.finish-button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 42px;

    background: #063b68;

    color: white;

    text-decoration: none;

    padding:
        10px 25px;

    border-radius: 2px;

    font-size: 14px;

    font-weight: 700;
}

.finish-button:hover {

    background: #052f53;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    max-width: 1050px;

    margin:
        15px auto 0;

    display: flex;

    justify-content: space-between;

    gap: 15px;

    color: #777;

    font-size: 11px;
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 800px) {

    .performance-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }

    .performance-box {

        padding:
            15px 10px;
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

    .candidate-info {

        display: none;
    }

    .candidate-photo {

        width: 29px;
        height: 29px;

        min-width: 29px;
    }

    .logout {

        font-size: 11px;

        padding-left: 8px;
    }

    .system-bar {

        height: 30px;

        padding:
            0 10px;

        font-size: 10px;
    }

    .page {

        padding:
            10px 7px 25px;
    }

    .page-title {

        padding:
            12px;

        flex-direction: column;

        align-items: flex-start;

        margin-bottom: 8px;
    }

    .page-title h1 {

        font-size: 17px;
    }

    .completed-status {

        width: 100%;

        text-align: center;
    }

    .card-header {

        padding:
            12px;

        flex-direction: column;

        align-items: flex-start;

        gap: 4px;
    }

    .card-header-title {

        font-size: 16px;
    }

    .result-content {

        padding:
            10px;
    }

    .submission-panel {

        padding:
            14px;

        gap: 12px;

        align-items: flex-start;
    }

    .success-icon {

        width: 45px;
        height: 45px;

        min-width: 45px;

        font-size: 23px;
    }

    .submission-text h2 {

        font-size: 17px;
    }

    .submission-text p {

        font-size: 12px;
    }

    .exam-heading-top {

        padding:
            10px 12px;
    }

    .exam-heading-name {

        padding:
            12px;

        font-size: 16px;
    }

    .score-body {

        padding:
            18px;
    }

    .score-value {

        font-size: 40px;
    }

    .score-total {

        font-size: 15px;
    }

    .performance-grid {

        grid-template-columns:
            1fr;
    }

    .performance-box {

        border-right: none;

        border-bottom:
            1px solid #ddd;

        padding:
            14px;
    }

    .performance-box:last-child {

        border-bottom: none;
    }

    .performance-number {

        font-size: 24px;
    }

    .info-row {

        align-items: flex-start;

        flex-direction: column;

        gap: 5px;

        padding:
            11px 12px;
    }

    .info-value {

        text-align: left;

        width: 100%;
    }

    .final-notice {

        font-size: 12px;

        padding:
            12px;
    }

    .actions {

        justify-content: stretch;
    }

    .finish-button {

        width: 100%;

        min-height: 44px;
    }

    .footer {

        flex-direction: column;

        text-align: center;

        padding:
            0 5px;
    }
}


/* =========================================================
   VERY SMALL PHONE
========================================================= */

@media (max-width: 380px) {

    .logo-title {

        font-size: 14px;
    }

    .result-content {

        padding:
            8px;
    }

    .submission-panel {

        padding:
            11px;
    }

    .score-value {

        font-size: 36px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
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

    EXAMINATION SYSTEM
    &nbsp; | &nbsp;
    EXAMINATION SUBMISSION

</div>


<!-- =====================================================
     PAGE
===================================================== -->

<main class="page">


    <!-- PAGE TITLE -->

    <div class="page-title">


        <div>

            <h1>
                Examination Submitted
            </h1>

            <p>
                Your examination response has been successfully recorded.
            </p>

        </div>


        <div class="completed-status">

            ✓ EXAMINATION COMPLETED

        </div>


    </div>


    <!-- =================================================
         RESULT CARD
    ================================================= -->

    <section class="result-card">


        <div class="card-header">

            <div class="card-header-title">

                Examination Submission Details

            </div>


            <div class="card-header-code">

                MODUS CBT

            </div>

        </div>


        <div class="result-content">


            <!-- =========================================
                 SUCCESS MESSAGE
            ========================================== -->

            <div class="submission-panel">


                <div class="success-icon">

                    ✓

                </div>


                <div class="submission-text">

                    <h2>
                        Examination Submitted Successfully
                    </h2>

                    <p>

                        Your responses have been submitted
                        successfully and your examination session
                        has now ended.

                    </p>

                </div>


            </div>


            <!-- =========================================
                 EXAM NAME
            ========================================== -->

            <div class="exam-heading">


                <div class="exam-heading-top">

                    EXAMINATION

                </div>


                <div class="exam-heading-name">

                    <?= htmlspecialchars(
                        $result['title']
                    ) ?>

                </div>


            </div>


            <!-- =========================================
                 SCORE
            ========================================== -->

            <div class="score-section">


                <div class="section-heading">

                    Examination Score

                </div>


                <div class="score-body">


                    <div>


                        <div class="score-value">

                            <?= htmlspecialchars(
                                $result['score']
                            ) ?>

                        </div>


                        <div class="score-divider">

                            OUT OF

                        </div>


                        <div class="score-total">

                            <?= htmlspecialchars(
                                $result['total_marks']
                            ) ?>

                            Marks

                        </div>


                        <div class="score-label">

                            Final Recorded Score

                        </div>


                    </div>


                </div>


            </div>


            <!-- =========================================
                 PERFORMANCE
            ========================================== -->

            <div class="performance-section">


                <div class="section-heading">

                    Response Summary

                </div>


                <div class="performance-grid">


                    <div class="performance-box">

                        <div class="performance-number correct">

                            <?= (int)$result['correct_answers'] ?>

                        </div>

                        <div class="performance-label">

                            Correct Answers

                        </div>

                    </div>


                    <div class="performance-box">

                        <div class="performance-number wrong">

                            <?= (int)$result['wrong_answers'] ?>

                        </div>

                        <div class="performance-label">

                            Wrong Answers

                        </div>

                    </div>


                    <div class="performance-box">

                        <div class="performance-number unanswered">

                            <?= (int)$result['unanswered'] ?>

                        </div>

                        <div class="performance-label">

                            Unanswered

                        </div>

                    </div>


                </div>


            </div>


            <!-- =========================================
                 SUBMISSION INFORMATION
            ========================================== -->

            <div class="submission-info">


                <div class="info-row">

                    <span class="info-label">
                        Candidate UID
                    </span>

                    <span class="info-value">

                        <?= htmlspecialchars(
                            $studentUid
                        ) ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Examination Duration
                    </span>

                    <span class="info-value">

                        <?= htmlspecialchars(
                            $result['duration_minutes']
                        ) ?>

                        Minutes

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Submission Status
                    </span>

                    <span class="info-value">

                        Submitted Successfully

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Submitted At
                    </span>

                    <span class="info-value">

                        <?= htmlspecialchars(
                            $result['submitted_at']
                        ) ?>

                    </span>

                </div>


            </div>


            <!-- =========================================
                 FINAL NOTICE
            ========================================== -->

            <div class="final-notice">

                <strong>Important:</strong>

                Please keep your examination credentials
                secure. The result displayed above has been
                retrieved from the MODUS CBT examination server.

                If your institution provides a separate result
                publication system, the officially published
                result should be considered for further use.

            </div>


            <!-- =========================================
                 ACTION
            ========================================== -->

            <div class="actions">

                <a
                    href="logout.php"
                    class="finish-button"
                >
                    FINISH &amp; LOG OUT
                </a>

            </div>


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


</body>

</html>