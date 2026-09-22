<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();


/*
|--------------------------------------------------------------------------
| Selected Test
|--------------------------------------------------------------------------
*/

$test_id = isset($_GET['test_id'])
    ? (int) $_GET['test_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Get All Tests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        status,
        duration_minutes,
        total_marks,
        negative_marks
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Selected Test Information
|--------------------------------------------------------------------------
*/

$selected_test = null;
$results = [];

if ($test_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            status,
            duration_minutes,
            total_marks,
            negative_marks,
            start_time,
            end_time
        FROM tests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$test_id]);

    $selected_test = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Get Student Results
    |--------------------------------------------------------------------------
    */

    if ($selected_test) {

        $stmt = $pdo->prepare("
            SELECT
                st.id AS student_test_id,
                st.status,
                st.score,
                st.correct_answers,
                st.wrong_answers,
                st.unanswered,
                st.started_at,
                st.submitted_at,

                s.uid,
                s.name,
                s.mobile,
                s.date_of_birth

            FROM student_tests st

            INNER JOIN students s
                ON s.id = st.student_id

            WHERE st.test_id = ?

            ORDER BY
                st.score DESC,
                st.submitted_at ASC,
                s.uid ASC
        ");

        $stmt->execute([$test_id]);

        $results = $stmt->fetchAll();
    }
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total_students = count($results);

$submitted = 0;
$absent = 0;
$highest_score = null;

foreach ($results as $result) {

    if ($result['status'] === 'submitted') {
        $submitted++;
    }

    if ($result['status'] === 'absent') {
        $absent++;
    }

    if (
        $highest_score === null ||
        (float) $result['score'] > $highest_score
    ) {
        $highest_score = (float) $result['score'];
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

<title>Results — MODUS CBT</title>


<style>

/* =========================================================
   MODUS RESULTS DASHBOARD
========================================================= */

:root {

    --bg: #050812;

    --panel: #0c1220;

    --panel-2: #101827;

    --border: rgba(148,163,184,.13);

    --text: #f1f5f9;

    --muted: #94a3b8;

    --blue: #60a5fa;

    --blue-strong: #2563eb;

    --purple: #a78bfa;

    --green: #4ade80;

    --yellow: #fbbf24;

    --red: #fb7185;
}


/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}


/* =========================================================
   BODY
========================================================= */

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
            circle at 8% 5%,
            rgba(37,99,235,.13),
            transparent 30%
        ),

        radial-gradient(
            circle at 92% 15%,
            rgba(124,58,237,.11),
            transparent 28%
        ),

        radial-gradient(
            circle at 50% 100%,
            rgba(37,99,235,.06),
            transparent 35%
        ),

        #050812;
}


/* =========================================================
   BACKGROUND GRID
========================================================= */

body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    opacity: .20;

    background-image:

        linear-gradient(
            rgba(255,255,255,.018) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(255,255,255,.018) 1px,
            transparent 1px
        );

    background-size: 45px 45px;

    mask-image:
        linear-gradient(
            to bottom,
            black,
            transparent 85%
        );
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    position: sticky;

    top: 0;

    z-index: 50;

    min-height: 68px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 0 28px;

    background:
        rgba(5,8,18,.90);

    border-bottom:
        1px solid rgba(148,163,184,.10);

    backdrop-filter: blur(18px);

    -webkit-backdrop-filter: blur(18px);

    box-shadow:
        0 10px 35px rgba(0,0,0,.20);
}


.brand {

    display: flex;

    align-items: center;

    gap: 11px;

    font-size: 18px;

    font-weight: 800;

    letter-spacing: -.3px;
}


.brand-mark {

    width: 32px;

    height: 32px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    color: white;

    font-size: 13px;

    box-shadow:
        0 8px 25px rgba(37,99,235,.25);
}


.topbar-right {

    color: #94a3b8;

    font-size: 13px;

    font-weight: 600;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    width: min(1280px, calc(100% - 32px));

    margin: 0 auto;

    padding: 38px 0 80px;

    position: relative;

    z-index: 1;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    margin-bottom: 25px;
}

.page-header h1 {

    margin: 0;

    font-size: 32px;

    line-height: 1.15;

    letter-spacing: -.8px;

    font-weight: 800;
}

.page-header p {

    margin: 9px 0 0;

    color: var(--muted);

    font-size: 14px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            145deg,
            rgba(15,23,42,.97),
            rgba(7,12,24,.98)
        );

    border:
        1px solid var(--border);

    border-radius: 20px;

    padding: 24px;

    margin-bottom: 20px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.35),
        inset 0 1px 0 rgba(255,255,255,.025);

    backdrop-filter: blur(16px);

    -webkit-backdrop-filter: blur(16px);
}


/* =========================================================
   TEST SELECTOR
========================================================= */

.selector-card::before {

    content: "";

    position: absolute;

    width: 280px;

    height: 180px;

    right: -100px;

    top: -100px;

    background:
        rgba(37,99,235,.10);

    filter: blur(55px);

    pointer-events: none;
}


.section-label {

    display: block;

    margin-bottom: 9px;

    color: #cbd5e1;

    font-size: 12px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .7px;
}


.test-selector {

    display: flex;

    align-items: center;

    gap: 10px;

    position: relative;

    z-index: 1;
}


select {

    flex: 1;

    min-width: 250px;

    height: 46px;

    padding: 0 14px;

    border-radius: 11px;

    border:
        1px solid rgba(148,163,184,.16);

    background: #080f1d;

    color: #f8fafc;

    font-size: 14px;

    outline: none;

    color-scheme: dark;
}


select:focus {

    border-color:
        rgba(96,165,250,.55);

    box-shadow:
        0 0 0 3px rgba(37,99,235,.10);
}


/* =========================================================
   BUTTONS
========================================================= */

.btn {

    min-height: 42px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding: 0 16px;

    border-radius: 11px;

    border: 1px solid transparent;

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;

    transition:
        transform .18s ease,
        background .18s ease,
        border-color .18s ease,
        box-shadow .18s ease;
}

.btn:hover {

    transform: translateY(-1px);
}


.btn-dark {

    color: white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    border-color:
        rgba(96,165,250,.35);

    box-shadow:
        0 10px 30px rgba(37,99,235,.20);
}

.btn-dark:hover {

    box-shadow:
        0 14px 35px rgba(37,99,235,.30);
}


.btn-pdf {

    background:
        rgba(15,23,42,.95);

    color: #dbeafe;

    border-color:
        rgba(96,165,250,.18);
}

.btn-pdf:hover {

    background: #18243a;

    border-color:
        rgba(96,165,250,.35);
}


.faculty-qr-btn {

    background:
        linear-gradient(
            135deg,
            rgba(124,58,237,.85),
            rgba(79,70,229,.85)
        );

    color: white;

    border-color:
        rgba(167,139,250,.30);

    box-shadow:
        0 10px 28px rgba(124,58,237,.16);
}


/* =========================================================
   TEST INFO
========================================================= */

.test-title-row {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 20px;
}


.test-title {

    margin: 0;

    font-size: 22px;

    font-weight: 750;

    letter-spacing: -.4px;
}


.test-status {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 7px 10px;

    border-radius: 999px;

    background:
        rgba(96,165,250,.08);

    border:
        1px solid rgba(96,165,250,.15);

    color: #93c5fd;

    font-size: 11px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .5px;
}


.status-dot {

    width: 6px;

    height: 6px;

    border-radius: 50%;

    background: #60a5fa;

    box-shadow:
        0 0 10px rgba(96,165,250,.70);
}


.test-info {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 10px;
}


.info-box {

    padding: 15px;

    border-radius: 13px;

    background:
        rgba(8,15,29,.88);

    border:
        1px solid rgba(148,163,184,.10);

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.02);
}


.info-label {

    margin-bottom: 7px;

    color: #64748b;

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .5px;
}


.info-value {

    color: #e2e8f0;

    font-size: 17px;

    font-weight: 750;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    align-items: center;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 18px;

    padding-top: 18px;

    border-top:
        1px solid rgba(148,163,184,.08);
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    margin-bottom: 20px;
}


.stat {

    position: relative;

    overflow: hidden;

    padding: 20px;

    border-radius: 17px;

    background:
        linear-gradient(
            145deg,
            rgba(15,23,42,.97),
            rgba(7,12,24,.98)
        );

    border:
        1px solid rgba(148,163,184,.12);

    box-shadow:
        0 20px 55px rgba(0,0,0,.25),
        inset 0 1px 0 rgba(255,255,255,.025);

    transition:
        transform .18s ease,
        border-color .18s ease;
}


.stat:hover {

    transform: translateY(-2px);

    border-color:
        rgba(96,165,250,.20);
}


.stat::after {

    content: "";

    position: absolute;

    width: 100px;

    height: 100px;

    right: -50px;

    top: -50px;

    background:
        rgba(37,99,235,.08);

    filter: blur(30px);

    pointer-events: none;
}


.stat-title {

    color: #64748b;

    font-size: 11px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .7px;
}


.stat-value {

    margin-top: 8px;

    color: #f8fafc;

    font-size: 28px;

    font-weight: 800;

    letter-spacing: -.6px;
}


/* =========================================================
   RESULTS CARD
========================================================= */

.results-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 18px;
}


.results-heading h2 {

    margin: 0;

    font-size: 19px;

    font-weight: 750;
}


.results-count {

    padding: 6px 10px;

    border-radius: 8px;

    background:
        rgba(148,163,184,.06);

    border:
        1px solid rgba(148,163,184,.10);

    color: #94a3b8;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;

    border:
        1px solid rgba(148,163,184,.10);

    border-radius: 15px;

    background: #080d18;
}


table {

    width: 100%;

    min-width: 1050px;

    border-collapse: collapse;
}


th {

    padding: 13px 14px;

    background:
        #0d1524;

    border-bottom:
        1px solid rgba(148,163,184,.11);

    color: #64748b;

    font-size: 10px;

    font-weight: 750;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .6px;

    white-space: nowrap;
}


td {

    padding: 14px;

    border-bottom:
        1px solid rgba(148,163,184,.07);

    color: #aebbd0;

    font-size: 13px;

    white-space: nowrap;

    background: rgba(8,13,24,.78);
}


tbody tr {

    transition:
        background .15s ease;
}


tbody tr:hover td {

    background:
        rgba(30,41,59,.48);
}


tbody tr:last-child td {

    border-bottom: none;
}


/* =========================================================
   STUDENT
========================================================= */

.rank {

    color: #64748b;

    font-weight: 700;
}


.student-cell {

    display: flex;

    align-items: center;

    gap: 10px;
}


.student-avatar {

    width: 32px;

    height: 32px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.18),
            rgba(124,58,237,.18)
        );

    border:
        1px solid rgba(96,165,250,.15);

    color: #93c5fd;

    font-size: 11px;

    font-weight: 800;
}


.student-name {

    color: #f1f5f9;

    font-weight: 700;
}


.uid {

    color: #93c5fd;

    font-weight: 700;
}


.score {

    color: #f8fafc;

    font-size: 14px;

    font-weight: 800;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    padding: 5px 9px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .3px;
}


.status-submitted {

    background:
        rgba(74,222,128,.10);

    border:
        1px solid rgba(74,222,128,.18);

    color: #86efac;
}


.status-assigned {

    background:
        rgba(251,191,36,.10);

    border:
        1px solid rgba(251,191,36,.18);

    color: #fcd34d;
}


.status-started {

    background:
        rgba(96,165,250,.10);

    border:
        1px solid rgba(96,165,250,.18);

    color: #93c5fd;
}


.status-absent {

    background:
        rgba(251,113,133,.10);

    border:
        1px solid rgba(251,113,133,.18);

    color: #fda4af;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 65px 20px;

    text-align: center;

    color: #64748b;
}


.empty-icon {

    width: 50px;

    height: 50px;

    margin: 0 auto 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        rgba(37,99,235,.08);

    border:
        1px solid rgba(96,165,250,.12);

    color: #60a5fa;

    font-size: 20px;
}


.empty h3 {

    margin: 0 0 7px;

    color: #e2e8f0;

    font-size: 18px;
}


.empty p {

    margin: 0;

    color: #64748b;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .test-info {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 650px) {

    .topbar {

        padding: 0 17px;
    }

    .topbar-right {

        display: none;
    }

    .container {

        width: min(
            100% - 22px,
            1280px
        );

        padding-top: 25px;
    }

    .page-header h1 {

        font-size: 27px;
    }

    .test-selector {

        flex-direction: column;

        align-items: stretch;
    }

    select {

        min-width: 100%;

        width: 100%;
    }

    .test-selector .btn {

        width: 100%;
    }

    .test-info {

        grid-template-columns: 1fr;
    }

    .stats {

        grid-template-columns: 1fr 1fr;
    }

    .test-title-row {

        flex-direction: column;
    }

}


@media (max-width: 430px) {

    .stats {

        grid-template-columns: 1fr;
    }

    .card {

        padding: 17px;

        border-radius: 17px;
    }

    .actions {

        flex-direction: column;

        align-items: stretch;
    }

    .actions .btn {

        width: 100%;
    }

}


/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation: none !important;

        transition: none !important;
    }

}

</style>

</head>


<body>


<!-- =======================================================
     TOPBAR
======================================================== -->

<div class="topbar">

    <div class="brand">

        <div class="brand-mark">
            M
        </div>

        MODUS CBT

    </div>


    <div class="topbar-right">
        Results Dashboard
    </div>

</div>


<div class="container">


    <!-- ===================================================
         PAGE HEADER
    ==================================================== -->

    <div class="page-header">

        <h1>
            Results
        </h1>

        <p>
            Analyze student performance and export completed test results.
        </p>

    </div>


    <!-- ===================================================
         TEST SELECTOR
    ==================================================== -->

    <div class="card selector-card">

        <span class="section-label">
            Select Test
        </span>


        <form
            method="GET"
            class="test-selector"
        >

            <select
                name="test_id"
                required
            >

                <option value="">
                    Select a test
                </option>


                <?php foreach ($tests as $test): ?>

                    <option
                        value="<?= (int)$test['id'] ?>"
                        <?= ($test_id === (int)$test['id'])
                            ? 'selected'
                            : '' ?>
                    >

                        <?= htmlspecialchars($test['title']) ?>

                    </option>

                <?php endforeach; ?>


            </select>


            <button
                type="submit"
                class="btn btn-dark"
            >
                View Results
            </button>

        </form>

    </div>


    <?php if ($selected_test): ?>


        <!-- =================================================
             TEST INFORMATION
        ================================================== -->

        <div class="card">


            <div class="test-title-row">

                <h2 class="test-title">

                    <?= htmlspecialchars(
                        $selected_test['title']
                    ) ?>

                </h2>


                <div class="test-status">

                    <span class="status-dot"></span>

                    <?= htmlspecialchars(
                        ucfirst($selected_test['status'])
                    ) ?>

                </div>

            </div>


            <div class="test-info">


                <div class="info-box">

                    <div class="info-label">
                        Duration
                    </div>

                    <div class="info-value">

                        <?= (int)
                            $selected_test['duration_minutes']
                        ?>

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
                        Test Status
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            ucfirst(
                                $selected_test['status']
                            )
                        ) ?>

                    </div>

                </div>


            </div>


            <!-- ACTIONS -->

            <div class="actions">


                <a
                    href="result_pdf.php?test_id=<?= (int)$test_id ?>"
                    target="_blank"
                    class="btn btn-pdf"
                >
                    ↓ Download Result PDF
                </a>


                <?php if (
                    $test_id > 0 &&
                    $selected_test['status'] === 'completed'
                ): ?>

                    <a
                        href="faculty_qr.php?test_id=<?= (int)$test_id ?>"
                        class="btn faculty-qr-btn"
                        target="_blank"
                    >
                        ▣ Generate Faculty QR
                    </a>

                <?php endif; ?>


            </div>


        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats">


            <div class="stat">

                <div class="stat-title">
                    Students
                </div>

                <div class="stat-value">
                    <?= $total_students ?>
                </div>

            </div>


            <div class="stat">

                <div class="stat-title">
                    Submitted
                </div>

                <div class="stat-value">
                    <?= $submitted ?>
                </div>

            </div>


            <div class="stat">

                <div class="stat-title">
                    Absent
                </div>

                <div class="stat-value">
                    <?= $absent ?>
                </div>

            </div>


            <div class="stat">

                <div class="stat-title">
                    Highest Score
                </div>

                <div class="stat-value">

                    <?=
                        ($highest_score !== null)
                        ? number_format(
                            $highest_score,
                            2
                        )
                        : '-'
                    ?>

                </div>

            </div>


        </div>


        <!-- =================================================
             RESULTS TABLE
        ================================================== -->

        <div class="card">


            <div class="results-heading">

                <h2>
                    Student Results
                </h2>

                <div class="results-count">

                    <?= $total_students ?>

                    students

                </div>

            </div>


            <?php if (empty($results)): ?>


                <div class="empty">

                    <div class="empty-icon">
                        —
                    </div>

                    <h3>
                        No results yet
                    </h3>

                    <p>
                        No students are assigned to this test yet.
                    </p>

                </div>


            <?php else: ?>


                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Rank
                                </th>

                                <th>
                                    Student
                                </th>

                                <th>
                                    UID
                                </th>

                                <th>
                                    Mobile
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Correct
                                </th>

                                <th>
                                    Wrong
                                </th>

                                <th>
                                    Unanswered
                                </th>

                                <th>
                                    Score
                                </th>

                                <th>
                                    Submitted
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php

                            $rank = 1;

                            foreach ($results as $result):

                                $student_name =
                                    trim(
                                        $result['name']
                                    );

                                $avatar =
                                    strtoupper(
                                        substr(
                                            $student_name,
                                            0,
                                            1
                                        )
                                    );

                            ?>


                                <tr>


                                    <td class="rank">

                                        #<?= $rank ?>

                                    </td>


                                    <td>

                                        <div class="student-cell">

                                            <div class="student-avatar">

                                                <?= htmlspecialchars(
                                                    $avatar
                                                ) ?>

                                            </div>


                                            <div>

                                                <div class="student-name">

                                                    <?= htmlspecialchars(
                                                        $student_name
                                                    ) ?>

                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <td class="uid">

                                        <?= htmlspecialchars(
                                            $result['uid']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $result['mobile']
                                        ) ?>

                                    </td>


                                    <td>

                                        <span
                                            class="status status-<?= htmlspecialchars(
                                                $result['status']
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $result['status']
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= (int)
                                            $result['correct_answers']
                                        ?>

                                    </td>


                                    <td>

                                        <?= (int)
                                            $result['wrong_answers']
                                        ?>

                                    </td>


                                    <td>

                                        <?= (int)
                                            $result['unanswered']
                                        ?>

                                    </td>


                                    <td class="score">

                                        <?= number_format(
                                            (float)$result['score'],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?=
                                            !empty(
                                                $result['submitted_at']
                                            )
                                            ? htmlspecialchars(
                                                $result['submitted_at']
                                            )
                                            : '-'
                                        ?>

                                    </td>


                                </tr>


                            <?php

                                $rank++;

                            endforeach;

                            ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>


    <?php else: ?>


        <!-- =================================================
             NO TEST SELECTED
        ================================================== -->

        <div class="card">

            <div class="empty">

                <div class="empty-icon">
                    ↗
                </div>

                <h3>
                    Select a test
                </h3>

                <p>
                    Choose a test above to view its student results.
                </p>

            </div>

        </div>


    <?php endif; ?>


</div>


</body>

</html>