<?php
// assignments/assign.php — Assign Students Action Endpoint
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_html(405, 'Method Not Allowed');
}

csrf_verify();

$test_id = int_param('test_id', 0, 'post');
$mode    = str_param('mode', 'single', 'post');

if ($test_id <= 0) {
    redirect('index.php');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test Not Found');
}

try {
    if ($mode === 'all') {
        // High-performance batch INSERT IGNORE rewrite (D2)
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO student_tests (student_id, test_id, status)
            SELECT id, :tid, 'assigned'
            FROM students
            WHERE status = 'active'
        ");
        $stmt->execute([':tid' => $test_id]);
    } else {
        $student_id = int_param('student_id', 0, 'post');
        if ($student_id > 0) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO student_tests (student_id, test_id, status)
                VALUES (?, ?, 'assigned')
            ");
            $stmt->execute([$student_id, $test_id]);
        }
    }

    redirect("index.php?test_id=$test_id");
} catch (Throwable $e) {
    $ref = app_log('error', 'Assignment failed: ' . $e->getMessage(), ['test_id' => $test_id]);
    abort_html(500, 'Assignment Failed', 'Ref: ' . $ref);
}
