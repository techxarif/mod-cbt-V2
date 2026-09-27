<?php
// answers/load.php — Load Student Saved Answers API
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!studentLoggedIn()) {
    json_response(['success' => false, 'message' => 'Login required.'], 401);
}

$student_id = (int)$_SESSION['student_id'];
$test_id    = int_param('test_id', 0);

if ($test_id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid test ID.'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT a.question_id, a.selected_option
        FROM answers a
        INNER JOIN student_tests st ON st.id = a.student_test_id
        WHERE st.student_id = ? AND st.test_id = ?
    ");
    $stmt->execute([$student_id, $test_id]);
    $answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    json_response([
        'success' => true,
        'test_id' => $test_id,
        'answers' => $answers ?: (object)[]
    ]);
} catch (Throwable $e) {
    $ref = app_log('error', 'Load answers error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error. Ref: ' . $ref], 500);
}
