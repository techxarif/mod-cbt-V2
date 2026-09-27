<?php
// qr/export_modus.php — Download Two-Way Encrypted .modus Package for Faculty
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$test_id = int_param('test_id', 0);
if ($test_id <= 0) {
    abort_html(400, 'Invalid Request', 'Please specify a valid test ID.');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test Not Found', 'The requested examination was not found.');
}

// 1. Gather all candidate results
$results = get_test_results($test_id);
$stats = result_stats($results);

$student_data = [];
foreach ($results as $r) {
    $student_data[] = [
        'student_test_id' => (int)($r['student_test_id'] ?? 0),
        'uid'             => (string)$r['uid'],
        'name'            => (string)$r['name'],
        'mobile'          => (string)($r['mobile'] ?? ''),
        'date_of_birth'   => (string)($r['date_of_birth'] ?? ''),
        'status'          => (string)$r['status'],
        'score'           => (float)$r['score'],
        'correct'         => (int)($r['correct_answers'] ?? 0),
        'wrong'           => (int)($r['wrong_answers'] ?? 0),
        'unanswered'      => (int)($r['unanswered'] ?? 0),
        'started_at'      => (string)($r['started_at'] ?? ''),
        'submitted_at'    => (string)($r['submitted_at'] ?? ''),
    ];
}

// 2. Gather question structure & answer keys
$questions = get_test_questions($test_id, true);
$question_data = [];
foreach ($questions as $q) {
    $question_data[] = [
        'id'             => (int)$q['id'],
        'order'          => (int)($q['question_order'] ?? 0),
        'question_text'  => $q['question_text'],
        'question_image' => $q['question_image'],
        'option_a'       => $q['option_a'],
        'option_b'       => $q['option_b'],
        'option_c'       => $q['option_c'],
        'option_d'       => $q['option_d'],
        'correct_option' => $q['correct_option'],
        'marks'          => (float)($q['marks'] ?? MARKS_CORRECT),
        'negative_marks' => (float)($q['negative_marks'] ?? MARKS_WRONG),
    ];
}

// 3. Construct unified JSON payload
$payload = [
    'type'         => 'MODUS_CBT_FACULTY_PACKAGE',
    'app'          => defined('APP_NAME') ? APP_NAME : 'MODUS CBT',
    'version'      => '1.0',
    'salt_policy'  => 'MODUS CBT prefixed and suffixed',
    'generated_at' => date('c'),
    'test'         => [
        'id'               => (int)$test['id'],
        'title'            => $test['title'],
        'status'           => $test['status'],
        'duration_minutes' => (int)($test['duration_minutes'] ?? 0),
        'created_at'       => $test['created_at'] ?? null,
    ],
    'stats'        => [
        'total_candidates' => count($results),
        'submitted_count'  => (int)($stats['submitted'] ?? 0),
        'average_score'    => (float)($stats['average'] ?? 0),
        'highest_score'    => (float)($stats['highest'] ?? 0),
        'lowest_score'     => (float)($stats['lowest'] ?? 0),
    ],
    'students'     => $student_data,
    'questions'    => $question_data
];

// 4. Encrypt with fixed salt "MODUS CBT" at start & end via 2-way AES-256-CBC
$encryptedPackage = modus_encrypt_package($payload);

// 5. Stream as .modus download
$safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(trim($test['title'])));
$safeTitle = trim(substr($safeTitle, 0, 30), '_');
$filename  = 'modus_test_' . $test['id'] . ($safeTitle !== '' ? '_' . $safeTitle : '') . '.modus';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($encryptedPackage));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow');

echo $encryptedPackage;
exit;
