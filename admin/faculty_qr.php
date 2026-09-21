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

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    die('Unable to create Faculty result package.');
}

/*
|--------------------------------------------------------------------------
| Direct QR Link Generator with Raw JSON inside text param
|--------------------------------------------------------------------------
*/

$qr_url = "https://quickchart.io/qr?text=" . urlencode($json) . "&size=420";
$qr_warning = strlen($json) > 2000;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty QR - <?= htmlspecialchars($test['title']) ?></title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
        }

        .header p {
            margin-top: 8px;
            color: #6b7280;
        }

        .test-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }

        .info-box {
            background: #f9fafb;
            border-radius: 12px;
            padding: 15px;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .info-value {
            margin-top: 5px;
            font-size: 17px;
            font-weight: 700;
        }

        .qr-area {
            text-align: center;
            padding: 25px;
        }

        #qrcode {
            display: inline-block;
            padding: 18px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
        }

        .export-id {
            margin-top: 20px;
            font-family: monospace;
            font-size: 14px;
            color: #374151;
            word-break: break-all;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        button, .btn-link {
            border: none;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .download {
            background: #111827;
            color: white;
        }

        .print {
            background: #e5e7eb;
            color: #111827;
        }

        .warning {
            margin-top: 20px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            padding: 15px;
            border-radius: 10px;
        }

        @media (max-width: 700px) {
            .test-info {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                background: white;
            }

            .container {
                margin: 0;
                max-width: none;
            }

            .card {
                box-shadow: none;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h1>MODUS CBT</h1>
            <p>Faculty Result Import QR</p>
        </div>

        <div class="test-info">
            <div class="info-box">
                <div class="info-label">Test</div>
                <div class="info-value"><?= htmlspecialchars($test['title']) ?></div>
            </div>

            <div class="info-box">
                <div class="info-label">Students</div>
                <div class="info-value"><?= count($students) ?></div>
            </div>

            <div class="info-box">
                <div class="info-label">Test ID</div>
                <div class="info-value"><?= (int) $test_id ?></div>
            </div>
        </div>

        <div class="qr-area">
            <div id="qrcode">
                <img id="qrImage" src="<?= htmlspecialchars($qr_url) ?>" alt="Faculty Import QR" style="width:420px;max-width:100%;height:auto;">
            </div>

            <div class="export-id">
                Export ID: <strong><?= htmlspecialchars($export_id) ?></strong>
            </div>
        </div>

        <?php if ($qr_warning): ?>
            <div class="warning">
                <strong>QR data is large.</strong><br>
                This test contains a large number of student responses. Ensure sufficient lighting and high scanner resolution when scanning.
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="<?= htmlspecialchars($qr_url) ?>" target="_blank" class="btn-link download">
                Open Direct QR Link
            </a>

            <button class="download" onclick="downloadQR()">
                Download QR (PNG)
            </button>

            <button class="print" onclick="window.print()">
                Print QR
            </button>
        </div>
    </div>
</div>

<script>
const qrImageSrc = <?= json_encode($qr_url) ?>;

function downloadQR() {
    const img = document.getElementById('qrImage');
    const filename = <?= json_encode('MODUS_CBT_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $test['title']) . '_Faculty_QR.png') ?>;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    const width = img.naturalWidth || 420;
    const height = img.naturalHeight || 420;

    canvas.width = width;
    canvas.height = height;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);

    const tempImg = new Image();
    tempImg.crossOrigin = 'anonymous';

    tempImg.onload = function () {
        ctx.drawImage(tempImg, 0, 0, width, height);
        const pngUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.href = pngUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    tempImg.src = qrImageSrc;
}
</script>

</body>
</html>