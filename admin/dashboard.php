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
   RESET & BASE (OneSignal Dark Theme Refactor)
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;

    min-height: 100vh;

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background: #111318;

    color: #e2e8f0;
}


/* =========================================================
   APP CONTAINER
========================================================= */

.app {
    min-height: 100vh;
    display: flex;
    background: #111318;
}


/* =========================================================
   SIDEBAR (Clean Dark Variant)
========================================================= */

.sidebar {
    width: 240px;
    min-height: 100vh;
    background: #181b22;
    border-right: 1px solid #272b35;
    padding: 20px 14px;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 20;
    display: flex;
    flex-direction: column;
}


.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 8px 24px;
}


.brand-logo {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 800;
    font-size: 16px;
}


.brand-text {
    display: flex;
    flex-direction: column;
}


.brand-name {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.3px;
    color: #f8fafc;
}


.brand-subtitle {
    font-size: 9px;
    color: #64748b;
    margin-top: 1px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}


/* =========================================================
   NAVIGATION
========================================================= */

.nav-label {
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    padding: 0 10px 8px;
}


.nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}


.nav a {
    text-decoration: none;
    color: #94a3b8;
    padding: 9px 10px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.15s ease;
}


.nav a:hover {
    color: #f8fafc;
    background: #212630;
}


.nav a.active {
    color: #ffffff;
    background: #4f46e5;
    font-weight: 600;
}


.nav-icon {
    width: 18px;
    text-align: center;
    font-size: 14px;
}


/* =========================================================
   SIDEBAR BOTTOM
========================================================= */

.sidebar-bottom {
    border-top: 1px solid #272b35;
    padding-top: 12px;
    margin-top: auto;
}


.admin-mini {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 6px 8px;
}


.admin-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: white;
}


.admin-info {
    overflow: hidden;
}


.admin-name {
    font-size: 12px;
    font-weight: 600;
    color: #f8fafc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}


.admin-role {
    font-size: 10px;
    color: #64748b;
    margin-top: 1px;
}


/* =========================================================
   MAIN LAYOUT & TOPBAR
========================================================= */

.main {
    margin-left: 240px;
    width: calc(100% - 240px);
    min-height: 100vh;
    background: #111318;
}


.topbar {
    height: 60px;
    border-bottom: 1px solid #272b35;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    background: #181b22;
    position: sticky;
    top: 0;
    z-index: 10;
}


.breadcrumb {
    color: #64748b;
    font-size: 12px;
}


.breadcrumb strong {
    color: #f8fafc;
    font-weight: 600;
}


.top-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}


.icon-button {
    width: 32px;
    height: 32px;
    border: 1px solid #272b35;
    background: #181b22;
    color: #94a3b8;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 13px;
    transition: 0.15s;
}


.icon-button:hover {
    color: white;
    background: #212630;
    border-color: #3b4252;
}


.profile {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: 6px;
    padding-left: 12px;
    border-left: 1px solid #272b35;
}


.profile-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: white;
}


.profile-text {
    line-height: 13px;
}


.profile-name {
    font-size: 11px;
    font-weight: 600;
    color: #f8fafc;
}


.profile-role {
    color: #64748b;
    font-size: 9px;
}


/* =========================================================
   CONTENT AREA
========================================================= */

.content {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}


.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 20px;
}


.page-heading h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #f8fafc;
    letter-spacing: -0.4px;
}


.page-heading p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 12px;
}


.heading-actions {
    display: flex;
    gap: 8px;
}


.btn {
    border: 1px solid #272b35;
    background: #181b22;
    color: #cbd5e1;
    border-radius: 6px;
    padding: 7px 12px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: 0.15s;
}


.btn:hover {
    color: white;
    background: #212630;
    border-color: #3b4252;
}


.btn-primary {
    background: #4f46e5;
    border-color: #4f46e5;
    color: white;
    font-weight: 600;
}


.btn-primary:hover {
    background: #4338ca;
    border-color: #4338ca;
}


/* =========================================================
   CARDS & STAT CARDS (OneSignal Metric Style)
========================================================= */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}


.card {
    background: #181b22;
    border: 1px solid #272b35;
    border-radius: 8px;
}


.stat-card {
    padding: 16px;
    position: relative;
    overflow: hidden;
}


.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}


.stat-label {
    color: #94a3b8;
    font-size: 11px;
    font-weight: 500;
}


.stat-icon {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #212630;
    color: #818cf8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}


.stat-value {
    font-size: 24px;
    font-weight: 700;
    margin-top: 10px;
    color: #f8fafc;
    letter-spacing: -0.5px;
}


.stat-bottom {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    font-size: 11px;
    color: #64748b;
}


.stat-accent {
    color: #34d399;
    font-weight: 600;
}


/* =========================================================
   ANALYTICS GRID (Two Column Split)
========================================================= */

.analytics-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(320px, 1fr);
    gap: 12px;
    margin-bottom: 12px;
}


.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #272b35;
}


.card-title {
    font-size: 13px;
    font-weight: 600;
    color: #f8fafc;
}


.card-subtitle {
    color: #64748b;
    font-size: 11px;
    margin-top: 2px;
}


.card-menu {
    color: #64748b;
    font-size: 14px;
    cursor: pointer;
}


/* =========================================================
   PERFORMANCE CHART
========================================================= */

.chart-card {
    display: flex;
    flex-direction: column;
}


.chart-area {
    height: 230px;
    padding: 16px;
    position: relative;
    flex: 1;
}


.chart-grid {
    position: absolute;
    left: 45px;
    right: 16px;
    top: 16px;
    bottom: 32px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}


.grid-line {
    height: 1px;
    background: #212630;
}


.bars {
    position: absolute;
    left: 55px;
    right: 16px;
    bottom: 32px;
    top: 20px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 8px;
}


.bar {
    width: 100%;
    max-width: 28px;
    border-radius: 3px 3px 0 0;
    background: #272b35;
}


.bar.highlight {
    background: #4f46e5;
}


.months {
    position: absolute;
    left: 55px;
    right: 16px;
    bottom: 8px;
    display: flex;
    justify-content: space-between;
    color: #64748b;
    font-size: 10px;
}


.chart-tooltip {
    position: absolute;
    right: 30px;
    top: 24px;
    background: #212630;
    border: 1px solid #3b4252;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 10px;
}


.tooltip-label {
    color: #94a3b8;
    margin-bottom: 2px;
}


.tooltip-value {
    font-weight: 700;
    font-size: 11px;
    color: #f8fafc;
}


/* =========================================================
   EXAM STATUS PANEL
========================================================= */

.exam-card {
    display: flex;
    flex-direction: column;
}


.exam-content {
    padding: 16px;
}


.exam-status {
    padding: 10px 12px;
    border-radius: 6px;
    background: #212630;
    border: 1px solid #272b35;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}


.exam-name {
    font-size: 12px;
    font-weight: 600;
    color: #f8fafc;
    max-width: 65%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}


.status-pill {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}


.status-active {
    background: rgba(52, 211, 153, 0.12);
    color: #34d399;
}


.status-waiting {
    background: rgba(251, 191, 36, 0.12);
    color: #fbbf24;
}


.status-draft {
    background: #272b35;
    color: #94a3b8;
}


.status-completed {
    background: rgba(129, 140, 248, 0.12);
    color: #818cf8;
}


.exam-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 14px;
}


.exam-metric {
    padding: 10px 6px;
    background: #212630;
    border-radius: 6px;
    text-align: center;
    border: 1px solid #272b35;
}


.exam-metric-value {
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
}


.exam-metric-label {
    color: #64748b;
    font-size: 10px;
    margin-top: 2px;
}


.progress-label {
    display: flex;
    justify-content: space-between;
    color: #94a3b8;
    font-size: 11px;
    margin-bottom: 6px;
}


.progress {
    height: 6px;
    background: #212630;
    border-radius: 3px;
    overflow: hidden;
}


.progress-fill {
    height: 100%;
    background: #4f46e5;
    border-radius: inherit;
}


.exam-buttons {
    display: flex;
    gap: 8px;
    margin-top: 16px;
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
    grid-template-columns: minmax(0, 1fr) minmax(320px, 0.7fr);
    gap: 12px;
}


/* =========================================================
   RECENT TESTS TABLE
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
    min-width: 580px;
}


th {
    text-align: left;
    color: #64748b;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 16px;
    border-bottom: 1px solid #272b35;
    background: #181b22;
}


td {
    padding: 11px 16px;
    border-bottom: 1px solid #212630;
    font-size: 12px;
    color: #94a3b8;
}


td strong {
    color: #f8fafc;
    font-weight: 500;
}


.test-title {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}


.small-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}


/* =========================================================
   ACTIVITY LIST
========================================================= */

.activity-card {
    display: flex;
    flex-direction: column;
}


.activity-list {
    padding: 8px 16px;
}


.activity-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #212630;
}


.activity-item:last-child {
    border-bottom: none;
}


.activity-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #212630;
    color: #818cf8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    flex-shrink: 0;
}


.activity-main {
    min-width: 0;
    flex: 1;
}


.activity-name {
    font-size: 11px;
    font-weight: 600;
    color: #f8fafc;
}


.activity-description {
    color: #64748b;
    font-size: 10px;
    margin-top: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}


.activity-score {
    color: #34d399;
    font-weight: 700;
    font-size: 12px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {
    padding: 40px 20px;
    text-align: center;
    color: #64748b;
    font-size: 12px;
}


/* =========================================================
   RESPONSIVE DESIGN
========================================================= */

@media (max-width: 1150px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .analytics-grid,
    .lower-grid {
        grid-template-columns: 1fr;
    }
}


@media (max-width: 800px) {
    .sidebar {
        width: 64px;
        padding: 16px 8px;
    }

    .brand {
        justify-content: center;
        padding: 0 0 16px 0;
    }

    .brand-text,
    .nav-label,
    .nav a span:not(.nav-icon),
    .admin-info {
        display: none;
    }

    .nav a {
        justify-content: center;
        padding: 10px;
    }

    .sidebar-bottom {
        padding-top: 8px;
    }

    .admin-mini {
        justify-content: center;
    }

    .main {
        margin-left: 64px;
        width: calc(100% - 64px);
    }

    .content {
        padding: 16px;
    }
}


@media (max-width: 560px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }

    .heading-actions {
        width: 100%;
    }

    .heading-actions .btn {
        flex: 1;
        text-align: center;
    }

    .topbar {
        padding: 0 16px;
    }

    .profile-text {
        display: none;
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

                        <div style="font-size:22px;margin-bottom:6px;">
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
                        style="padding:5px 10px;"
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