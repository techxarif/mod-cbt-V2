<?php

require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->query("
    SELECT
        id,
        title,
        duration_minutes,
        total_marks,
        negative_marks,
        start_time,
        end_time,
        status,
        created_at
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Tests - MODUS CBT</title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {

            margin: 0;

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
                    circle at 10% 5%,
                    rgba(37,99,235,.10),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(124,58,237,.08),
                    transparent 32%
                ),
                #050812;

            color: #e2e8f0;

            min-height: 100vh;
        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {

            position: sticky;

            top: 0;

            z-index: 100;

            height: 64px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 28px;

            background:
                rgba(5,8,18,.94);

            border-bottom:
                1px solid rgba(148,163,184,.10);

            box-shadow:
                0 10px 35px rgba(0,0,0,.25);

            backdrop-filter: blur(18px);

            -webkit-backdrop-filter: blur(18px);
        }


        .logo {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #f8fafc;

            font-size: 18px;

            font-weight: 850;

            letter-spacing: .5px;
        }


        .logo-mark {

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

            font-weight: 900;

            box-shadow:
                0 7px 22px rgba(37,99,235,.25);
        }


        .topbar-links {

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .topbar-links a {

            padding: 8px 11px;

            border-radius: 8px;

            color: #94a3b8;

            text-decoration: none;

            font-size: 12px;

            font-weight: 650;

            transition:
                color .18s ease,
                background .18s ease;
        }


        .topbar-links a:hover {

            color: #f1f5f9;

            background:
                rgba(148,163,184,.07);
        }


        .topbar-separator {

            color: #334155;

            font-size: 12px;
        }


        /* =====================================================
           PAGE
        ===================================================== */

        .container {

            width: min(1500px, calc(100% - 48px));

            margin: 0 auto;

            padding: 36px 0 60px;
        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .page-header {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 26px;
        }


        .page-title {

            margin: 0;

            color: #f8fafc;

            font-size: 32px;

            line-height: 1.1;

            font-weight: 850;

            letter-spacing: -.8px;
        }


        .page-subtitle {

            margin: 9px 0 0;

            color: #64748b;

            font-size: 13px;
        }


        .page-subtitle strong {

            color: #94a3b8;

            font-weight: 750;
        }


        /* =====================================================
           ACTION BUTTONS
        ===================================================== */

        .buttons {

            display: flex;

            align-items: center;

            gap: 9px;

            flex-wrap: wrap;
        }


        .button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-height: 42px;

            padding: 0 16px;

            border-radius: 10px;

            border: 1px solid transparent;

            text-decoration: none;

            font-size: 12px;

            font-weight: 750;

            transition:
                transform .18s ease,
                box-shadow .18s ease,
                border-color .18s ease;
        }


        .button:hover {

            transform: translateY(-1px);
        }


        .button.primary {

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            border-color:
                rgba(96,165,250,.30);

            color: #fff;

            box-shadow:
                0 10px 28px rgba(37,99,235,.20);
        }


        .button.primary:hover {

            box-shadow:
                0 14px 35px rgba(37,99,235,.30);
        }


        .button.secondary {

            background:
                rgba(15,23,42,.85);

            border-color:
                rgba(148,163,184,.13);

            color: #cbd5e1;
        }


        .button.secondary:hover {

            background:
                rgba(30,41,59,.90);

            border-color:
                rgba(148,163,184,.20);
        }


        /* =====================================================
           MAIN CARD
        ===================================================== */

        .tests-card {

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    145deg,
                    rgba(15,23,42,.97),
                    rgba(7,12,24,.98)
                );

            border:
                1px solid rgba(148,163,184,.13);

            border-radius: 20px;

            box-shadow:
                0 25px 70px rgba(0,0,0,.38),
                inset 0 1px 0 rgba(255,255,255,.025);

            backdrop-filter: blur(16px);

            -webkit-backdrop-filter: blur(16px);
        }


        .tests-card::before {

            content: "";

            position: absolute;

            width: 360px;

            height: 220px;

            right: -140px;

            top: -150px;

            background:
                rgba(37,99,235,.09);

            filter: blur(70px);

            pointer-events: none;
        }


        /* =====================================================
           CARD HEADER
        ===================================================== */

        .card-header {

            position: relative;

            z-index: 1;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding: 21px 23px;

            border-bottom:
                1px solid rgba(148,163,184,.09);
        }


        .card-heading {

            display: flex;

            align-items: center;

            gap: 11px;
        }


        .card-icon {

            width: 36px;

            height: 36px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    rgba(37,99,235,.17),
                    rgba(124,58,237,.17)
                );

            border:
                1px solid rgba(96,165,250,.15);

            color: #93c5fd;

            font-size: 14px;

            font-weight: 850;
        }


        .card-heading h2 {

            margin: 0;

            color: #e2e8f0;

            font-size: 16px;

            font-weight: 750;
        }


        .card-heading span {

            display: block;

            margin-top: 3px;

            color: #64748b;

            font-size: 11px;
        }


        .test-count {

            padding: 7px 11px;

            border-radius: 8px;

            background:
                rgba(148,163,184,.06);

            border:
                1px solid rgba(148,163,184,.10);

            color: #94a3b8;

            font-size: 11px;

            font-weight: 750;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-container {

            position: relative;

            z-index: 1;

            overflow-x: auto;
        }


        table {

            width: 100%;

            min-width: 1050px;

            border-collapse: collapse;
        }


        th {

            padding: 13px 18px;

            background:
                #0d1524;

            border-bottom:
                1px solid rgba(148,163,184,.10);

            color: #64748b;

            font-size: 10px;

            font-weight: 750;

            text-align: left;

            text-transform: uppercase;

            letter-spacing: .6px;

            white-space: nowrap;
        }


        td {

            padding: 15px 18px;

            background:
                rgba(8,13,24,.72);

            border-bottom:
                1px solid rgba(148,163,184,.07);

            color: #aebbd0;

            font-size: 13px;

            white-space: nowrap;
        }


        tbody tr {

            transition:
                background .15s ease;
        }


        tbody tr:hover td {

            background:
                rgba(30,41,59,.45);
        }


        tbody tr:last-child td {

            border-bottom: none;
        }


        /* =====================================================
           TEST ID
        ===================================================== */

        .test-id {

            color: #64748b;

            font-size: 11px;

            font-weight: 750;
        }


        /* =====================================================
           TEST NAME
        ===================================================== */

        .test-name-wrap {

            display: flex;

            align-items: center;

            gap: 11px;
        }


        .test-avatar {

            width: 34px;

            height: 34px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 9px;

            background:
                linear-gradient(
                    135deg,
                    rgba(37,99,235,.16),
                    rgba(124,58,237,.16)
                );

            border:
                1px solid rgba(96,165,250,.13);

            color: #93c5fd;

            font-size: 11px;

            font-weight: 850;
        }


        .test-name {

            color: #f1f5f9;

            font-size: 13px;

            font-weight: 750;
        }


        /* =====================================================
           METRICS
        ===================================================== */

        .metric {

            color: #cbd5e1;

            font-weight: 650;
        }


        .metric-highlight {

            color: #93c5fd;

            font-weight: 750;
        }


        .negative-mark {

            color: #fda4af;

            font-weight: 700;
        }


        /* =====================================================
           START TIME
        ===================================================== */

        .start-time {

            color: #94a3b8;

            font-size: 12px;
        }


        .not-set {

            color: #475569;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 5px 10px;

            border-radius: 999px;

            font-size: 10px;

            font-weight: 750;

            text-transform: uppercase;

            letter-spacing: .35px;
        }


        .status::before {

            content: "";

            width: 5px;

            height: 5px;

            flex-shrink: 0;

            border-radius: 50%;
        }


        .status.draft {

            background:
                rgba(148,163,184,.09);

            border:
                1px solid rgba(148,163,184,.14);

            color: #94a3b8;
        }


        .status.draft::before {

            background: #64748b;
        }


        .status.waiting {

            background:
                rgba(250,204,21,.09);

            border:
                1px solid rgba(250,204,21,.17);

            color: #fde68a;
        }


        .status.waiting::before {

            background: #facc15;

            box-shadow:
                0 0 8px rgba(250,204,21,.45);
        }


        .status.active {

            background:
                rgba(74,222,128,.09);

            border:
                1px solid rgba(74,222,128,.17);

            color: #86efac;
        }


        .status.active::before {

            background: #4ade80;

            box-shadow:
                0 0 9px rgba(74,222,128,.55);
        }


        .status.completed {

            background:
                rgba(96,165,250,.09);

            border:
                1px solid rgba(96,165,250,.17);

            color: #93c5fd;
        }


        .status.completed::before {

            background: #60a5fa;
        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty {

            padding: 75px 20px;

            text-align: center;
        }


        .empty-icon {

            width: 52px;

            height: 52px;

            margin: 0 auto 16px;

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

            font-weight: 800;
        }


        .empty h3 {

            margin: 0 0 7px;

            color: #e2e8f0;

            font-size: 18px;

            font-weight: 750;
        }


        .empty p {

            margin: 0;

            color: #64748b;

            font-size: 13px;
        }


        .empty-action {

            margin-top: 18px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 760px) {

            .topbar {

                padding: 0 16px;
            }


            .topbar-links a:first-child,
            .topbar-separator {

                display: none;
            }


            .container {

                width: min(
                    100% - 28px,
                    1500px
                );

                padding-top: 25px;
            }


            .page-header {

                align-items: stretch;

                flex-direction: column;
            }


            .page-title {

                font-size: 27px;
            }


            .buttons {

                width: 100%;
            }


            .button {

                flex: 1;
            }


            .card-header {

                align-items: flex-start;

                flex-direction: column;
            }

        }


        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                transition: none !important;

                animation: none !important;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     TOPBAR
========================================================= -->

<div class="topbar">


    <div class="logo">

        <div class="logo-mark">
            M
        </div>

        MODUS CBT

    </div>


    <div class="topbar-links">

        <a href="dashboard.php">
            Dashboard
        </a>

        <span class="topbar-separator">
            /
        </span>

        <a href="../logout.php">
            Logout
        </a>

    </div>


</div>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">


        <div>

            <h1 class="page-title">
                Tests
            </h1>

            <p class="page-subtitle">

                Manage CBT examinations and configurations.

                <strong>
                    <?= count($tests) ?>
                    total tests
                </strong>

            </p>

        </div>


        <div class="buttons">


            <a
                href="create_test.php"
                class="button primary"
            >
                + Create Test
            </a>


            <a
                href="dashboard.php"
                class="button secondary"
            >
                Dashboard
            </a>


        </div>


    </div>



    <!-- =====================================================
         TESTS CARD
    ====================================================== -->

    <div class="tests-card">


        <div class="card-header">


            <div class="card-heading">


                <div class="card-icon">
                    T
                </div>


                <div>

                    <h2>
                        Examination Library
                    </h2>

                    <span>
                        All created CBT tests
                    </span>

                </div>


            </div>


            <div class="test-count">

                <?= count($tests) ?>

                Tests

            </div>


        </div>



        <!-- =================================================
             TABLE
        ================================================== -->

        <div class="table-container">


            <?php if (count($tests) === 0): ?>


                <div class="empty">


                    <div class="empty-icon">
                        +
                    </div>


                    <h3>
                        No tests created yet
                    </h3>


                    <p>
                        Create your first CBT test to begin building an examination.
                    </p>


                    <div class="empty-action">

                        <a
                            href="create_test.php"
                            class="button primary"
                        >
                            + Create First Test
                        </a>

                    </div>


                </div>


            <?php else: ?>


                <table>


                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Test Name
                            </th>

                            <th>
                                Duration
                            </th>

                            <th>
                                Total Marks
                            </th>

                            <th>
                                Negative Marks
                            </th>

                            <th>
                                Start Time
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($tests as $test): ?>


                        <?php

                        $test_title =
                            trim($test['title']);

                        $test_initial =
                            strtoupper(
                                substr(
                                    $test_title,
                                    0,
                                    1
                                )
                            );

                        ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <span class="test-id">

                                    #
                                    <?= htmlspecialchars(
                                        $test['id']
                                    ) ?>

                                </span>

                            </td>


                            <!-- TEST NAME -->

                            <td>

                                <div class="test-name-wrap">


                                    <div class="test-avatar">

                                        <?= htmlspecialchars(
                                            $test_initial
                                        ) ?>

                                    </div>


                                    <div class="test-name">

                                        <?= htmlspecialchars(
                                            $test_title
                                        ) ?>

                                    </div>


                                </div>

                            </td>


                            <!-- DURATION -->

                            <td>

                                <span class="metric">

                                    <?= htmlspecialchars(
                                        $test['duration_minutes']
                                    ) ?>

                                    min

                                </span>

                            </td>


                            <!-- TOTAL MARKS -->

                            <td>

                                <span class="metric-highlight">

                                    <?= htmlspecialchars(
                                        $test['total_marks']
                                    ) ?>

                                </span>

                            </td>


                            <!-- NEGATIVE MARKS -->

                            <td>

                                <span class="negative-mark">

                                    -
                                    <?= htmlspecialchars(
                                        $test['negative_marks']
                                    ) ?>

                                </span>

                            </td>


                            <!-- START TIME -->

                            <td>

                                <?php if ($test['start_time']): ?>

                                    <span class="start-time">

                                        <?= htmlspecialchars(
                                            $test['start_time']
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="not-set">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= htmlspecialchars(
                                        $test['status']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $test['status']
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>