<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();

if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    http_response_code(400);
    exit('Invalid test ID.');
}

$test_id = (int) $_GET['test_id'];

/*
|--------------------------------------------------------------------------
| Test
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
        status
    FROM tests
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    http_response_code(404);
    exit('Test not found.');
}

if ($test['status'] !== 'completed') {
    http_response_code(400);
    exit('Test is not completed.');
}

/*
|--------------------------------------------------------------------------
| Submitted students
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        st.score,
        st.correct_answers,
        st.wrong_answers,
        st.unanswered,
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
| Export ID & Payload Building
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
| JSON String
|--------------------------------------------------------------------------
*/

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    http_response_code(500);
    exit('Unable to generate result data.');
}

/*
|--------------------------------------------------------------------------
| Redirect to QuickChart with Raw JSON Payload
|--------------------------------------------------------------------------
*/

$qr_url = "https://quickchart.io/qr?text=" . urlencode($json) . "&size=420&format=png";

header('Location: ' . $qr_url);
exit;