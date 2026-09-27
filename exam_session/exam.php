<?php

require_once __DIR__ . '/../includes/bootstrap.php';

requireStudent();

$student_id = (int) $_SESSION['student_id'];

/* =========================================================
   GET STUDENT
========================================================= */

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

/* =========================================================
   GET ACTIVE TEST
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        st.id AS student_test_id,
        st.test_id,
        st.status AS student_test_status,
        st.started_at AS student_started_at,

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

/* =========================================================
   CALCULATE EXAM TIME
========================================================= */

$duration_seconds = ((int) $test['duration_minutes']) * 60;

if (empty($test['student_started_at'])) {

    $stmt = $pdo->prepare("
        UPDATE student_tests
        SET
            started_at = NOW(),
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

/* =========================================================
   GET QUESTIONS
========================================================= */

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
    abort_html(404, 'No Questions', 'No questions have been added to this examination. Please contact your invigilator.');
}

/* =========================================================
   SAVED ANSWERS
========================================================= */

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

    $saved_answers[(int)$answer['question_id']]
        = $answer['selected_option'];
}

/* =========================================================
   CURRENT QUESTION
========================================================= */

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
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<title><?= e($page_title) ?> — <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="../assets/img/logo.png">
<link rel="shortcut icon" type="image/png" href="../assets/img/logo.png">
<link rel="apple-touch-icon" href="../assets/img/logo.png">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/exam.css">
<link rel="stylesheet" href="../assets/css/responsive.css">

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
    height: 100%;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f8;
    color: #1f2937;

    overflow-x: hidden;
}

button,
select,
input {
    font-family: inherit;
}

button {
    -webkit-tap-highlight-color: transparent;
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

    box-shadow:
        0 1px 4px rgba(0,0,0,0.25);

    position: relative;
    z-index: 50;
}

.brand-area {

    display: flex;
    align-items: center;

    gap: 14px;

    min-width: 0;
}

.brand-logo-wrap {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 8px;
    background: #063b68;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.brand-logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.modus-logo {

    width: 42px;
    height: 42px;

    min-width: 42px;

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

    min-width: 0;
}

.brand-title {

    font-size: 22px;

    font-weight: 800;

    letter-spacing: 0.5px;

    white-space: nowrap;
}

.brand-subtitle {

    font-size: 12px;

    opacity: 0.8;

    white-space: nowrap;
}

.candidate-top {

    display: flex;
    align-items: center;

    gap: 9px;

    font-size: 15px;

    min-width: 0;
}

.candidate-icon {

    width: 31px;
    height: 31px;

    min-width: 31px;

    background: white;

    color: #063b68;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: bold;
}


/* =========================================================
   INSTRUCTION OVERLAY
========================================================= */

.exam-instruction-overlay {

    position: fixed;

    inset: 0;

    background: #f5f7fa;

    z-index: 99999;

    display: flex;

    align-items: center;
    justify-content: center;

    padding: 30px;

    overflow: auto;
}

.instruction-panel {

    width: 100%;

    max-width: 900px;

    max-height: 90vh;

    background: #fff;

    border-radius: 12px;

    box-shadow:
        0 10px 40px rgba(0,0,0,0.15);

    display: flex;

    flex-direction: column;

    overflow: hidden;
}

.instruction-header {

    background: #1f2937;

    color: #fff;

    padding: 20px 25px;

    flex-shrink: 0;
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

    flex: 1;
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

    flex-shrink: 0;
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

    flex-shrink: 0;
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

    white-space: nowrap;
}

.start-exam-btn:disabled {

    background: #9ca3af;

    cursor: not-allowed;
}


/* =========================================================
   EXAM INFORMATION
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

    gap: 15px;

    box-shadow:
        0 1px 2px rgba(0,0,0,0.05);
}

.candidate-details {

    display: flex;

    align-items: center;

    gap: 16px;

    min-width: 0;
}

.avatar {

    width: 82px;
    height: 82px;

    min-width: 82px;

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

    min-width: 0;
}

.details-row {

    display: flex;

    min-width: 0;
}

.details-label {

    width: 125px;

    min-width: 125px;

    color: #444;
}

.details-value {

    font-weight: 700;

    color: #333;

    min-width: 0;

    overflow-wrap: anywhere;
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

    max-width: 100%;

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

    padding: 0 12px 15px;

    height: calc(100vh - 184px);

    min-height: 400px;
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

    min-height: 0;
}

.question-heading {

    height: 57px;

    min-height: 57px;

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

    min-height: 0;

    overflow-y: auto;

    padding: 25px 28px;

    -webkit-overflow-scrolling: touch;
}

.question-content {

    width: 100%;
}

.question-text {

    font-family:
        Georgia,
        "Times New Roman",
        serif;

    font-size: 20px;

    line-height: 1.55;

    color: #111;

    margin-bottom: 25px;

    white-space: pre-wrap;

    overflow-wrap: anywhere;
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

    font-family:
        Georgia,
        "Times New Roman",
        serif;

    font-size: 18px;

    line-height: 1.4;

    transition:
        background 0.12s ease,
        border-color 0.12s ease;
}

.option:hover {

    background: #f5f8fb;
}

.option:active {

    background: #eef5fb;

    border-color: #c5dff4;
}

.option input {

    margin-top: 4px;

    width: 18px;
    height: 18px;

    min-width: 18px;
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

    flex-shrink: 0;
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
   BOTTOM NAVIGATION
========================================================= */

.bottom-navigation {

    min-height: 55px;

    background: #f7f7f7;

    border-top: 1px solid #d5d9de;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 8px 16px;

    gap: 10px;

    flex-shrink: 0;
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

    min-height: 40px;

    font-size: 15px;

    font-weight: 800;

    cursor: pointer;

    border-radius: 2px;
}


/* =========================================================
   RIGHT QUESTION SIDEBAR
========================================================= */

.sidebar {

    background: white;

    border: 1px solid #d9dee5;

    min-width: 0;

    display: flex;

    flex-direction: column;

    overflow: hidden;

    position: relative;

    z-index: 20;
}

.status-legend {

    margin: 8px;

    border: 2px dotted #444;

    padding: 13px;

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 15px 10px;

    flex-shrink: 0;
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

    flex-shrink: 0;
}

.palette-close {

    display: none;
}

.question-palette {

    flex: 1;

    min-height: 0;

    overflow-y: auto;

    padding: 12px;

    -webkit-overflow-scrolling: touch;
}

.palette-grid {

    display: grid;

    grid-template-columns:
        repeat(8, minmax(34px, 1fr));

    gap: 7px;
}

.palette-btn {

    height: 35px;

    min-width: 34px;

    border: 1px solid #aeb5bc;

    background: #f0f2f4;

    color: #333;

    font-size: 13px;

    cursor: pointer;

    border-radius: 4px;

    position: relative;

    touch-action: manipulation;
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
   MOBILE PALETTE BUTTON
========================================================= */

.palette-toggle {

    display: none;

    position: fixed;

    right: 12px;

    bottom: 72px;

    z-index: 1001;

    background: #063b68;

    color: #fff;

    border: 2px solid white;

    border-radius: 6px;

    padding: 10px 14px;

    font-size: 12px;

    font-weight: 800;

    cursor: pointer;

    box-shadow:
        0 4px 15px rgba(0,0,0,.3);

    touch-action: manipulation;
}

.palette-toggle:active {

    transform: scale(.97);
}

.palette-overlay {

    display: none;

    position: fixed;

    inset: 0;

    background: rgba(0,0,0,.45);

    z-index: 999;
}

.palette-overlay.active {

    display: block;
}


/* =========================================================
   FOOTER
========================================================= */

.exam-footer {

    display: none;
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1100px) {

    .cbt-container {

        grid-template-columns:
            minmax(0, 1fr)
            320px;
    }

    .palette-grid {

        grid-template-columns:
            repeat(6, minmax(36px, 1fr));
    }

    .question-text {

        font-size: 18px;
    }

    .option {

        font-size: 17px;
    }
}


/* =========================================================
   TABLET / SMALL LAPTOP
========================================================= */

@media (max-width: 950px) {

    .modus-header {

        min-height: 60px;

        height: auto;

        padding: 8px 12px;
    }

    .brand-title {

        font-size: 18px;
    }

    .brand-subtitle {

        font-size: 10px;
    }

    .candidate-top {

        font-size: 13px;
    }

    .exam-info {

        margin: 8px;

        padding: 10px 12px;
    }

    .cbt-container {

        display: block;

        height:
            calc(100vh - 175px);

        padding:
            0
            8px
            10px;
    }

    .question-panel {

        width: 100%;

        height: 100%;
    }

    /* DRAWER */

    #questionSidebar.sidebar,
    .sidebar {

        position: fixed !important;

        top: 0 !important;

        right: 0 !important;

        left: auto !important;

        bottom: 0 !important;

        width:
            min(390px, 88vw) !important;

        height: 100vh !important;

        z-index: 1000 !important;

        transform:
            translateX(100%) !important;

        transition:
            transform .25s ease !important;

        box-shadow:
            -8px 0 30px rgba(0,0,0,.28) !important;

        background: #ffffff !important;
    }

    #questionSidebar.sidebar.open,
    .sidebar.open {

        transform:
            translateX(0) !important;

        right: 0 !important;

        left: auto !important;
    }

    .palette-toggle {

        display: block;
    }

    .palette-header {

        height: 52px;

        padding:
            0 12px;

        justify-content: space-between;
    }

    .palette-close {

        display: flex;

        align-items: center;

        justify-content: center;

        width: 34px;

        height: 34px;

        background: #dc2626;

        color: white;

        border: none;

        border-radius: 4px;

        font-size: 21px;

        font-weight: 700;

        cursor: pointer;
    }

    .question-palette {

        padding: 14px;
    }

    .palette-grid {

        grid-template-columns:
            repeat(6, minmax(42px, 1fr));

        gap: 8px;
    }

    .palette-btn {

        height: 42px;

        min-width: 42px;
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    html,
    body {

        width: 100%;

        min-width: 0;

        overflow-x: hidden;
    }

    /* HEADER */

    .modus-header {

        min-height: 55px;

        padding:
            7px 10px;

        gap: 8px;
    }

    .brand-area {

        gap: 8px;

        min-width: 0;
    }

    .modus-logo {

        width: 36px;

        height: 36px;

        min-width: 36px;

        font-size: 20px;
    }

    .brand-title {

        font-size: 16px;

        white-space: nowrap;
    }

    .brand-subtitle {

        display: none;
    }

    .candidate-top {

        max-width: 45%;

        font-size: 12px;

        overflow: hidden;

        text-overflow: ellipsis;

        white-space: nowrap;
    }

    .candidate-icon {

        width: 27px;

        height: 27px;

        min-width: 27px;
    }


    /* EXAM INFORMATION */

    .exam-info {

        margin: 6px;

        padding: 10px;

        min-height: auto;

        gap: 10px;

        flex-direction: column;

        align-items: stretch;
    }

    .candidate-details {

        width: 100%;

        gap: 10px;

        align-items: flex-start;
    }

    .avatar {

        width: 52px;

        height: 52px;

        min-width: 52px;

        font-size: 27px;
    }

    .details-table {

        width: 100%;

        font-size: 12px;

        line-height: 19px;
    }

    .details-row {

        display: grid;

        grid-template-columns:
            92px minmax(0, 1fr);

        gap: 2px;
    }

    .details-label {

        width: auto;

        min-width: 0;
    }

    .details-value {

        min-width: 0;

        overflow-wrap: anywhere;
    }

    .timer {

        min-width: 92px;

        padding:
            2px 8px;

        font-size: 12px;
    }

    .language-select {

        width: 100%;

        height: 38px;

        font-size: 13px;
    }


    /* MAIN */

    .cbt-container {

        height:
            calc(100vh - 215px);

        min-height: 420px;

        padding:
            0
            6px
            5px;
    }

    .question-panel {

        min-height: 0;

        height: 100%;
    }


    /* QUESTION HEADER */

    .question-heading {

        height: 48px;

        min-height: 48px;

        padding:
            0 12px;

        font-size: 16px;
    }

    .scroll-indicator {

        width: 27px;

        height: 27px;

        font-size: 18px;
    }


    /* QUESTION */

    .question-scroll {

        padding:
            17px 14px;

        overflow-y: auto;

        -webkit-overflow-scrolling: touch;
    }

    .question-text {

        font-size: 17px;

        line-height: 1.55;

        margin-bottom: 18px;
    }

    .question-image {

        max-width: 100%;

        max-height: 260px;

        margin:
            12px auto;
    }


    /* OPTIONS */

    .options {

        gap: 7px;
    }

    .option {

        padding:
            11px 8px;

        gap: 8px;

        font-size: 16px;

        line-height: 1.45;

        border-radius: 4px;
    }

    .option input {

        width: 19px;

        height: 19px;

        min-width: 19px;

        margin-top: 3px;
    }

    .option-label {

        min-width: 25px;
    }


    /* ACTION BUTTONS */

    .action-bar {

        min-height: auto;

        padding: 7px;

        display: grid;

        grid-template-columns:
            1fr 1fr;

        gap: 6px;
    }

    .btn {

        width: 100%;

        min-height: 40px;

        padding:
            9px 6px;

        font-size: 11px;

        white-space: normal;

        line-height: 1.2;
    }


    /* BOTTOM NAV */

    .bottom-navigation {

        min-height: 55px;

        padding:
            6px 7px;

        gap: 6px;
    }

    .navigation-left {

        flex: 1;

        display: grid;

        grid-template-columns:
            1fr 1fr;

        gap: 5px;
    }

    .btn-bottom {

        min-height: 40px;

        padding:
            8px 5px;

        font-size: 11px;
    }

    .submit-button {

        min-height: 40px;

        padding:
            8px 12px;

        font-size: 12px;

        white-space: nowrap;
    }


    /* PALETTE */

    .palette-toggle {

        right: 10px;

        bottom: 70px;

        padding:
            10px 13px;

        font-size: 12px;
    }


    /* INSTRUCTIONS */

    .exam-instruction-overlay {

        padding: 10px;

        align-items: center;

        overflow: auto;
    }

    .instruction-panel {

        width: 100%;

        max-height:
            calc(100vh - 20px);

        border-radius: 9px;
    }

    .instruction-header {

        padding:
            15px;
    }

    .instruction-header h2 {

        font-size: 19px;
    }

    .instruction-header p {

        font-size: 12px;

        overflow-wrap: anywhere;
    }

    .instruction-body {

        padding:
            15px;

        font-size: 13px;
    }

    .instruction-body li {

        margin-bottom: 9px;

        line-height: 1.45;
    }

    .instruction-warning {

        padding:
            11px;

        font-size: 12px;
    }

    .instruction-footer {

        padding:
            12px;

        display: flex;

        flex-direction: column;

        align-items: stretch;

        gap: 11px;
    }

    .agree-label {

        font-size: 13px;

        line-height: 1.4;

        align-items: flex-start;
    }

    .start-exam-btn {

        width: 100%;

        min-height: 45px;

        font-size: 14px;
    }
}


/* =========================================================
   VERY SMALL PHONES
========================================================= */

@media (max-width: 400px) {

    .modus-header {

        padding:
            6px 8px;
    }

    .brand-title {

        font-size: 14px;
    }

    .candidate-top {

        max-width: 42%;

        font-size: 11px;
    }

    .candidate-icon {

        width: 24px;

        height: 24px;

        min-width: 24px;

        font-size: 11px;
    }

    .exam-info {

        margin: 4px;

        padding: 8px;
    }

    .avatar {

        width: 45px;

        height: 45px;

        min-width: 45px;

        font-size: 23px;
    }

    .details-table {

        font-size: 11px;

        line-height: 17px;
    }

    .details-row {

        grid-template-columns:
            82px minmax(0, 1fr);
    }

    .question-text {

        font-size: 16px;
    }

    .option {

        font-size: 15px;

        padding:
            10px 6px;
    }

    .btn {

        font-size: 10px;
    }

    .submit-button {

        padding:
            8px 9px;

        font-size: 11px;
    }

    .palette-grid {

        grid-template-columns:
            repeat(5, minmax(43px, 1fr));

        gap: 8px;
    }
}


/* =========================================================
   LANDSCAPE PHONE
========================================================= */

@media (max-width: 900px) and (orientation: landscape) {

    .modus-header {

        min-height: 48px;
    }

    .exam-info {

        min-height: 70px;

        margin: 5px;
    }

    .avatar {

        width: 48px;

        height: 48px;

        min-width: 48px;
    }

    .cbt-container {

        height:
            calc(100vh - 130px);

        padding-bottom: 5px;
    }

    .question-scroll {

        padding:
            12px 15px;
    }

    .question-text {

        font-size: 16px;

        margin-bottom: 12px;
    }

    .option {

        padding: 7px;

        font-size: 14px;
    }

    .action-bar {

        grid-template-columns:
            repeat(4, 1fr);

        padding: 4px;
    }

    .bottom-navigation {

        min-height: 48px;
    }

    .instruction-body {

        max-height: 45vh;
    }
}


/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    .sidebar {

        transition: none;
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


<!-- =====================================================
     EXAM INSTRUCTION OVERLAY
===================================================== -->

<div
    id="examInstructionOverlay"
    class="exam-instruction-overlay"
>

    <div class="instruction-panel">

        <div class="instruction-header">

            <h2>
                Exam Instructions
            </h2>

            <p>
                <?= htmlspecialchars($test['title']) ?>
            </p>

        </div>


        <div class="instruction-body">

            <h3>
                Please read the following instructions carefully
            </h3>

            <ol>

                <li>
                    The examination duration is
                    <strong>
                        <?= (int)$test['duration_minutes'] ?> minutes
                    </strong>.
                </li>

                <li>
                    Once you start the examination,
                    the timer will begin immediately.
                </li>

                <li>
                    Select the appropriate option
                    for each question.
                </li>

                <li>
                    Your answers are saved automatically
                    while you attempt the examination.
                </li>

                <li>
                    You may navigate between questions
                    using the question palette.
                </li>

                <li>
                    You may review questions and change
                    your answers before submitting.
                </li>

                <li>
                    Make sure to submit the examination
                    before the timer reaches zero.
                </li>

                <li>
                    Once the examination is submitted,
                    you will not be able to continue it.
                </li>

            </ol>

            <div class="instruction-warning">

                <strong>Important:</strong>

                Do not refresh or close the browser
                unnecessarily while the examination
                is in progress.

            </div>

        </div>


        <div class="instruction-footer">

            <label class="agree-label">

                <input
                    type="checkbox"
                    id="agreeCheckbox"
                >

                <span>
                    I have read and understood
                    the instructions.
                </span>

            </label>


            <button
                type="button"
                id="startExamButton"
                class="start-exam-btn"
                disabled
            >
                I Agree &amp; Start Exam
            </button>

        </div>

    </div>

</div>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="modus-header">

    <div class="brand-area">

        <div class="brand-logo-wrap">
            <img src="../assets/img/logo.png" alt="<?= e(APP_NAME) ?>" class="brand-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="modus-logo" style="display:none;">M</div>
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

            <?= avatar_initial($student['name']) ?>

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
     MOBILE PALETTE BUTTON
===================================================== -->

<button
    type="button"
    class="palette-toggle"
    id="openPaletteBtn"
>
    ☷ QUESTION PALETTE
</button>


<div
    class="palette-overlay"
    id="paletteOverlay"
></div>


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

            $question_id =
                (int) $question['id'];

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
                        htmlspecialchars(
                            $question['question_text']
                        )
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
            SAVE &amp; NEXT
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
            SAVE &amp; MARK FOR REVIEW
        </button>


        <button
            type="button"
            class="btn btn-review-next"
            id="reviewNextBtn"
        >
            MARK FOR REVIEW &amp; NEXT
        </button>

    </div>


    <!-- =================================================
         BOTTOM NAVIGATION
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
     QUESTION SIDEBAR
===================================================== -->

<aside class="sidebar" id="questionSidebar">


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


    <!-- PALETTE HEADER -->

    <div class="palette-header">

        <span>
            Question Palette
        </span>

        <button
            type="button"
            class="palette-close"
            id="closePaletteBtn"
            aria-label="Close question palette"
        >
            ×
        </button>

    </div>


    <!-- PALETTE -->

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


<script>

/* =========================================================
   INSTRUCTIONS
========================================================= */

const examInstructionOverlay =
    document.getElementById(
        'examInstructionOverlay'
    );

const agreeCheckbox =
    document.getElementById(
        'agreeCheckbox'
    );

const startExamButton =
    document.getElementById(
        'startExamButton'
    );


agreeCheckbox.addEventListener(
    'change',
    function () {

        startExamButton.disabled =
            !this.checked;

    }
);


startExamButton.addEventListener(
    'click',
    function () {

        if (!agreeCheckbox.checked) {
            return;
        }

        examInstructionOverlay.style.display =
            'none';

    }
);


/* =========================================================
   DATA
========================================================= */

const questions =
    <?= json_encode(
        array_map(
            function ($q) {

                return [
                    'id' => (int)$q['id']
                ];

            },
            $questions
        )
    ) ?>;

const testId =
    <?= $test_id ?>;

const studentTestId =
    <?= $student_test_id ?>;

const csrfToken =
    '<?= csrf_token() ?>';

let currentIndex =
    <?= $current_question_index ?>;

let remainingSeconds =
    <?= (int)$remaining_seconds ?>;

let markedForReview =
    new Set();

let visitedQuestions =
    new Set();

visitedQuestions.add(
    currentIndex
);


/* =========================================================
   DOM
========================================================= */

const questionContents =
    document.querySelectorAll(
        '.question-content'
    );

const paletteButtons =
    document.querySelectorAll(
        '.palette-btn'
    );

const questionTitle =
    document.getElementById(
        'questionTitle'
    );

const timerElement =
    document.getElementById(
        'timer'
    );

const questionSidebar =
    document.getElementById(
        'questionSidebar'
    );

const openPaletteBtn =
    document.getElementById(
        'openPaletteBtn'
    );

const closePaletteBtn =
    document.getElementById(
        'closePaletteBtn'
    );

const paletteOverlay =
    document.getElementById(
        'paletteOverlay'
    );


/* =========================================================
   PALETTE DRAWER
========================================================= */

function openPalette() {

    questionSidebar.classList.add(
        'open'
    );

    paletteOverlay.classList.add(
        'active'
    );

    document.body.style.overflow =
        'hidden';
}


function closePalette() {

    questionSidebar.classList.remove(
        'open'
    );

    paletteOverlay.classList.remove(
        'active'
    );

    document.body.style.overflow =
        '';
}


openPaletteBtn.addEventListener(
    'click',
    openPalette
);


closePaletteBtn.addEventListener(
    'click',
    closePalette
);


paletteOverlay.addEventListener(
    'click',
    closePalette
);


/* =========================================================
   TIMER
========================================================= */

function formatTime(seconds) {

    seconds =
        Math.max(0, seconds);

    const hours =
        Math.floor(
            seconds / 3600
        );

    const minutes =
        Math.floor(
            (seconds % 3600) / 60
        );

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


let timerInterval = null;


function updateTimer() {

    timerElement.textContent =
        formatTime(
            remainingSeconds
        );

    timerElement.classList.remove(
        'warning',
        'danger'
    );


    if (remainingSeconds <= 300) {

        timerElement.classList.add(
            'danger'
        );

    } else if (remainingSeconds <= 900) {

        timerElement.classList.add(
            'warning'
        );
    }


    if (remainingSeconds <= 0) {

        if (timerInterval) {

            clearInterval(
                timerInterval
            );
        }

        submitExam(true);

        return;
    }

    remainingSeconds--;
}


updateTimer();

timerInterval =
    setInterval(
        updateTimer,
        1000
    );


/* =========================================================
   SHOW QUESTION
========================================================= */

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


    currentIndex =
        index;

    visitedQuestions.add(
        index
    );


    questionTitle.textContent =
        'Question ' +
        (index + 1);


    updatePalette();

    updateStatistics();


    const container =
        document.getElementById(
            'questionContainer'
        );

    container.scrollTop = 0;
}


/* =========================================================
   GET CURRENT ANSWER
========================================================= */

function getCurrentAnswer() {

    const current =
        questionContents[
            currentIndex
        ];

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


/* =========================================================
   SAVE ANSWER
========================================================= */

async function saveCurrentAnswer() {

    const question =
        questions[
            currentIndex
        ];

    const selected =
        getCurrentAnswer();


    if (!selected) {
        return false;
    }


    try {

        const response =
            await fetch(
                '../answers/save.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',
                        'X-CSRF-Token':
                            csrfToken
                    },

                    body:
                        JSON.stringify({

                            test_id:
                                testId,

                            question_id:
                                question.id,

                            selected_option:
                                selected

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


/* =========================================================
   SAVE & NEXT
========================================================= */

document
    .getElementById(
        'saveNextBtn'
    )
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


/* =========================================================
   CLEAR
========================================================= */

document
    .getElementById(
        'clearBtn'
    )
    .addEventListener(
        'click',
        function () {

            const current =
                questionContents[
                    currentIndex
                ];


            const selected =
                current.querySelector(
                    'input[type="radio"]:checked'
                );


            if (selected) {

                selected.checked =
                    false;
            }


            updatePalette();

            updateStatistics();
        }
    );


/* =========================================================
   SAVE & MARK REVIEW
========================================================= */

document
    .getElementById(
        'saveReviewBtn'
    )
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


/* =========================================================
   MARK REVIEW & NEXT
========================================================= */

document
    .getElementById(
        'reviewNextBtn'
    )
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


/* =========================================================
   BACK
========================================================= */

document
    .getElementById(
        'backBtn'
    )
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


/* =========================================================
   NEXT
========================================================= */

document
    .getElementById(
        'nextBtn'
    )
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


/* =========================================================
   QUESTION PALETTE
========================================================= */

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


                /*
                 * Close drawer on
                 * tablet/mobile.
                 */

                if (
                    window.innerWidth <= 950
                ) {

                    closePalette();
                }
            }
        );

    }
);


/* =========================================================
   PALETTE STATE
========================================================= */

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
                markedForReview.has(
                    index
                );


            const visited =
                visitedQuestions.has(
                    index
                );


            if (
                isReview &&
                selected
            ) {

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


            if (
                index === currentIndex
            ) {

                button.classList.add(
                    'current'
                );
            }

        }
    );
}


/* =========================================================
   STATISTICS
========================================================= */

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
                markedForReview.has(
                    index
                );


            const visited =
                visitedQuestions.has(
                    index
                );


            if (!visited) {

                notVisited++;

            } else if (
                isReview &&
                selected
            ) {

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
    ).textContent =
        notVisited;


    document.getElementById(
        'legendNotAnswered'
    ).textContent =
        notAnswered;


    document.getElementById(
        'legendAnswered'
    ).textContent =
        answered;


    document.getElementById(
        'legendReview'
    ).textContent =
        review;


    document.getElementById(
        'legendAnsweredReview'
    ).textContent =
        answeredReview;
}


/* =========================================================
   RADIO CHANGES
========================================================= */

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


/* =========================================================
   SUBMIT
========================================================= */

let submitting = false;


async function submitExam(
    autoSubmit = false
) {

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


    /*
     * Stop timer.
     */

    if (timerInterval) {

        clearInterval(
            timerInterval
        );
    }


    try {

        /*
         * Save current answer
         * before submission.
         */

        const selected =
            getCurrentAnswer();


        if (selected) {

            await saveCurrentAnswer();
        }

    } catch (error) {

        /*
         * Continue with server
         * submission.
         */
    }



    /*
     * P0.2 FIX: Submit via POST (not GET redirect).
     * Creates a hidden form with CSRF token and submits it.
     * submit.php now enforces POST-only and verifies CSRF.
     */

    const submitForm = document.createElement('form');
    submitForm.method = 'POST';
    submitForm.action = 'submit.php';
    submitForm.style.display = 'none';

    const testIdInput = document.createElement('input');
    testIdInput.type = 'hidden';
    testIdInput.name = 'test_id';
    testIdInput.value = testId;
    submitForm.appendChild(testIdInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_csrf';
    csrfInput.value = csrfToken;
    submitForm.appendChild(csrfInput);

    document.body.appendChild(submitForm);
    submitForm.submit();
}


document
    .getElementById(
        'submitBtn'
    )
    .addEventListener(
        'click',
        function () {

            submitExam(false);

        }
    );


/* =========================================================
   INITIAL STATE
========================================================= */

updatePalette();

updateStatistics();


/* =========================================================
   PREVENT BROWSER BACK
========================================================= */

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


/* =========================================================
   WARN BEFORE LEAVING
========================================================= */

window.addEventListener(
    'beforeunload',
    function (event) {

        if (!submitting) {

            event.preventDefault();

            event.returnValue = '';
        }

    }
);


/* =========================================================
   DESKTOP / MOBILE RESIZE SAFETY
========================================================= */

window.addEventListener(
    'resize',
    function () {

        /*
         * If moving back to desktop,
         * remove mobile drawer state.
         */

        if (window.innerWidth > 950) {
            questionSidebar.classList.remove('open');
            paletteOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
);

// Universal right-click disable
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
}, false);

// ============================================================
// ANTI-CHEAT & FULLSCREEN MODULE
// ============================================================

(function() {
    var examActive = false;            // true once Start Exam is clicked
    var fsWarningShown = false;
    var tabWarningCount = 0;
    var MAX_TAB_WARNINGS = 3;

    // ── Fullscreen helpers ───────────────────────────────────
    function requestFullscreen(el) {
        el = el || document.documentElement;
        if (el.requestFullscreen)           return el.requestFullscreen();
        if (el.webkitRequestFullscreen)     return el.webkitRequestFullscreen();
        if (el.mozRequestFullScreen)        return el.mozRequestFullScreen();
        if (el.msRequestFullscreen)         return el.msRequestFullscreen();
    }
    function exitFullscreen() {
        if (document.exitFullscreen)            return document.exitFullscreen();
        if (document.webkitExitFullscreen)      return document.webkitExitFullscreen();
        if (document.mozCancelFullScreen)       return document.mozCancelFullScreen();
        if (document.msExitFullscreen)          return document.msExitFullscreen();
    }
    function isFullscreen() {
        return !!(
            document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement ||
            document.msFullscreenElement
        );
    }

    // ── Warning overlay ──────────────────────────────────────
    var warnEl = document.createElement('div');
    warnEl.id = 'antiCheatWarn';
    warnEl.innerHTML =
        '<div class="ac-warn-box">' +
          '<div class="ac-warn-icon">&#x26A0;&#xFE0F;</div>' +
          '<div class="ac-warn-title">Tab Switch / Fullscreen Warning</div>' +
          '<div class="ac-warn-msg" id="acWarnMsg">Switching tabs during an exam is not allowed.</div>' +
          '<div class="ac-warn-count" id="acWarnCount"></div>' +
          '<button class="ac-warn-btn" onclick="document.getElementById(\'antiCheatWarn\').style.display=\'none\'; window.__acResumeFullscreen && window.__acResumeFullscreen();">Return to Exam</button>' +
        '</div>';
    var warnStyle = document.createElement('style');
    warnStyle.textContent = [
        '#antiCheatWarn{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:99999;align-items:center;justify-content:center;animation:acFadeIn .2s ease;}',
        '#antiCheatWarn.show{display:flex;}',
        '@keyframes acFadeIn{from{opacity:0}to{opacity:1}}',
        '.ac-warn-box{background:#1e1e2e;border:2px solid #ef4444;border-radius:16px;padding:36px 32px;max-width:420px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.8);}',
        '.ac-warn-icon{font-size:48px;margin-bottom:12px;animation:pulse-ac 1.4s ease-in-out infinite;}',
        '@keyframes pulse-ac{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}',
        '.ac-warn-title{font-size:18px;font-weight:800;color:#f87171;margin-bottom:10px;}',
        '.ac-warn-msg{font-size:13.5px;color:#cbd5e1;margin-bottom:8px;line-height:1.55;}',
        '.ac-warn-count{font-size:12px;color:#f87171;font-weight:700;margin-bottom:22px;min-height:18px;}',
        '.ac-warn-btn{padding:11px 28px;background:#ef4444;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:800;cursor:pointer;transition:background .15s;}',
        '.ac-warn-btn:hover{background:#dc2626;}',
    ].join('');
    document.head.appendChild(warnStyle);
    document.addEventListener('DOMContentLoaded', function() {
        document.body.appendChild(warnEl);
    });

    function showWarn(msg, count) {
        document.getElementById('acWarnMsg').textContent = msg || 'Suspicious activity detected.';
        var countEl = document.getElementById('acWarnCount');
        if (count !== undefined) {
            countEl.textContent = 'Warning ' + count + ' of ' + MAX_TAB_WARNINGS +
                (count >= MAX_TAB_WARNINGS ? ' — This incident has been recorded.' : '');
        } else {
            countEl.textContent = '';
        }
        warnEl.classList.add('show');
        warnEl.style.display = 'flex';
    }

    // ── Attach fullscreen & anti-cheat when exam starts ─────
    var startBtn = document.getElementById('startExamButton');
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            examActive = true;
            requestFullscreen(document.documentElement).catch(function() {
                // Fullscreen might be denied (mobile/browser restriction) — continue anyway
            });
        }, true);
    }

    // ── Fullscreen exit detection ────────────────────────────
    function onFsChange() {
        if (!examActive) return;
        if (!isFullscreen()) {
            window.__acResumeFullscreen = function() {
                requestFullscreen(document.documentElement).catch(function(){});
            };
            showWarn(
                'You have exited fullscreen mode. Please return to exam and do not leave fullscreen.',
                undefined
            );
        }
    }
    document.addEventListener('fullscreenchange', onFsChange);
    document.addEventListener('webkitfullscreenchange', onFsChange);
    document.addEventListener('mozfullscreenchange', onFsChange);
    document.addEventListener('MSFullscreenChange', onFsChange);

    // ── Tab / window visibility change ───────────────────────
    document.addEventListener('visibilitychange', function() {
        if (!examActive) return;
        if (document.visibilityState === 'hidden') {
            tabWarningCount++;
            window.__acResumeFullscreen = function() {
                requestFullscreen(document.documentElement).catch(function(){});
            };
            // We can only show the overlay when they come back
        } else {
            // They returned — show the warning now
            if (tabWarningCount > 0) {
                showWarn(
                    'You switched tabs or minimized the window during the exam. This is not allowed.',
                    tabWarningCount
                );
            }
        }
    });

    // ── Keyboard shortcut blocking ───────────────────────────
    document.addEventListener('keydown', function(e) {
        if (!examActive) return;
        var blocked = false;

        // F12, F5, F11
        if (e.key === 'F12' || e.key === 'F5' || e.key === 'F11') blocked = true;

        // Ctrl / Cmd combos
        if (e.ctrlKey || e.metaKey) {
            var k = e.key.toLowerCase();
            // Block: C (copy), X (cut), V (paste), A (select all), U (view source),
            //        S (save), P (print), D (bookmark), F (find), G (find next),
            //        J (downloads), K, L, N (new window), T (new tab), W (close tab)
            if ('cxvasupdfgjklntw'.indexOf(k) !== -1) blocked = true;
            // Ctrl+Shift+I/J/C (devtools)
            if (e.shiftKey && (k === 'i' || k === 'j' || k === 'c')) blocked = true;
        }

        // Alt+F4, Alt+Tab (limited on browsers but attempt it)
        if (e.altKey && (e.key === 'F4' || e.key === 'Tab')) blocked = true;

        // PrintScreen
        if (e.key === 'PrintScreen') {
            blocked = true;
            // Attempt to clear clipboard (may not work in all browsers)
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText('').catch(function(){});
            }
        }

        if (blocked) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);

    // ── Block copy / cut / paste on the page ─────────────────
    ['copy','cut','paste'].forEach(function(evt) {
        document.addEventListener(evt, function(e) {
            if (!examActive) return;
            e.preventDefault();
            return false;
        }, true);
    });

    // ── Block drag ───────────────────────────────────────────
    document.addEventListener('dragstart', function(e) {
        if (!examActive) return;
        e.preventDefault();
    }, true);

    // ── Disable text selection via CSS once exam starts ──────
    var selStyle = document.createElement('style');
    selStyle.id = 'acNoSelect';
    selStyle.textContent = 'body.exam-active{-webkit-user-select:none!important;user-select:none!important;}';
    document.head.appendChild(selStyle);

    if (startBtn) {
        startBtn.addEventListener('click', function() {
            document.body.classList.add('exam-active');
        });
    }

})();

</script>

</body>
</html>