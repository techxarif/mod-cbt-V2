<?php
// assignments/remove.php — Remove Student Assignment Endpoint
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_html(405, 'Method Not Allowed');
}

csrf_verify();

$assignment_id = int_param('assignment_id', 0, 'post');
$test_id       = int_param('test_id', 0, 'post');

if ($assignment_id <= 0) {
    redirect('index.php');
}

try {
    // Only remove if not submitted
    $stmt = $pdo->prepare("DELETE FROM student_tests WHERE id = ? AND status != 'submitted'");
    $stmt->execute([$assignment_id]);

    $redirect_url = $test_id > 0 ? "index.php?test_id=$test_id" : "index.php";
    redirect($redirect_url);
} catch (Throwable $e) {
    $ref = app_log('error', 'Remove assignment error: ' . $e->getMessage(), ['assignment_id' => $assignment_id]);
    abort_html(500, 'Removal Failed', 'Could not remove student assignment. Ref: ' . $ref);
}
