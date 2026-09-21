<?php

require_once '../includes/db.php';
require_once '../includes/auth.php';

requireTeacher();

$page_title = 'Dashboard';

/*
|--------------------------------------------------------------------------
| Teacher
|--------------------------------------------------------------------------
*/

$teacher_name = $_SESSION['teacher_name'] ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM students
    WHERE status = 'active'
");

$active_students = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM tests
");

$total_tests = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM questions
");

$total_questions = (int) $stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM student_tests
    WHERE status = 'submitted'
");

$total_submissions = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Assigned Students
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM student_tests
");

$total_assignments = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Current Examination
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        t.id,
        t.title,
        t.duration_minutes,
        t.total_marks,
        t.negative_marks,
        t.start_time,
        t.status

    FROM tests t

    WHERE t.status IN ('waiting', 'active')

    ORDER BY
        CASE
            WHEN t.status = 'active' THEN 1
            ELSE 2
        END,
        t.id DESC

    LIMIT 1
");

$current_exam = $stmt->fetch();


$current_exam_stats = [
    'assigned' => 0,
    'started' => 0,
    'submitted' => 0
];


if ($current_exam) {

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS assigned,

            SUM(
                CASE
                    WHEN status = 'started'
                    THEN 1 ELSE 0
                END
            ) AS started,

            SUM(
                CASE
                    WHEN status = 'submitted'
                    THEN 1 ELSE 0
                END
            ) AS submitted

        FROM student_tests

        WHERE test_id = ?
    ");

    $stmt->execute([
        $current_exam['id']
    ]);

    $current_exam_stats =
        $stmt->fetch();

}


/*
|--------------------------------------------------------------------------
| Recent Tests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        t.id,
        t.title,
        t.duration_minutes,
        t.total_marks,
        t.status,
        t.created_at,

        (
            SELECT COUNT(*)
            FROM test_questions tq
            WHERE tq.test_id = t.id
        ) AS question_count,

        (
            SELECT COUNT(*)
            FROM student_tests st
            WHERE st.test_id = t.id
              AND st.status = 'submitted'
        ) AS submissions

    FROM tests t

    ORDER BY t.id DESC

    LIMIT 6
");

$recent_tests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Recent Student Activity
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        st.status,
        st.score,
        st.submitted_at,
        s.name,
        s.uid,
        t.title

    FROM student_tests st

    INNER JOIN students s
        ON s.id = st.student_id

    INNER JOIN tests t
        ON t.id = st.test_id

    WHERE st.status = 'submitted'

    ORDER BY st.submitted_at DESC

    LIMIT 5
");

$recent_activity = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Test Status Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        status,
        COUNT(*) AS total

    FROM tests

    GROUP BY status
");

$test_status = [
    'draft' => 0,
    'waiting' => 0,
    'active' => 0,
    'completed' => 0
];

foreach ($stmt->fetchAll() as $row) {

    if (isset($test_status[$row['status']])) {

        $test_status[$row['status']] =
            (int)$row['total'];
    }
}


/*
|--------------------------------------------------------------------------
| Submission Rate
|--------------------------------------------------------------------------
*/

$submission_rate = 0;

if ($total_assignments > 0) {

    $submission_rate =
        round(
            ($total_submissions / $total_assignments) * 100
        );
}


/*
|--------------------------------------------------------------------------
| Include Header
|--------------------------------------------------------------------------
*/

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
    MODUS CBT — Dashboard
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
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background: #090b16;

    color: #f4f5fa;
}


/* =========================================================
   APP
========================================================= */

.app {

    min-height: 100vh;

    display: flex;

    background:
        radial-gradient(
            circle at 80% 0%,
            rgba(81, 61, 180, 0.12),
            transparent 28%
        ),
        #090b16;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 235px;

    min-height: 100vh;

    background: #080a13;

    border-right: 1px solid #1b1e2d;

    padding: 22px 14px;

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    z-index: 20;
}


.brand {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 4px 10px 28px;
}


.brand-logo {

    width: 34px;
    height: 34px;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #6657ff,
            #8a5cff
        );

    display: flex;

    align-items: center;
    justify-content: center;

    color: white;

    font-weight: 900;

    font-size: 17px;

    box-shadow:
        0 5px 20px rgba(100, 80, 255, 0.28);
}


.brand-text {

    display: flex;

    flex-direction: column;
}


.brand-name {

    font-size: 17px;

    font-weight: 800;

    letter-spacing: 0.4px;
}


.brand-subtitle {

    font-size: 9px;

    color: #777b91;

    margin-top: 2px;

    letter-spacing: 0.5px;
}


/* =========================================================
   NAVIGATION
========================================================= */

.nav-label {

    color: #666b82;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1px;

    padding: 0 11px 9px;
}


.nav {

    display: flex;

    flex-direction: column;

    gap: 3px;
}


.nav a {

    text-decoration: none;

    color: #969bad;

    padding: 10px 11px;

    border-radius: 8px;

    font-size: 13px;

    display: flex;

    align-items: center;

    gap: 11px;

    transition: 0.15s ease;
}


.nav a:hover {

    color: white;

    background: #151827;
}


.nav a.active {

    color: white;

    background:
        linear-gradient(
            90deg,
            rgba(98, 82, 255, 0.25),
            rgba(98, 82, 255, 0.08)
        );

    box-shadow:
        inset 3px 0 0 #6c5cff;
}


.nav-icon {

    width: 20px;

    text-align: center;

    font-size: 15px;
}


/* =========================================================
   SIDEBAR BOTTOM
========================================================= */

.sidebar-bottom {

    position: absolute;

    left: 14px;
    right: 14px;
    bottom: 18px;

    border-top: 1px solid #1a1d2b;

    padding-top: 15px;
}


.admin-mini {

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 8px;
}


.admin-avatar {

    width: 32px;
    height: 32px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #6257ff,
            #a25cff
        );

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 12px;

    font-weight: 800;
}


.admin-info {

    overflow: hidden;
}


.admin-name {

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.admin-role {

    font-size: 10px;

    color: #666b80;

    margin-top: 2px;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 235px;

    width: calc(100% - 235px);

    min-height: 100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 68px;

    border-bottom: 1px solid #1b1e2d;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 27px;

    background:
        rgba(9,11,22,0.86);

    backdrop-filter: blur(10px);

    position: sticky;

    top: 0;

    z-index: 10;
}


.breadcrumb {

    color: #777c91;

    font-size: 12px;
}


.breadcrumb strong {

    color: #e8e9f0;

    font-weight: 600;
}


.top-actions {

    display: flex;

    align-items: center;

    gap: 8px;
}


.icon-button {

    width: 34px;
    height: 34px;

    border: 1px solid #24283a;

    background: #10121e;

    color: #9da1b4;

    border-radius: 8px;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;
}


.icon-button:hover {

    color: white;

    border-color: #373c55;
}


.profile {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-left: 6px;

    padding-left: 10px;

    border-left: 1px solid #24283a;
}


.profile-avatar {

    width: 32px;
    height: 32px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #554cff,
            #b85cff
        );

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 11px;

    font-weight: 800;
}


.profile-text {

    line-height: 14px;
}


.profile-name {

    font-size: 11px;

    font-weight: 700;
}


.profile-role {

    color: #686d82;

    font-size: 9px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding: 28px;
}


.page-heading {

    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    margin-bottom: 23px;
}


.page-heading h1 {

    margin: 0;

    font-size: 26px;

    letter-spacing: -0.7px;
}


.page-heading p {

    margin: 6px 0 0;

    color: #74798e;

    font-size: 12px;
}


.heading-actions {

    display: flex;

    gap: 8px;
}


.btn {

    border: 1px solid #282c40;

    background: #111420;

    color: #c4c7d3;

    border-radius: 7px;

    padding: 9px 13px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 600;
}


.btn:hover {

    color: white;

    border-color: #454b68;
}


.btn-primary {

    background:
        linear-gradient(
            135deg,
            #6357ff,
            #7456e9
        );

    border-color: transparent;

    color: white;

    box-shadow:
        0 7px 20px rgba(92, 77, 255, 0.2);
}


/* =========================================================
   STAT CARDS
========================================================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 13px;
}


.card {

    background:
        linear-gradient(
            145deg,
            #111321,
            #0f111d
        );

    border: 1px solid #202337;

    border-radius: 11px;

    box-shadow:
        0 8px 25px rgba(0,0,0,0.12);
}


.stat-card {

    min-height: 130px;

    padding: 17px;

    position: relative;

    overflow: hidden;
}


.stat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.stat-label {

    color: #8a8fa3;

    font-size: 11px;

    font-weight: 600;
}


.stat-icon {

    width: 28px;
    height: 28px;

    border-radius: 7px;

    background: #181b2b;

    color: #7f73ff;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 13px;
}


.stat-value {

    font-size: 26px;

    font-weight: 750;

    margin-top: 14px;

    letter-spacing: -1px;
}


.stat-bottom {

    display: flex;

    align-items: center;

    gap: 6px;

    margin-top: 7px;

    font-size: 10px;

    color: #6d7288;
}


.stat-accent {

    color: #45d5ae;

    font-weight: 700;
}


/* =========================================================
   TWO COLUMN
========================================================= */

.analytics-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.55fr)
        minmax(300px, 1fr);

    gap: 13px;

    margin-bottom: 13px;
}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 17px 18px 10px;
}


.card-title {

    font-size: 13px;

    font-weight: 700;
}


.card-subtitle {

    color: #686d82;

    font-size: 9px;

    margin-top: 3px;
}


.card-menu {

    color: #666b82;

    font-size: 16px;
}


/* =========================================================
   PERFORMANCE CHART
========================================================= */

.chart-card {

    min-height: 295px;

    overflow: hidden;
}


.chart-area {

    height: 225px;

    padding: 14px 18px 12px;

    position: relative;
}


.chart-grid {

    position: absolute;

    left: 50px;
    right: 17px;
    top: 20px;
    bottom: 35px;

    display: flex;

    flex-direction: column;

    justify-content: space-between;
}


.grid-line {

    height: 1px;

    background: #1c2030;
}


.bars {

    position: absolute;

    left: 58px;
    right: 20px;
    bottom: 35px;
    top: 30px;

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 8px;
}


.bar {

    width: 100%;

    max-width: 30px;

    border-radius: 5px 5px 2px 2px;

    background:
        linear-gradient(
            180deg,
            #393b67,
            #20233d
        );

    position: relative;
}


.bar.highlight {

    background:
        linear-gradient(
            180deg,
            #766cff,
            #4b43c9
        );

    box-shadow:
        0 0 18px rgba(105, 92, 255, 0.18);
}


.months {

    position: absolute;

    left: 58px;
    right: 20px;
    bottom: 9px;

    display: flex;

    justify-content: space-between;

    color: #555b72;

    font-size: 8px;
}


.chart-tooltip {

    position: absolute;

    left: 54%;

    top: 36px;

    background: #191c2c;

    border: 1px solid #2b3045;

    border-radius: 7px;

    padding: 9px 11px;

    font-size: 9px;

    box-shadow:
        0 10px 25px rgba(0,0,0,0.3);
}


.tooltip-label {

    color: #747a91;

    margin-bottom: 4px;
}


.tooltip-value {

    font-weight: 800;

    font-size: 12px;
}


/* =========================================================
   EXAM STATUS
========================================================= */

.exam-card {

    min-height: 295px;

    overflow: hidden;
}


.exam-content {

    padding: 10px 18px 18px;
}


.exam-status {

    padding: 11px;

    border-radius: 8px;

    background: #171a28;

    border: 1px solid #24283b;

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 13px;
}


.exam-name {

    font-size: 12px;

    font-weight: 700;

    max-width: 65%;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.status-pill {

    padding: 4px 8px;

    border-radius: 20px;

    font-size: 8px;

    font-weight: 800;

    text-transform: uppercase;
}


.status-active {

    background: rgba(45, 205, 154, 0.12);

    color: #45d5ae;
}


.status-waiting {

    background: rgba(242, 171, 70, 0.12);

    color: #eab05d;
}


.status-draft {

    background: #222537;

    color: #858a9d;
}


.status-completed {

    background: rgba(110, 105, 255, 0.12);

    color: #8980ff;
}


.exam-metrics {

    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 7px;

    margin-bottom: 16px;
}


.exam-metric {

    padding: 11px 8px;

    background: #141724;

    border-radius: 7px;

    text-align: center;
}


.exam-metric-value {

    font-size: 17px;

    font-weight: 800;
}


.exam-metric-label {

    color: #666b80;

    font-size: 8px;

    margin-top: 3px;
}


.progress-label {

    display: flex;

    justify-content: space-between;

    color: #74798e;

    font-size: 9px;

    margin-bottom: 6px;
}


.progress {

    height: 6px;

    background: #1d2130;

    border-radius: 20px;

    overflow: hidden;
}


.progress-fill {

    height: 100%;

    background:
        linear-gradient(
            90deg,
            #6156ff,
            #9873ff
        );

    border-radius: inherit;
}


.exam-buttons {

    display: flex;

    gap: 7px;

    margin-top: 15px;
}


.exam-buttons a {

    flex: 1;

    text-align: center;
}


/* =========================================================
   LOWER GRID
========================================================= */

.lower-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        minmax(300px, 0.72fr);

    gap: 13px;
}


/* =========================================================
   RECENT TESTS
========================================================= */

.table-card {

    overflow: hidden;
}


.table-wrapper {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 620px;
}


th {

    text-align: left;

    color: #5e6479;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.5px;

    padding: 10px 17px;

    border-bottom: 1px solid #202337;
}


td {

    padding: 12px 17px;

    border-bottom: 1px solid #181b29;

    font-size: 10px;

    color: #a9adbb;
}


td strong {

    color: #e4e5eb;

    font-size: 11px;
}


.test-title {

    max-width: 220px;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;
}


.small-status {

    display: inline-block;

    padding: 4px 7px;

    border-radius: 15px;

    font-size: 8px;

    font-weight: 800;
}


/* =========================================================
   ACTIVITY
========================================================= */

.activity-card {

    min-height: 340px;
}


.activity-list {

    padding: 7px 18px 14px;
}


.activity-item {

    display: flex;

    gap: 10px;

    padding: 12px 0;

    border-bottom: 1px solid #191c2b;
}


.activity-item:last-child {

    border-bottom: none;
}


.activity-avatar {

    width: 30px;
    height: 30px;

    border-radius: 50%;

    background: #191c30;

    color: #8177ff;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 10px;

    font-weight: 800;

    flex-shrink: 0;
}


.activity-main {

    min-width: 0;

    flex: 1;
}


.activity-name {

    font-size: 10px;

    font-weight: 700;

    color: #dddfe7;
}


.activity-description {

    color: #686d82;

    font-size: 9px;

    margin-top: 3px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.activity-score {

    color: #45d5ae;

    font-weight: 800;

    font-size: 11px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    padding: 45px 20px;

    text-align: center;

    color: #666b80;

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1150px) {

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .analytics-grid,
    .lower-grid {

        grid-template-columns: 1fr;
    }
}


@media (max-width: 800px) {

    .sidebar {

        width: 70px;

        padding: 18px 9px;
    }

    .brand {

        justify-content: center;

        padding-left: 0;
        padding-right: 0;
    }

    .brand-text,
    .nav-label,
    .nav a span:not(.nav-icon),
    .admin-info {

        display: none;
    }

    .nav a {

        justify-content: center;

        padding: 11px;
    }

    .sidebar-bottom {

        left: 9px;
        right: 9px;
    }

    .admin-mini {

        justify-content: center;
    }

    .main {

        margin-left: 70px;

        width: calc(100% - 70px);
    }

    .content {

        padding: 18px;
    }
}


@media (max-width: 560px) {

    .stats-grid {

        grid-template-columns: 1fr;
    }

    .page-heading {

        align-items: flex-start;

        flex-direction: column;

        gap: 14px;
    }

    .heading-actions {

        width: 100%;
    }

    .heading-actions .btn {

        flex: 1;

        text-align: center;
    }

    .topbar {

        padding: 0 15px;
    }

    .profile-text {

        display: none;
    }

    .content {

        padding: 13px;
    }
}

</style>

</head>


<body>


<div class="app">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


    <div class="brand">

        <div class="brand-logo">
            M
        </div>

        <div class="brand-text">

            <div class="brand-name">
                MODUS
            </div>

            <div class="brand-subtitle">
                CBT PLATFORM
            </div>

        </div>

    </div>


    <div class="nav-label">
        Main Menu
    </div>


    <nav class="nav">

        <a
            href="dashboard.php"
            class="active"
        >
            <span class="nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>


        <a href="students.php">

            <span class="nav-icon">♙</span>

            <span>Students</span>

        </a>


        <a href="import_students.php">

            <span class="nav-icon">⇧</span>

            <span>Import Students</span>

        </a>


        <a href="tests.php">

            <span class="nav-icon">▣</span>

            <span>Tests</span>

        </a>


        <a href="create_test.php">

            <span class="nav-icon">＋</span>

            <span>Create Test</span>

        </a>


        <a href="questions.php">

            <span class="nav-icon">☷</span>

            <span>Question Bank</span>

        </a>


        <a href="assign_students.php">

            <span class="nav-icon">⇄</span>

            <span>Assign Students</span>

        </a>


        <a href="exam_control.php">

            <span class="nav-icon">▶</span>

            <span>Exam Control</span>

        </a>


        <a href="results.php">

            <span class="nav-icon">▤</span>

            <span>Results</span>

        </a>

    </nav>


    <div class="sidebar-bottom">

        <div class="admin-mini">

            <div class="admin-avatar">

                <?= strtoupper(
                    substr($teacher_name, 0, 1)
                ) ?>

            </div>

            <div class="admin-info">

                <div class="admin-name">
                    <?= htmlspecialchars($teacher_name) ?>
                </div>

                <div class="admin-role">
                    Administrator
                </div>

            </div>

        </div>

    </div>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="breadcrumb">

            MODUS CBT
            &nbsp; / &nbsp;
            <strong>Dashboard</strong>

        </div>


        <div class="top-actions">

            <a
                href="exam_control.php"
                class="icon-button"
                title="Exam Control"
            >
                ▶
            </a>


            <a
                href="results.php"
                class="icon-button"
                title="Results"
            >
                ▤
            </a>


            <div class="profile">

                <div class="profile-avatar">

                    <?= strtoupper(
                        substr($teacher_name, 0, 1)
                    ) ?>

                </div>

                <div class="profile-text">

                    <div class="profile-name">

                        <?= htmlspecialchars($teacher_name) ?>

                    </div>

                    <div class="profile-role">

                        Administrator

                    </div>

                </div>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <!-- PAGE TITLE -->

        <div class="page-heading">

            <div>

                <h1>
                    Dashboard
                </h1>

                <p>
                    Monitor your MODUS CBT examination system
                </p>

            </div>


            <div class="heading-actions">

                <a
                    href="create_test.php"
                    class="btn"
                >
                    + Create Test
                </a>

                <a
                    href="exam_control.php"
                    class="btn btn-primary"
                >
                    Exam Control
                </a>

            </div>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <div class="stats-grid">


            <div class="card stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Active Students
                    </div>

                    <div class="stat-icon">
                        ♙
                    </div>

                </div>

                <div class="stat-value">
                    <?= number_format($active_students) ?>
                </div>

                <div class="stat-bottom">

                    <span class="stat-accent">
                        ● Active
                    </span>

                    <span>
                        registered students
                    </span>

                </div>

            </div>


            <div class="card stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Total Tests
                    </div>

                    <div class="stat-icon">
                        ▣
                    </div>

                </div>

                <div class="stat-value">
                    <?= number_format($total_tests) ?>
                </div>

                <div class="stat-bottom">

                    <span class="stat-accent">
                        <?= $test_status['completed'] ?>
                    </span>

                    <span>
                        completed
                    </span>

                </div>

            </div>


            <div class="card stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Question Bank
                    </div>

                    <div class="stat-icon">
                        ☷
                    </div>

                </div>

                <div class="stat-value">
                    <?= number_format($total_questions) ?>
                </div>

                <div class="stat-bottom">

                    <span class="stat-accent">
                        Questions
                    </span>

                    <span>
                        available
                    </span>

                </div>

            </div>


            <div class="card stat-card">

                <div class="stat-top">

                    <div class="stat-label">
                        Submissions
                    </div>

                    <div class="stat-icon">
                        ✓
                    </div>

                </div>

                <div class="stat-value">
                    <?= number_format($total_submissions) ?>
                </div>

                <div class="stat-bottom">

                    <span class="stat-accent">
                        <?= $submission_rate ?>%
                    </span>

                    <span>
                        submission rate
                    </span>

                </div>

            </div>


        </div>


        <!-- =================================================
             ANALYTICS
        ================================================= -->

        <div class="analytics-grid">


            <!-- PERFORMANCE CHART -->

            <div class="card chart-card">

                <div class="card-header">

                    <div>

                        <div class="card-title">
                            Examination Activity
                        </div>

                        <div class="card-subtitle">
                            Test and submission overview
                        </div>

                    </div>

                    <div class="card-menu">
                        ···
                    </div>

                </div>


                <div class="chart-area">

                    <div class="chart-grid">

                        <div class="grid-line"></div>
                        <div class="grid-line"></div>
                        <div class="grid-line"></div>
                        <div class="grid-line"></div>
                        <div class="grid-line"></div>

                    </div>


                    <?php

                    /*
                     * Simple visual distribution.
                     * Values are generated from dashboard totals.
                     */

                    $chart_values = [
                        max(8, min(100, $total_tests * 7)),
                        max(12, min(100, $total_submissions * 3)),
                        max(10, min(100, $total_questions / 5)),
                        max(15, min(100, $active_students / 2)),
                        max(20, min(100, $total_assignments / 2)),
                        max(25, min(100, $total_submissions * 4)),
                        max(15, min(100, $total_tests * 10)),
                        max(18, min(100, $total_questions / 4)),
                        max(25, min(100, $total_students ?? $active_students)),
                        max(20, min(100, $total_submissions * 5))
                    ];

                    ?>


                    <div class="bars">

                        <?php foreach (
                            $chart_values
                            as $i => $value
                        ): ?>

                            <div
                                class="bar <?= $i === 5 ? 'highlight' : '' ?>"
                                style="height: <?= (int)$value ?>%;"
                            ></div>

                        <?php endforeach; ?>

                    </div>


                    <div class="months">

                        <span>1</span>
                        <span>2</span>
                        <span>3</span>
                        <span>4</span>
                        <span>5</span>
                        <span>6</span>
                        <span>7</span>
                        <span>8</span>
                        <span>9</span>
                        <span>10</span>

                    </div>


                    <div class="chart-tooltip">

                        <div class="tooltip-label">
                            Total submissions
                        </div>

                        <div class="tooltip-value">
                            <?= number_format($total_submissions) ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- CURRENT EXAM -->

            <div class="card exam-card">

                <div class="card-header">

                    <div>

                        <div class="card-title">
                            Current Examination
                        </div>

                        <div class="card-subtitle">
                            Live examination status
                        </div>

                    </div>

                    <div class="card-menu">
                        ···
                    </div>

                </div>


                <?php if ($current_exam): ?>

                    <div class="exam-content">


                        <div class="exam-status">

                            <div class="exam-name">

                                <?= htmlspecialchars(
                                    $current_exam['title']
                                ) ?>

                            </div>


                            <span
                                class="status-pill
                                <?= $current_exam['status'] === 'active'
                                    ? 'status-active'
                                    : 'status-waiting'
                                ?>"
                            >

                                <?= htmlspecialchars(
                                    $current_exam['status']
                                ) ?>

                            </span>

                        </div>


                        <div class="exam-metrics">


                            <div class="exam-metric">

                                <div class="exam-metric-value">

                                    <?= (int)
                                        $current_exam_stats['assigned']
                                    ?>

                                </div>

                                <div class="exam-metric-label">
                                    Assigned
                                </div>

                            </div>


                            <div class="exam-metric">

                                <div class="exam-metric-value">

                                    <?= (int)
                                        $current_exam_stats['started']
                                    ?>

                                </div>

                                <div class="exam-metric-label">
                                    Started
                                </div>

                            </div>


                            <div class="exam-metric">

                                <div class="exam-metric-value">

                                    <?= (int)
                                        $current_exam_stats['submitted']
                                    ?>

                                </div>

                                <div class="exam-metric-label">
                                    Submitted
                                </div>

                            </div>


                        </div>


                        <?php

                        $assigned =
                            (int)$current_exam_stats['assigned'];

                        $submitted =
                            (int)$current_exam_stats['submitted'];

                        $current_progress =
                            $assigned > 0
                                ? min(
                                    100,
                                    round(
                                        ($submitted / $assigned) * 100
                                    )
                                )
                                : 0;

                        ?>


                        <div class="progress-label">

                            <span>
                                Submission progress
                            </span>

                            <span>
                                <?= $current_progress ?>%
                            </span>

                        </div>


                        <div class="progress">

                            <div
                                class="progress-fill"
                                style="width: <?= $current_progress ?>%;"
                            ></div>

                        </div>


                        <div class="exam-buttons">

                            <a
                                href="exam_control.php"
                                class="btn btn-primary"
                            >
                                Control Exam
                            </a>

                            <a
                                href="results.php?test_id=<?= (int)$current_exam['id'] ?>"
                                class="btn"
                            >
                                Results
                            </a>

                        </div>


                    </div>

                <?php else: ?>

                    <div class="empty">

                        <div style="font-size:25px;margin-bottom:8px;">
                            ◌
                        </div>

                        No active or waiting examination.

                        <br><br>

                        <a
                            href="create_test.php"
                            class="btn btn-primary"
                        >
                            Create Test
                        </a>

                    </div>

                <?php endif; ?>

            </div>


        </div>


        <!-- =================================================
             LOWER SECTION
        ================================================= -->

        <div class="lower-grid">


            <!-- RECENT TESTS -->

            <div class="card table-card">

                <div class="card-header">

                    <div>

                        <div class="card-title">
                            Recent Tests
                        </div>

                        <div class="card-subtitle">
                            Latest examinations created in MODUS
                        </div>

                    </div>


                    <a
                        href="tests.php"
                        class="btn"
                        style="padding:6px 9px;"
                    >
                        View All
                    </a>

                </div>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Test
                                </th>

                                <th>
                                    Questions
                                </th>

                                <th>
                                    Duration
                                </th>

                                <th>
                                    Submissions
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php if ($recent_tests): ?>

                            <?php foreach (
                                $recent_tests
                                as $test
                            ): ?>

                                <tr>

                                    <td>

                                        <div class="test-title">

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $test['title']
                                                ) ?>

                                            </strong>

                                        </div>

                                    </td>


                                    <td>

                                        <?= (int)
                                            $test['question_count']
                                        ?>

                                    </td>


                                    <td>

                                        <?= (int)
                                            $test['duration_minutes']
                                        ?>
                                        min

                                    </td>


                                    <td>

                                        <?= (int)
                                            $test['submissions']
                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        $status_class =
                                            'status-draft';

                                        if (
                                            $test['status']
                                            === 'active'
                                        ) {
                                            $status_class =
                                                'status-active';

                                        } elseif (
                                            $test['status']
                                            === 'waiting'
                                        ) {
                                            $status_class =
                                                'status-waiting';

                                        } elseif (
                                            $test['status']
                                            === 'completed'
                                        ) {
                                            $status_class =
                                                'status-completed';
                                        }

                                        ?>

                                        <span
                                            class="small-status
                                            <?= $status_class ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $test['status']
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="5"
                                    style="text-align:center;padding:35px;"
                                >

                                    No tests created yet.

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- RECENT ACTIVITY -->

            <div class="card activity-card">

                <div class="card-header">

                    <div>

                        <div class="card-title">
                            Recent Activity
                        </div>

                        <div class="card-subtitle">
                            Latest student submissions
                        </div>

                    </div>

                </div>


                <div class="activity-list">

                    <?php if ($recent_activity): ?>

                        <?php foreach (
                            $recent_activity
                            as $activity
                        ): ?>

                            <div class="activity-item">


                                <div class="activity-avatar">

                                    <?= strtoupper(
                                        substr(
                                            $activity['name'],
                                            0,
                                            1
                                        )
                                    ) ?>

                                </div>


                                <div class="activity-main">

                                    <div class="activity-name">

                                        <?= htmlspecialchars(
                                            $activity['name']
                                        ) ?>

                                    </div>

                                    <div class="activity-description">

                                        Submitted
                                        <?= htmlspecialchars(
                                            $activity['title']
                                        ) ?>

                                    </div>

                                </div>


                                <div class="activity-score">

                                    <?= number_format(
                                        (float)$activity['score'],
                                        1
                                    ) ?>

                                </div>


                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty">

                            No student submissions yet.

                        </div>

                    <?php endif; ?>

                </div>

            </div>


        </div>


    </section>

</main>

</div>


</body>

</html>