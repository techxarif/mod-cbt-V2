<?php
// tests/delete.php — Delete Test Endpoint
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
    $stmt = $pdo->prepare("DELETE FROM tests WHERE id = ?");
    $stmt->execute([$test_id]);
    redirect('index.php');
} catch (Throwable $e) {
    $ref = app_log('error', 'Delete test error: ' . $e->getMessage(), ['test_id' => $test_id]);
    abort_html(500, 'Delete Failed', 'Could not delete test. It may have existing references. Ref: ' . $ref);
}
