<?php
// qr/image.php — Secure Server-Side QR Image Stream
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

header('X-Robots-Tag: noindex, nofollow');

$test_id = int_param('test_id', 0);
if ($test_id <= 0) {
    abort_html(400, 'Invalid test ID');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test not found');
}

// Fetch results for this test
$results = get_test_results($test_id);
$student_data = [];

foreach ($results as $r) {
    if ($r['status'] === 'submitted') {
        $student_data[] = [
            'uid'     => $r['uid'],
            'name'    => $r['name'],
            'score'   => (float)$r['score'],
            'correct' => (int)($r['correct_answers'] ?? 0),
            'wrong'   => (int)($r['wrong_answers'] ?? 0),
        ];
    }
}

$payload = [
    'type'       => 'MODUS_CBT_FACULTY_RESULT',
    'test_id'    => (int)$test['id'],
    'title'      => $test['title'],
    'status'     => $test['status'],
    'students'   => $student_data,
    'generated'  => date('c')
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);

// 1. Try local QR generation using chillerlan
try {
    $qr = new QRCode();
    $dataUri = $qr->render($json);
    
    if (str_starts_with($dataUri, 'data:image/svg+xml;base64,')) {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo base64_decode(substr($dataUri, 26));
        exit;
    } elseif (str_starts_with($dataUri, 'data:image/png;base64,')) {
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo base64_decode(substr($dataUri, 22));
        exit;
    }
} catch (Throwable $e) {
    // If payload was large, try compact payload
    try {
        $compactPayload = [
            'type'    => 'MODUS_CBT_FACULTY_RESULT',
            'test_id' => (int)$test['id'],
            'title'   => $test['title'],
            'count'   => count($student_data),
            'summary' => array_map(fn($s) => [$s['uid'], $s['score']], $student_data)
        ];
        $qr = new QRCode();
        $dataUri = $qr->render(json_encode($compactPayload));
        if (str_starts_with($dataUri, 'data:image/svg+xml;base64,')) {
            header('Content-Type: image/svg+xml');
            echo base64_decode(substr($dataUri, 26));
            exit;
        } elseif (str_starts_with($dataUri, 'data:image/png;base64,')) {
            header('Content-Type: image/png');
            echo base64_decode(substr($dataUri, 22));
            exit;
        }
    } catch (Throwable $e2) {
        app_log('warning', 'Local QR generation fallback to server-side POST: ' . $e2->getMessage());
    }
}

// 2. Fallback: Server-side POST to QuickChart (never exposes PII in GET query string)
$postData = json_encode([
    'text'   => $json,
    'width'  => 360,
    'height' => 360
]);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAccept: image/png\r\n",
        'content' => $postData,
        'timeout' => 8
    ]
]);

$img = @file_get_contents('https://quickchart.io/qr', false, $ctx);
if ($img !== false) {
    header('Content-Type: image/png');
    echo $img;
    exit;
}

abort_html(500, 'QR Generation Failed');
