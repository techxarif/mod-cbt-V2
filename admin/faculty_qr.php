<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();

if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    die('Invalid test ID.');
}

$test_id = (int)$_GET['test_id'];

/*
|--------------------------------------------------------------------------
| Get Test
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die('Test not found.');
}

/*
|--------------------------------------------------------------------------
| Only completed tests can be exported
|--------------------------------------------------------------------------
*/

if ($test['status'] !== 'completed') {
    die('Faculty QR can only be generated after the test is completed.');
}

/*
|--------------------------------------------------------------------------
| Get submitted students
|--------------------------------------------------------------------------
*/

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

        s.id AS modus_student_id,
        s.uid,
        s.name

    FROM student_tests st

    INNER JOIN students s
        ON s.id = st.student_id

    WHERE st.test_id = ?
      AND st.status = 'submitted'

    ORDER BY
        st.score DESC,
        st.submitted_at ASC,
        s.uid ASC
");

$stmt->execute([$test_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Create compact Faculty import package
|--------------------------------------------------------------------------
*/

$export_id = 'CBT-' . $test_id . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

$student_data = [];

foreach ($students as $student) {
    $student_data[] = [
        'modus_student_id' => (int) $student['modus_student_id'],
        'uid'              => $student['uid'],
        'name'             => $student['name'],
        'marks'            => (float) $student['score'],
        'correct'          => (int) $student['correct_answers'],
        'wrong'            => (int) $student['wrong_answers'],
        'unanswered'       => (int) $student['unanswered'],
        'submitted_at'     => $student['submitted_at']
    ];
}

/*
|--------------------------------------------------------------------------
| QR Payload
|--------------------------------------------------------------------------
*/

$payload = [
    'type'      => 'MODUS_CBT_RESULT',
    'version'   => '1.0',
    'export_id' => $export_id,

    'test' => [
        'modus_test_id'    => (int) $test['id'],
        'title'            => $test['title'],
        'duration_minutes' => (int) $test['duration_minutes'],
        'total_marks'      => (float) $test['total_marks'],
        'negative_marks'   => (float) $test['negative_marks'],
        'completed_at'     => $test['end_time'] ?: date('Y-m-d H:i:s')
    ],

    'students' => $student_data
];

/*
|--------------------------------------------------------------------------
| Convert JSON Text
|--------------------------------------------------------------------------
*/

$json = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    die('Unable to create Faculty result package.');
}

/*
|--------------------------------------------------------------------------
| Direct QR Link Generator
|--------------------------------------------------------------------------
*/

$qr_url = "https://quickchart.io/qr?text=" . urlencode($json) . "&size=420";

$qr_warning = strlen($json) > 2000;

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
        Faculty QR — <?= htmlspecialchars($test['title']) ?>
    </title>

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
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            color: #eef2ff;

            background:
                radial-gradient(
                    circle at 15% 15%,
                    rgba(37, 99, 235, 0.16),
                    transparent 34%
                ),
                radial-gradient(
                    circle at 85% 20%,
                    rgba(124, 58, 237, 0.17),
                    transparent 35%
                ),
                radial-gradient(
                    circle at 50% 100%,
                    rgba(14, 165, 233, 0.08),
                    transparent 40%
                ),
                #050812;

            overflow-x: hidden;
        }

        /* =========================================================
           AMBIENT BACKGROUND
        ========================================================= */

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
            z-index: -1;
            opacity: 0.18;
            animation: drift 12s ease-in-out infinite alternate;
        }

        body::before {
            background: #2563eb;
            top: -220px;
            left: -180px;
        }

        body::after {
            background: #7c3aed;
            right: -200px;
            bottom: -220px;
            animation-delay: -5s;
        }

        @keyframes drift {
            from {
                transform: translate3d(0, 0, 0) scale(1);
            }

            to {
                transform: translate3d(35px, 25px, 0) scale(1.08);
            }
        }

        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;

            height: 76px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 34px;

            background: rgba(5, 8, 18, 0.72);
            border-bottom: 1px solid rgba(255,255,255,0.07);

            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 38px;
            height: 38px;

            display: grid;
            place-items: center;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #7c3aed
                );

            box-shadow:
                0 0 25px rgba(59,130,246,0.25);

            font-weight: 900;
            font-size: 17px;
            letter-spacing: -1px;
        }

        .brand-text {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .brand-sub {
            font-size: 10px;
            color: #64748b;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .top-status {
            display: flex;
            align-items: center;
            gap: 8px;

            padding: 9px 13px;

            border-radius: 999px;

            background: rgba(16,185,129,0.07);
            border: 1px solid rgba(16,185,129,0.16);

            color: #86efac;

            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 12px rgba(34,197,94,0.8);
        }

        /* =========================================================
           PAGE
        ========================================================= */

        .page {
            width: min(1180px, calc(100% - 40px));
            margin: 0 auto;
            padding: 55px 0 70px;
        }

        /* =========================================================
           HEADER
        ========================================================= */

        .eyebrow {
            display: flex;
            align-items: center;
            gap: 9px;

            color: #60a5fa;

            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;

            margin-bottom: 14px;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #3b82f6;

            box-shadow:
                0 0 0 5px rgba(59,130,246,0.08),
                0 0 15px rgba(59,130,246,0.8);

            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.55;
                transform: scale(0.78);
            }
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 25px;

            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;

            font-size: clamp(34px, 5vw, 58px);
            line-height: 0.98;

            letter-spacing: -0.055em;
            font-weight: 850;

            color: #f8fafc;
        }

        .page-header p {
            margin: 13px 0 0;

            color: #7f8da5;

            font-size: 14px;
            line-height: 1.6;
        }

        .completed-badge {
            flex-shrink: 0;

            padding: 11px 15px;

            border-radius: 12px;

            background: rgba(16,185,129,0.08);
            border: 1px solid rgba(16,185,129,0.2);

            color: #6ee7b7;

            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        /* =========================================================
           MAIN GRID
        ========================================================= */

        .main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 430px;
            gap: 22px;
            align-items: stretch;
        }

        /* =========================================================
           GLASS CARD
        ========================================================= */

        .card {
            position: relative;

            background:
                linear-gradient(
                    145deg,
                    rgba(16,24,40,0.82),
                    rgba(8,13,25,0.82)
                );

            border: 1px solid rgba(148,163,184,0.11);

            border-radius: 24px;

            box-shadow:
                0 30px 80px rgba(0,0,0,0.25),
                inset 0 1px 0 rgba(255,255,255,0.025);

            overflow: hidden;

            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0;

            background:
                linear-gradient(
                    135deg,
                    rgba(59,130,246,0.045),
                    transparent 35%,
                    rgba(124,58,237,0.04)
                );

            pointer-events: none;
        }

        .card-content {
            position: relative;
            z-index: 1;
        }

        /* =========================================================
           TEST DETAILS
        ========================================================= */

        .details-card {
            padding: 30px;
        }

        .section-label {
            color: #64748b;

            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.17em;
            text-transform: uppercase;

            margin-bottom: 9px;
        }

        .test-title {
            font-size: 28px;
            line-height: 1.15;

            font-weight: 800;
            letter-spacing: -0.035em;

            color: #f8fafc;

            margin-bottom: 25px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .meta-box {
            min-height: 100px;

            padding: 18px;

            border-radius: 16px;

            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(255,255,255,0.06);

            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                transform 0.2s ease;
        }

        .meta-box:hover {
            transform: translateY(-2px);

            background: rgba(255,255,255,0.04);
            border-color: rgba(96,165,250,0.16);
        }

        .meta-icon {
            width: 30px;
            height: 30px;

            display: grid;
            place-items: center;

            margin-bottom: 13px;

            border-radius: 9px;

            background: rgba(59,130,246,0.09);
            color: #60a5fa;

            font-size: 13px;
            font-weight: 900;
        }

        .meta-label {
            color: #64748b;

            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .meta-value {
            margin-top: 5px;

            color: #e2e8f0;

            font-size: 16px;
            font-weight: 750;
        }

        /* =========================================================
           EXPORT INFO
        ========================================================= */

        .export-box {
            margin-top: 14px;
            padding: 17px 18px;

            border-radius: 15px;

            background: rgba(124,58,237,0.045);
            border: 1px solid rgba(124,58,237,0.12);
        }

        .export-label {
            color: #8b5cf6;

            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;

            margin-bottom: 7px;
        }

        .export-id {
            color: #a5b4fc;

            font-family:
                "SFMono-Regular",
                Consolas,
                "Liberation Mono",
                monospace;

            font-size: 12px;

            word-break: break-all;
        }

        /* =========================================================
           QR CARD
        ========================================================= */

        .qr-card {
            padding: 24px;

            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .qr-heading {
            width: 100%;
            margin-bottom: 18px;
        }

        .qr-heading h2 {
            margin: 0;

            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.02em;

            color: #f1f5f9;
        }

        .qr-heading p {
            margin: 5px 0 0;

            color: #64748b;

            font-size: 12px;
            line-height: 1.5;
        }

        .qr-shell {
            width: min(100%, 350px);
            aspect-ratio: 1;

            display: grid;
            place-items: center;

            padding: 18px;

            border-radius: 24px;

            background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #f1f5f9
                );

            box-shadow:
                0 25px 60px rgba(0,0,0,0.35),
                0 0 0 1px rgba(255,255,255,0.12);

            position: relative;
        }

        .qr-shell::before {
            content: "";

            position: absolute;
            inset: -1px;

            border-radius: 25px;

            background:
                linear-gradient(
                    135deg,
                    rgba(59,130,246,0.5),
                    transparent 35%,
                    rgba(124,58,237,0.5)
                );

            z-index: -1;

            filter: blur(12px);
            opacity: 0.5;
        }

        #qrImage {
            width: 100%;
            height: 100%;

            object-fit: contain;

            border-radius: 8px;

            image-rendering: pixelated;
        }

        .scan-hint {
            margin-top: 17px;

            display: flex;
            align-items: center;
            gap: 8px;

            color: #94a3b8;

            font-size: 11px;
            font-weight: 600;
        }

        .scan-icon {
            width: 8px;
            height: 8px;

            border-radius: 2px;

            border: 1px solid #60a5fa;
        }

        /* =========================================================
           WARNING
        ========================================================= */

        .warning {
            position: relative;
            z-index: 1;

            margin-top: 22px;

            padding: 15px 17px;

            border-radius: 14px;

            background: rgba(245,158,11,0.07);
            border: 1px solid rgba(245,158,11,0.18);

            color: #fbbf24;

            font-size: 12px;
            line-height: 1.6;
        }

        .warning strong {
            color: #fde68a;
        }

        /* =========================================================
           ACTIONS
        ========================================================= */

        .actions-card {
            margin-top: 22px;
            padding: 22px 24px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;

            background:
                linear-gradient(
                    135deg,
                    rgba(15,23,42,0.9),
                    rgba(8,13,25,0.9)
                );
        }

        .action-info {
            min-width: 0;
        }

        .action-info-title {
            color: #e2e8f0;

            font-size: 14px;
            font-weight: 750;
        }

        .action-info-sub {
            margin-top: 4px;

            color: #64748b;

            font-size: 11px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            position: relative;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            min-height: 43px;

            padding: 0 16px;

            border-radius: 11px;

            border: 1px solid rgba(255,255,255,0.08);

            color: #e2e8f0;

            font-size: 12px;
            font-weight: 750;

            text-decoration: none;

            cursor: pointer;

            overflow: hidden;

            transition:
                transform 0.2s ease,
                border-color 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            border-color: rgba(96,165,250,0.35);

            color: white;

            box-shadow:
                0 10px 25px rgba(37,99,235,0.2);
        }

        .btn-primary::after {
            content: "";

            position: absolute;

            top: 0;
            left: -120%;

            width: 70%;
            height: 100%;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255,255,255,0.16),
                    transparent
                );

            transform: skewX(-20deg);

            transition: left 0.6s ease;
        }

        .btn-primary:hover::after {
            left: 150%;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.035);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(148,163,184,0.2);
        }

        /* =========================================================
           FOOTER
        ========================================================= */

        .footer {
            text-align: center;

            margin-top: 28px;

            color: #475569;

            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 900px) {

            .main-grid {
                grid-template-columns: 1fr;
            }

            .qr-card {
                min-height: auto;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .actions-card {
                align-items: flex-start;
                flex-direction: column;
            }

            .actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 620px) {

            .topbar {
                padding: 0 18px;
            }

            .top-status {
                display: none;
            }

            .page {
                width: min(100% - 24px, 1180px);
                padding-top: 34px;
            }

            .page-header h1 {
                font-size: 39px;
            }

            .details-card {
                padding: 21px;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .qr-card {
                padding: 18px;
            }

            .actions {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .qr-shell {
                width: min(100%, 310px);
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
                scroll-behavior: auto !important;
            }
        }

        /* =========================================================
           PRINT
        ========================================================= */

        @media print {

            body {
                background: white !important;
                color: #111827 !important;
            }

            body::before,
            body::after,
            .topbar,
            .page-header,
            .actions-card,
            .footer,
            .warning,
            .scan-hint {
                display: none !important;
            }

            .page {
                width: 100%;
                margin: 0;
                padding: 0;
            }

            .main-grid {
                display: block;
            }

            .card {
                border: none;
                box-shadow: none;
                background: white;
            }

            .details-card {
                display: none;
            }

            .qr-card {
                padding: 0;
                min-height: 100vh;

                justify-content: center;
            }

            .qr-heading {
                text-align: center;
                color: #111827;
            }

            .qr-heading h2 {
                color: #111827;
            }

            .qr-heading p {
                color: #4b5563;
            }

            .qr-shell {
                width: 420px;
                height: 420px;

                box-shadow: none;
                border: 1px solid #ddd;
            }

            .export-id {
                color: #111827 !important;
            }
        }

    </style>
</head>

<body>

<!-- =========================================================
     TOPBAR
========================================================= -->

<header class="topbar">

    <div class="brand">

        <div class="brand-mark">
            M
        </div>

        <div>
            <div class="brand-text">
                MODUS
            </div>

            <div class="brand-sub">
                CBT Platform
            </div>
        </div>

    </div>

    <div class="top-status">
        <span class="status-dot"></span>
        Export Ready
    </div>

</header>


<!-- =========================================================
     PAGE
========================================================= -->

<main class="page">

    <div class="eyebrow">
        <span class="eyebrow-dot"></span>
        Faculty Integration
    </div>


    <div class="page-header">

        <div>

            <h1>
                Faculty QR
            </h1>

            <p>
                Import completed CBT results directly into
                Faculty by Modus.
            </p>

        </div>

        <div class="completed-badge">
            ● Test Completed
        </div>

    </div>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <div class="main-grid">


        <!-- =================================================
             TEST INFORMATION
        ================================================== -->

        <section class="card details-card">

            <div class="card-content">

                <div class="section-label">
                    Examination
                </div>

                <div class="test-title">
                    <?= htmlspecialchars($test['title']) ?>
                </div>


                <div class="meta-grid">

                    <div class="meta-box">

                        <div class="meta-icon">
                            ⏱
                        </div>

                        <div class="meta-label">
                            Duration
                        </div>

                        <div class="meta-value">
                            <?= (int) $test['duration_minutes'] ?> min
                        </div>

                    </div>


                    <div class="meta-box">

                        <div class="meta-icon">
                            ◈
                        </div>

                        <div class="meta-label">
                            Total Marks
                        </div>

                        <div class="meta-value">
                            <?= htmlspecialchars($test['total_marks']) ?>
                        </div>

                    </div>


                    <div class="meta-box">

                        <div class="meta-icon">
                            −
                        </div>

                        <div class="meta-label">
                            Negative Marks
                        </div>

                        <div class="meta-value">
                            <?= htmlspecialchars($test['negative_marks']) ?>
                        </div>

                    </div>


                    <div class="meta-box">

                        <div class="meta-icon">
                            #
                        </div>

                        <div class="meta-label">
                            Test ID
                        </div>

                        <div class="meta-value">
                            #<?= (int) $test_id ?>
                        </div>

                    </div>


                    <div class="meta-box">

                        <div class="meta-icon">
                            ◎
                        </div>

                        <div class="meta-label">
                            Submitted Students
                        </div>

                        <div class="meta-value">
                            <?= count($students) ?>
                        </div>

                    </div>


                    <div class="meta-box">

                        <div class="meta-icon">
                            ✓
                        </div>

                        <div class="meta-label">
                            Export Status
                        </div>

                        <div class="meta-value">
                            Ready
                        </div>

                    </div>

                </div>


                <div class="export-box">

                    <div class="export-label">
                        Export ID
                    </div>

                    <div class="export-id">
                        <?= htmlspecialchars($export_id) ?>
                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================
             QR CODE
        ================================================== -->

        <section class="card qr-card">

            <div class="card-content" style="width:100%;">

                <div class="qr-heading">

                    <h2>
                        Scan to Import
                    </h2>

                    <p>
                        Open Faculty by Modus and scan this QR
                        to import the completed result package.
                    </p>

                </div>


                <div class="qr-shell">

                    <img
                        id="qrImage"
                        src="<?= htmlspecialchars($qr_url) ?>"
                        alt="Faculty Import QR"
                    >

                </div>


                <div class="scan-hint">

                    <span class="scan-icon"></span>

                    Keep the QR fully visible while scanning

                </div>

            </div>

        </section>

    </div>


    <!-- =====================================================
         WARNING
    ====================================================== -->

    <?php if ($qr_warning): ?>

        <div class="warning">

            <strong>Large QR payload detected.</strong>

            <br>

            This result package contains a large amount of
            student data. For reliable scanning, use a bright
            display or printed QR with sufficient resolution.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ACTION BAR
    ====================================================== -->

    <section class="card actions-card">

        <div class="action-info">

            <div class="action-info-title">
                Faculty Result Package
            </div>

            <div class="action-info-sub">
                Export ID <?= htmlspecialchars($export_id) ?>
            </div>

        </div>


        <div class="actions">

            <a
                href="<?= htmlspecialchars($qr_url) ?>"
                target="_blank"
                class="btn btn-secondary"
            >
                ↗ Open QR
            </a>


            <button
                type="button"
                class="btn btn-primary"
                onclick="downloadQR()"
            >
                ↓ Download PNG
            </button>


            <button
                type="button"
                class="btn btn-secondary"
                onclick="window.print()"
            >
                ⎙ Print
            </button>

        </div>

    </section>


    <div class="footer">
        MODUS CBT · Faculty Integration · Secure Result Transfer
    </div>

</main>


<script>

const qrImageSrc = <?= json_encode($qr_url) ?>;


/* =========================================================
   DOWNLOAD QR
========================================================= */

function downloadQR() {

    const img = document.getElementById('qrImage');

    const filename =
        <?= json_encode(
            'MODUS_CBT_' .
            preg_replace(
                '/[^A-Za-z0-9_-]/',
                '_',
                $test['title']
            ) .
            '_Faculty_QR.png'
        ) ?>;


    const canvas = document.createElement('canvas');

    const ctx = canvas.getContext('2d');


    const width =
        img.naturalWidth || 420;

    const height =
        img.naturalHeight || 420;


    canvas.width = width;
    canvas.height = height;


    ctx.fillStyle = '#ffffff';

    ctx.fillRect(
        0,
        0,
        width,
        height
    );


    const tempImg = new Image();

    tempImg.crossOrigin = 'anonymous';


    tempImg.onload = function () {

        ctx.drawImage(
            tempImg,
            0,
            0,
            width,
            height
        );


        try {

            const pngUrl =
                canvas.toDataURL('image/png');


            const link =
                document.createElement('a');

            link.href = pngUrl;
            link.download = filename;

            document.body.appendChild(link);

            link.click();

            document.body.removeChild(link);

        } catch (error) {

            /*
             * If QuickChart blocks canvas export because
             * of CORS, open the QR directly instead.
             */

            window.open(
                qrImageSrc,
                '_blank'
            );

        }

    };


    tempImg.onerror = function () {

        window.open(
            qrImageSrc,
            '_blank'
        );

    };


    tempImg.src = qrImageSrc;

}

</script>

</body>
</html>