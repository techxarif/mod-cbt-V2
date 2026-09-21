<?php

require_once '../includes/db.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int) $_SESSION['student_id'];

/*
|--------------------------------------------------------------------------
| Get Student
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name, uid
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$student_id]);

$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Active Test
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        st.id AS student_test_id,
        st.test_id,
        st.status AS student_test_status,

        t.title,
        t.duration_minutes,
        t.total_marks,
        t.negative_marks,
        t.start_time,
        t.end_time,
        t.status AS test_status

    FROM student_tests st

    INNER JOIN tests t
        ON t.id = st.test_id

    WHERE st.student_id = ?
      AND t.status = 'active'

    ORDER BY t.id DESC

    LIMIT 1
");

$stmt->execute([$student_id]);

$test = $stmt->fetch();

if (!$test) {
    header('Location: waiting.php');
    exit;
}

$test_id = (int) $test['test_id'];
$student_test_id = (int) $test['student_test_id'];

/*
|--------------------------------------------------------------------------
| Calculate Exam Time
|--------------------------------------------------------------------------
*/
$duration_seconds = ((int) $test['duration_minutes']) * 60;

if (empty($test['student_started_at'])) {
    $stmt = $pdo->prepare("
        UPDATE student_tests
        SET started_at = NOW(),
            status = 'started'
        WHERE id = ?
          AND started_at IS NULL
    ");
    $stmt->execute([$student_test_id]);

    $student_started_at = date('Y-m-d H:i:s');
} else {
    $student_started_at = $test['student_started_at'];
}

$start_timestamp = strtotime($student_started_at);

$end_timestamp = $start_timestamp + $duration_seconds;

$current_timestamp = time();

$remaining_seconds = $end_timestamp - $current_timestamp;
/*
|--------------------------------------------------------------------------
| Get Questions
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        q.id,
        q.question_text,
        q.question_image,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        q.marks,
        q.negative_marks,
        tq.question_order

    FROM test_questions tq

    INNER JOIN questions q
        ON q.id = tq.question_id

    WHERE tq.test_id = ?

    ORDER BY tq.question_order ASC
");

$stmt->execute([$test_id]);

$questions = $stmt->fetchAll();

if (!$questions) {
    die("No questions have been added to this examination.");
}

/*
|--------------------------------------------------------------------------
| Saved Answers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        question_id,
        selected_option
    FROM answers
    WHERE student_test_id = ?
");

$stmt->execute([$student_test_id]);

$saved_answers = [];

foreach ($stmt->fetchAll() as $answer) {
    $saved_answers[(int)$answer['question_id']] =
        $answer['selected_option'];
}

/*
|--------------------------------------------------------------------------
| Current Question
|--------------------------------------------------------------------------
*/

$current_question_index = 0;

if (isset($_GET['q'])) {

    $requested_question = (int) $_GET['q'];

    if (
        $requested_question >= 0 &&
        $requested_question < count($questions)
    ) {
        $current_question_index = $requested_question;
    }
}

$page_title = "Examination";

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
    <?= htmlspecialchars($test['title']) ?> - MODUS CBT
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
    height: 100%;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
    color: #1f2937;
}

button,
select {
    font-family: inherit;
}

/* =========================================================
   TOP HEADER
========================================================= */

.modus-header {
    height: 64px;
    background: #063b68;
    color: white;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 22px;

    box-shadow: 0 1px 4px rgba(0,0,0,0.25);
}

.brand-area {
    display: flex;
    align-items: center;
    gap: 14px;
}

.modus-logo {
    width: 42px;
    height: 42px;

    border-radius: 7px;

    background: #00a86b;

    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 24px;
    font-weight: 800;
}

.brand-text {
    display: flex;
    flex-direction: column;
}
.exam-instruction-overlay {
    position: fixed;
    inset: 0;
    background: #f5f7fa;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px;
}

.instruction-panel {
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.instruction-header {
    background: #1f2937;
    color: #fff;
    padding: 20px 25px;
}

.instruction-header h2 {
    margin: 0;
    font-size: 24px;
}

.instruction-header p {
    margin: 6px 0 0;
    opacity: .8;
}

.instruction-body {
    padding: 25px;
    overflow-y: auto;
    color: #222;
}

.instruction-body h3 {
    margin-top: 0;
}

.instruction-body li {
    margin-bottom: 12px;
    line-height: 1.5;
}

.instruction-warning {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    padding: 14px;
    border-radius: 8px;
    margin-top: 20px;
}

.instruction-footer {
    padding: 18px 25px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.agree-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 15px;
}

.agree-label input {
    width: 18px;
    height: 18px;
}

.start-exam-btn {
    background: #16a34a;
    color: #fff;
    border: none;
    padding: 13px 25px;
    border-radius: 7px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

.start-exam-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}
.brand-title {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 0.5px;
}

.brand-subtitle {
    font-size: 12px;
    opacity: 0.8;
}

.candidate-top {
    display: flex;
    align-items: center;
    gap: 9px;

    font-size: 15px;
}

.candidate-icon {
    width: 31px;
    height: 31px;

    background: white;
    color: #063b68;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: bold;
}

/* =========================================================
   EXAM INFORMATION BAR
========================================================= */

.exam-info {
    margin: 10px 12px 8px;

    background: white;

    min-height: 100px;

    border: 1px solid #d9dee5;
    border-radius: 2px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 12px 18px;

    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.candidate-details {
    display: flex;
    align-items: center;
    gap: 16px;
}

.avatar {
    width: 82px;
    height: 82px;

    background: #eeeeee;
    border: 1px solid #c9c9c9;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #555;
    font-size: 44px;
}

.details-table {
    line-height: 22px;
    font-size: 15px;
}

.details-row {
    display: flex;
}

.details-label {
    width: 125px;
    color: #444;
}

.details-value {
    font-weight: 700;
    color: #333;
}

.timer {
    display: inline-flex;

    background: #1476d4;
    color: white;

    border-radius: 15px;

    padding: 3px 13px;

    font-weight: 700;
    letter-spacing: 0.5px;

    min-width: 105px;
    justify-content: center;
}

.timer.warning {
    background: #d97706;
}

.timer.danger {
    background: #dc2626;
}

.language-select {
    width: 290px;

    padding: 11px 14px;

    border: 1px solid #c7ccd2;
    border-radius: 2px;

    background: white;

    font-size: 15px;
}

/* =========================================================
   MAIN CBT AREA
========================================================= */

.cbt-container {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        390px;

    gap: 10px;

    padding: 0 12px 70px;

    height: calc(100vh - 184px);
}

/* =========================================================
   QUESTION PANEL
========================================================= */

.question-panel {
    background: white;

    border: 1px solid #d9dee5;

    display: flex;
    flex-direction: column;

    min-width: 0;
}

.question-heading {
    height: 57px;

    padding: 0 18px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid #cfd5db;

    font-size: 20px;
    font-weight: 700;
}

.scroll-indicator {
    width: 32px;
    height: 32px;

    border-radius: 50%;

    background: #1677e8;

    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 23px;
}

.question-scroll {
    flex: 1;

    overflow-y: auto;

    padding: 25px 28px;
}

.question-text {
    font-family: Georgia, "Times New Roman", serif;

    font-size: 20px;
    line-height: 1.55;

    color: #111;

    margin-bottom: 25px;

    white-space: pre-wrap;
}

.question-image {
    max-width: 90%;
    max-height: 350px;

    display: block;

    margin: 15px auto;

    object-fit: contain;
}

/* =========================================================
   OPTIONS
========================================================= */

.options {
    display: flex;
    flex-direction: column;

    gap: 12px;
}

.option {
    display: flex;
    align-items: flex-start;

    gap: 12px;

    padding: 12px;

    border: 1px solid transparent;

    cursor: pointer;

    font-family: Georgia, "Times New Roman", serif;

    font-size: 18px;

    transition: background 0.12s ease;
}

.option:hover {
    background: #f5f8fb;
}

.option input {
    margin-top: 4px;

    width: 18px;
    height: 18px;
}

.option-label {
    font-weight: 600;

    min-width: 28px;
}

/* =========================================================
   ACTION BAR
========================================================= */

.action-bar {
    min-height: 62px;

    padding: 9px 16px;

    border-top: 1px solid #d4d9de;

    display: flex;

    align-items: center;

    gap: 7px;

    flex-wrap: wrap;
}

.btn {
    border: 1px solid #c9ced4;

    background: white;

    padding: 10px 15px;

    min-height: 38px;

    font-size: 14px;
    font-weight: 700;

    cursor: pointer;

    border-radius: 2px;
}

.btn:hover {
    filter: brightness(0.96);
}

.btn-save {
    background: #20a84b;
    border-color: #178a3a;
    color: white;
}

.btn-clear {
    background: white;
    color: #333;
}

.btn-review {
    background: #f39a22;
    border-color: #db8210;
    color: white;
}

.btn-review-next {
    background: #1677d2;
    border-color: #0e63b4;
    color: white;
}

.btn-bottom {
    background: white;
    color: #333;
}

/* =========================================================
   NAVIGATION BAR
========================================================= */

.bottom-navigation {
    min-height: 55px;

    background: #f7f7f7;

    border-top: 1px solid #d5d9de;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 8px 16px;
}

.navigation-left {
    display: flex;
    gap: 5px;
}

.submit-button {
    background: #20a84b;

    color: white;

    border: none;

    padding: 11px 25px;

    font-size: 15px;
    font-weight: 800;

    cursor: pointer;

    border-radius: 2px;
}

/* =========================================================
   RIGHT SIDEBAR
========================================================= */

.sidebar {
    background: white;

    border: 1px solid #d9dee5;

    min-width: 0;

    display: flex;

    flex-direction: column;

    overflow: hidden;
}

.status-legend {
    margin: 8px;

    border: 2px dotted #444;

    padding: 13px;

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 15px 10px;
}

.legend-item {
    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 14px;
}

.legend-symbol {
    width: 39px;
    height: 33px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 700;

    border-radius: 3px;

    flex-shrink: 0;
}

.legend-not-visited {
    background: #eeeeee;
    border: 1px solid #aaa;
}

.legend-answered {
    background: #1caf00;
    color: white;
}

.legend-not-answered {
    background: #ed5a00;
    color: white;
}

.legend-review {
    background: #5c20a8;
    color: white;
    border-radius: 50%;
}

.legend-answer-review {
    background: #5c20a8;
    color: white;
    border-radius: 50%;
}

/* =========================================================
   PALETTE
========================================================= */

.palette-header {
    height: 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-bottom: 1px solid #ddd;

    font-weight: 700;

    color: #333;
}

.question-palette {
    flex: 1;

    overflow-y: auto;

    padding: 12px;
}

.palette-grid {
    display: grid;

    grid-template-columns:
        repeat(8, minmax(34px, 1fr));

    gap: 7px;
}

.palette-btn {
    height: 35px;

    border: 1px solid #aeb5bc;

    background: #f0f2f4;

    color: #333;

    font-size: 13px;

    cursor: pointer;

    border-radius: 4px;

    position: relative;
}

.palette-btn:hover {
    border-color: #1677d2;
}

.palette-btn.not-answered {
    background: #ed5a00;
    color: white;
    border-color: #ed5a00;
}

.palette-btn.answered {
    background: #18aa0b;
    color: white;
    border-color: #18aa0b;
}

.palette-btn.review {
    background: #5b20a8;
    color: white;
    border-color: #5b20a8;
    border-radius: 50%;
}

.palette-btn.answered-review {
    background: #5b20a8;
    color: white;
    border-color: #5b20a8;
    border-radius: 50%;
}

.palette-btn.current {
    outline: 3px solid #1677d2;
    outline-offset: 1px;
}

/* =========================================================
   FOOTER
========================================================= */

.exam-footer {
    position: fixed;

    bottom: 0;
    left: 0;
    right: 0;

    height: 0px;

    background: #063b68;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;

    z-index: 100;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .cbt-container {
        grid-template-columns: 1fr;
        height: auto;
    }

    .sidebar {
        min-height: 500px;
    }

    .language-select {
        width: 180px;
    }

}

@media (max-width: 700px) {

    .modus-header {
        padding: 0 10px;
    }

    .brand-subtitle {
        display: none;
    }

    .brand-title {
        font-size: 17px;
    }

    .exam-info {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .language-select {
        width: 100%;
    }

    .candidate-details {
        align-items: flex-start;
    }

    .avatar {
        width: 60px;
        height: 60px;
        font-size: 30px;
    }

    .details-label {
        width: 105px;
    }

    .question-text {
        font-size: 17px;
    }

    .palette-grid {
        grid-template-columns:
            repeat(6, minmax(35px, 1fr));
    }

}

/* =========================================================
   SCROLLBAR
========================================================= */

::-webkit-scrollbar {
    width: 9px;
    height: 9px;
}

::-webkit-scrollbar-track {
    background: #eeeeee;
}

::-webkit-scrollbar-thumb {
    background: #aeb4ba;
    border-radius: 4px;
}

</style>

</head>

<body>
    <div id="examInstructionOverlay" class="exam-instruction-overlay">

    <div class="instruction-panel">

        <div class="instruction-header">
            <h2>Exam Instructions</h2>
            <p><?= htmlspecialchars($test['title']) ?></p>
        </div>

        <div class="instruction-body">

            <h3>Please read the following instructions carefully</h3>

            <ol>
                <li>
                    The examination duration is
                    <strong><?= (int)$test['duration_minutes'] ?> minutes</strong>.
                </li>

                <li>
                    Once you start the examination, the timer will begin immediately.
                </li>

                <li>
                    Select the appropriate option for each question.
                </li>

                <li>
                    Your answers are saved automatically while you attempt the examination.
                </li>

                <li>
                    You may navigate between questions using the question palette.
                </li>

                <li>
                    You may review questions and change your answers before submitting.
                </li>

                <li>
                    Make sure to submit the examination before the timer reaches zero.
                </li>

                <li>
                    Once the examination is submitted, you will not be able to continue it.
                </li>
            </ol>

            <div class="instruction-warning">
                <strong>Important:</strong>
                Do not refresh or close the browser unnecessarily while the examination
                is in progress.
            </div>

        </div>

        <div class="instruction-footer">

            <label class="agree-label">
                <input type="checkbox" id="agreeCheckbox">
                <span>I have read and understood the instructions.</span>
            </label>

            <button
                type="button"
                id="startExamButton"
                class="start-exam-btn"
                disabled
            >
                I Agree & Start Exam
            </button>

        </div>

    </div>

</div>

<!-- =====================================================
     HEADER
===================================================== -->

<header class="modus-header">

    <div class="brand-area">

        <div class="modus-logo">
            M
        </div>

        <div class="brand-text">

            <div class="brand-title">
                MODUS CBT
            </div>

            <div class="brand-subtitle">
                MODUS CBT Testing Platform
            </div>

        </div>

    </div>


    <div class="candidate-top">

        <div class="candidate-icon">
            <?= strtoupper(substr($student['name'], 0, 1)) ?>
        </div>

        <?= htmlspecialchars($student['name']) ?>

    </div>

</header>


<!-- =====================================================
     EXAM INFORMATION
===================================================== -->

<section class="exam-info">

    <div class="candidate-details">

        <div class="avatar">
            👤
        </div>


        <div class="details-table">

            <div class="details-row">

                <div class="details-label">
                    Candidate Name
                </div>

                <div class="details-value">
                    : <?= htmlspecialchars($student['name']) ?>
                </div>

            </div>


            <div class="details-row">

                <div class="details-label">
                    Exam Name
                </div>

                <div class="details-value">
                    : <?= htmlspecialchars($test['title']) ?>
                </div>

            </div>


            <div class="details-row">

                <div class="details-label">
                    Subject Name
                </div>

                <div class="details-value">
                    : Full Test
                </div>

            </div>


            <div class="details-row">

                <div class="details-label">
                    Remaining Time
                </div>

                <div class="details-value">

                    :

                    <span
                        id="timer"
                        class="timer"
                        data-seconds="<?= $remaining_seconds ?>"
                    >
                        00:00:00
                    </span>

                </div>

            </div>

        </div>

    </div>


    <select class="language-select">

        <option value="English">
            English
        </option>

    </select>

</section>


<!-- =====================================================
     CBT MAIN
===================================================== -->

<main class="cbt-container">


<!-- =====================================================
     QUESTION PANEL
===================================================== -->

<section class="question-panel">

    <div class="question-heading">

        <span id="questionTitle">
            Question <?= $current_question_index + 1 ?>
        </span>

        <span class="scroll-indicator">
            ↓
        </span>

    </div>


    <div
        class="question-scroll"
        id="questionContainer"
    >

        <?php foreach ($questions as $index => $question): ?>

            <?php
                $question_id = (int) $question['id'];

                $selected =
                    $saved_answers[$question_id]
                    ?? null;
            ?>

            <div
                class="question-content"
                data-question-index="<?= $index ?>"
                data-question-id="<?= $question_id ?>"
                style="<?= $index === $current_question_index ? '' : 'display:none;' ?>"
            >

                <div class="question-text">

                    <?= nl2br(
                        htmlspecialchars($question['question_text'])
                    ) ?>

                </div>


                <?php if (!empty($question['question_image'])): ?>

                    <img
                        class="question-image"
                        src="../<?= htmlspecialchars($question['question_image']) ?>"
                        alt="Question image"
                    >

                <?php endif; ?>


                <div class="options">

                    <?php
                    $options = [
                        'A' => $question['option_a'],
                        'B' => $question['option_b'],
                        'C' => $question['option_c'],
                        'D' => $question['option_d']
                    ];
                    ?>

                    <?php foreach ($options as $letter => $option): ?>

                        <label class="option">

                            <input
                                type="radio"
                                name="question_<?= $question_id ?>"
                                value="<?= $letter ?>"
                                data-question-id="<?= $question_id ?>"
                                <?= $selected === $letter ? 'checked' : '' ?>
                            >

                            <span class="option-label">
                                (<?= $letter ?>)
                            </span>

                            <span>
                                <?= nl2br(
                                    htmlspecialchars($option)
                                ) ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- =================================================
         ACTION BUTTONS
    ================================================= -->

    <div class="action-bar">

        <button
            type="button"
            class="btn btn-save"
            id="saveNextBtn"
        >
            SAVE & NEXT
        </button>


        <button
            type="button"
            class="btn btn-clear"
            id="clearBtn"
        >
            CLEAR
        </button>


        <button
            type="button"
            class="btn btn-review"
            id="saveReviewBtn"
        >
            SAVE & MARK FOR REVIEW
        </button>


        <button
            type="button"
            class="btn btn-review-next"
            id="reviewNextBtn"
        >
            MARK FOR REVIEW & NEXT
        </button>

    </div>


    <!-- =================================================
         BACK / NEXT / SUBMIT
    ================================================= -->

    <div class="bottom-navigation">

        <div class="navigation-left">

            <button
                type="button"
                class="btn btn-bottom"
                id="backBtn"
            >
                &lt;&lt; BACK
            </button>

            <button
                type="button"
                class="btn btn-bottom"
                id="nextBtn"
            >
                NEXT &gt;&gt;
            </button>

        </div>


        <button
            type="button"
            class="submit-button"
            id="submitBtn"
        >
            SUBMIT
        </button>

    </div>

</section>


<!-- =====================================================
     RIGHT SIDEBAR
===================================================== -->

<aside class="sidebar">


    <!-- STATUS LEGEND -->

    <div class="status-legend">

        <div class="legend-item">

            <span
                class="legend-symbol legend-not-visited"
                id="legendNotVisited"
            >
                0
            </span>

            <span>
                Not Visited
            </span>

        </div>


        <div class="legend-item">

            <span
                class="legend-symbol legend-not-answered"
                id="legendNotAnswered"
            >
                0
            </span>

            <span>
                Not Answered
            </span>

        </div>


        <div class="legend-item">

            <span
                class="legend-symbol legend-answered"
                id="legendAnswered"
            >
                0
            </span>

            <span>
                Answered
            </span>

        </div>


        <div class="legend-item">

            <span
                class="legend-symbol legend-review"
                id="legendReview"
            >
                0
            </span>

            <span>
                Marked for Review
            </span>

        </div>


        <div
            class="legend-item"
            style="grid-column: 1 / -1;"
        >

            <span
                class="legend-symbol legend-answer-review"
                id="legendAnsweredReview"
            >
                0
            </span>

            <span>
                Answered &amp; Marked for Review
                <br>
                <small>
                    (will be considered for evaluation)
                </small>
            </span>

        </div>

    </div>


    <!-- PALETTE -->

    <div class="palette-header">
        Question Palette
    </div>


    <div class="question-palette">

        <div class="palette-grid">

            <?php foreach ($questions as $index => $question): ?>

                <button
                    type="button"
                    class="palette-btn"
                    data-index="<?= $index ?>"
                >
                    <?= str_pad(
                        $index + 1,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ) ?>
                </button>

            <?php endforeach; ?>

        </div>

    </div>

</aside>

</main>


<!-- =====================================================
     FOOTER
===================================================== -->




<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>
    const examInstructionOverlay = document.getElementById('examInstructionOverlay');
const agreeCheckbox = document.getElementById('agreeCheckbox');
const startExamButton = document.getElementById('startExamButton');

agreeCheckbox.addEventListener('change', function () {
    startExamButton.disabled = !this.checked;
});

startExamButton.addEventListener('click', function () {

    if (!agreeCheckbox.checked) {
        return;
    }

    examInstructionOverlay.style.display = 'none';

});

const questions = <?= json_encode(
    array_map(
        function ($q) {
            return [
                'id' => (int)$q['id']
            ];
        },
        $questions
    )
) ?>;

const testId = <?= $test_id ?>;

const studentTestId = <?= $student_test_id ?>;

let currentIndex = <?= $current_question_index ?>;

let remainingSeconds =
    <?= (int)$remaining_seconds ?>;

let markedForReview = new Set();

let visitedQuestions = new Set();

visitedQuestions.add(currentIndex);


/*
|--------------------------------------------------------------------------
| DOM
|--------------------------------------------------------------------------
*/

const questionContents =
    document.querySelectorAll('.question-content');

const paletteButtons =
    document.querySelectorAll('.palette-btn');

const questionTitle =
    document.getElementById('questionTitle');

const timerElement =
    document.getElementById('timer');


/*
|--------------------------------------------------------------------------
| Timer
|--------------------------------------------------------------------------
*/

function formatTime(seconds) {

    seconds = Math.max(0, seconds);

    const hours =
        Math.floor(seconds / 3600);

    const minutes =
        Math.floor((seconds % 3600) / 60);

    const secs =
        seconds % 60;

    return (
        String(hours).padStart(2, '0') +
        ':' +
        String(minutes).padStart(2, '0') +
        ':' +
        String(secs).padStart(2, '0')
    );
}


function updateTimer() {

    timerElement.textContent =
        formatTime(remainingSeconds);

    timerElement.classList.remove(
        'warning',
        'danger'
    );

    if (remainingSeconds <= 300) {
        timerElement.classList.add('danger');
    } else if (remainingSeconds <= 900) {
        timerElement.classList.add('warning');
    }

    if (remainingSeconds <= 0) {

        clearInterval(timerInterval);

        submitExam(true);

        return;
    }

    remainingSeconds--;
}


updateTimer();

const timerInterval =
    setInterval(updateTimer, 1000);


/*
|--------------------------------------------------------------------------
| Question Navigation
|--------------------------------------------------------------------------
*/

function showQuestion(index) {

    if (
        index < 0 ||
        index >= questions.length
    ) {
        return;
    }

    questionContents.forEach(
        (element, i) => {

            element.style.display =
                i === index
                    ? ''
                    : 'none';

        }
    );

    currentIndex = index;

    visitedQuestions.add(index);

    questionTitle.textContent =
        'Question ' + (index + 1);

    updatePalette();

    updateStatistics();

    const container =
        document.getElementById(
            'questionContainer'
        );

    container.scrollTop = 0;
}


/*
|--------------------------------------------------------------------------
| Get Current Answer
|--------------------------------------------------------------------------
*/

function getCurrentAnswer() {

    const current =
        questionContents[currentIndex];

    if (!current) {
        return null;
    }

    const selected =
        current.querySelector(
            'input[type="radio"]:checked'
        );

    return selected
        ? selected.value
        : null;
}


/*
|--------------------------------------------------------------------------
| Save Answer
|--------------------------------------------------------------------------
*/

async function saveCurrentAnswer() {

    const question =
        questions[currentIndex];

    const selected =
        getCurrentAnswer();

    if (!selected) {
        return false;
    }

    try {

        const response =
            await fetch(
                '../api/save_answer.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({
                        test_id: testId,
                        question_id: question.id,
                        selected_option: selected
                    })
                }
            );

        const data =
            await response.json();

        if (!data.success) {

            alert(
                data.message ||
                'Unable to save answer.'
            );

            return false;
        }

        return true;

    } catch (error) {

        alert(
            'Connection error. Your answer could not be saved.'
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| Save & Next
|--------------------------------------------------------------------------
*/

document
    .getElementById('saveNextBtn')
    .addEventListener(
        'click',
        async function () {

            const selected =
                getCurrentAnswer();

            if (selected) {

                const saved =
                    await saveCurrentAnswer();

                if (!saved) {
                    return;
                }

                markedForReview.delete(
                    currentIndex
                );
            }

            if (
                currentIndex <
                questions.length - 1
            ) {

                showQuestion(
                    currentIndex + 1
                );

            } else {

                updatePalette();
                updateStatistics();
            }
        }
    );


/*
|--------------------------------------------------------------------------
| Clear
|--------------------------------------------------------------------------
*/

document
    .getElementById('clearBtn')
    .addEventListener(
        'click',
        function () {

            const current =
                questionContents[currentIndex];

            const selected =
                current.querySelector(
                    'input[type="radio"]:checked'
                );

            if (selected) {
                selected.checked = false;
            }

            updatePalette();
            updateStatistics();
        }
    );


/*
|--------------------------------------------------------------------------
| Save & Mark Review
|--------------------------------------------------------------------------
*/

document
    .getElementById('saveReviewBtn')
    .addEventListener(
        'click',
        async function () {

            const selected =
                getCurrentAnswer();

            if (!selected) {

                markedForReview.add(
                    currentIndex
                );

                updatePalette();
                updateStatistics();

                return;
            }

            const saved =
                await saveCurrentAnswer();

            if (!saved) {
                return;
            }

            markedForReview.add(
                currentIndex
            );

            updatePalette();
            updateStatistics();
        }
    );


/*
|--------------------------------------------------------------------------
| Mark Review & Next
|--------------------------------------------------------------------------
*/

document
    .getElementById('reviewNextBtn')
    .addEventListener(
        'click',
        async function () {

            const selected =
                getCurrentAnswer();

            if (selected) {

                const saved =
                    await saveCurrentAnswer();

                if (!saved) {
                    return;
                }
            }

            markedForReview.add(
                currentIndex
            );

            updatePalette();

            updateStatistics();

            if (
                currentIndex <
                questions.length - 1
            ) {

                showQuestion(
                    currentIndex + 1
                );
            }
        }
    );


/*
|--------------------------------------------------------------------------
| Back
|--------------------------------------------------------------------------
*/

document
    .getElementById('backBtn')
    .addEventListener(
        'click',
        function () {

            if (currentIndex > 0) {

                showQuestion(
                    currentIndex - 1
                );
            }
        }
    );


/*
|--------------------------------------------------------------------------
| Next
|--------------------------------------------------------------------------
*/

document
    .getElementById('nextBtn')
    .addEventListener(
        'click',
        function () {

            if (
                currentIndex <
                questions.length - 1
            ) {

                showQuestion(
                    currentIndex + 1
                );
            }
        }
    );


/*
|--------------------------------------------------------------------------
| Palette
|--------------------------------------------------------------------------
*/

paletteButtons.forEach(
    button => {

        button.addEventListener(
            'click',
            function () {

                const index =
                    Number(
                        this.dataset.index
                    );

                showQuestion(index);
            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| Palette State
|--------------------------------------------------------------------------
*/

function updatePalette() {

    paletteButtons.forEach(
        (button, index) => {

            button.className =
                'palette-btn';

            const content =
                questionContents[index];

            const selected =
                content.querySelector(
                    'input[type="radio"]:checked'
                );

            const isReview =
                markedForReview.has(index);

            const visited =
                visitedQuestions.has(index);

            if (isReview && selected) {

                button.classList.add(
                    'answered-review'
                );

            } else if (isReview) {

                button.classList.add(
                    'review'
                );

            } else if (selected) {

                button.classList.add(
                    'answered'
                );

            } else if (visited) {

                button.classList.add(
                    'not-answered'
                );
            }

            if (index === currentIndex) {

                button.classList.add(
                    'current'
                );
            }
        }
    );
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

function updateStatistics() {

    let notVisited = 0;
    let notAnswered = 0;
    let answered = 0;
    let review = 0;
    let answeredReview = 0;

    questionContents.forEach(
        (content, index) => {

            const selected =
                content.querySelector(
                    'input[type="radio"]:checked'
                );

            const isReview =
                markedForReview.has(index);

            const visited =
                visitedQuestions.has(index);

            if (!visited) {

                notVisited++;

            } else if (isReview && selected) {

                answeredReview++;

            } else if (isReview) {

                review++;

            } else if (selected) {

                answered++;

            } else {

                notAnswered++;
            }
        }
    );

    document.getElementById(
        'legendNotVisited'
    ).textContent = notVisited;

    document.getElementById(
        'legendNotAnswered'
    ).textContent = notAnswered;

    document.getElementById(
        'legendAnswered'
    ).textContent = answered;

    document.getElementById(
        'legendReview'
    ).textContent = review;

    document.getElementById(
        'legendAnsweredReview'
    ).textContent = answeredReview;
}


/*
|--------------------------------------------------------------------------
| Radio Changes
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        'input[type="radio"]'
    )
    .forEach(
        radio => {

            radio.addEventListener(
                'change',
                function () {

                    updatePalette();
                    updateStatistics();

                }
            );

        }
    );


/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

let submitting = false;

async function submitExam(autoSubmit = false) {

    if (submitting) {
        return;
    }

    if (!autoSubmit) {

        const confirmed =
            confirm(
                'Are you sure you want to submit the examination?'
            );

        if (!confirmed) {
            return;
        }
    }

    submitting = true;

    try {

        /*
         * Save current answer before submission.
         */
        const selected =
            getCurrentAnswer();

        if (selected) {
            await saveCurrentAnswer();
        }

    } catch (error) {
        // Continue to server submission.
    }

    window.location.href =
        'submit.php?test_id=' +
        encodeURIComponent(testId);
}


document
    .getElementById('submitBtn')
    .addEventListener(
        'click',
        function () {

            submitExam(false);

        }
    );


/*
|--------------------------------------------------------------------------
| Initial State
|--------------------------------------------------------------------------
*/

updatePalette();
updateStatistics();


/*
|--------------------------------------------------------------------------
| Prevent Browser Back
|--------------------------------------------------------------------------
*/

history.pushState(
    null,
    '',
    location.href
);

window.addEventListener(
    'popstate',
    function () {

        history.pushState(
            null,
            '',
            location.href
        );

    }
);


/*
|--------------------------------------------------------------------------
| Warn Before Leaving
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'beforeunload',
    function (event) {

        if (!submitting) {

            event.preventDefault();

            event.returnValue = '';
        }
    }
);

</script>

</body>
</html>