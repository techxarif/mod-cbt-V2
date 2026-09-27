<?php
// exam_control/stop.php — Stop / End Active Exam Endpoint
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_html(405, 'Method Not Allowed');
}

csrf_verify();

$test_id = int_param('test_id', 0, 'post');
if ($test_id <= 0) {
    redirect('index.php');
}

try {
    $stmt = $pdo->prepare("
        UPDATE tests
        SET status = 'completed', end_time = NOW()
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$test_id]);

    if (is_json_request()) {
        json_response(['success' => true, 'message' => 'Exam completed successfully.']);
    } else {
        redirect("index.php?test_id=$test_id");
    }
} catch (Throwable $e) {
    $ref = app_log('error', 'Stop exam error: ' . $e->getMessage(), ['test_id' => $test_id]);
    if (is_json_request()) {
        json_response(['success' => false, 'message' => 'Server error. Ref: ' . $ref], 500);
    }
    abort_html(500, 'Error Stopping Exam', 'Ref: ' . $ref);
}
