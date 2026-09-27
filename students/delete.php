<?php
// students/delete.php — Delete Student Endpoint
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_html(405, 'Method Not Allowed');
}

csrf_verify();

$student_id = int_param('student_id', 0, 'post');
if ($student_id <= 0) {
    redirect('index.php');
}

try {
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    redirect('index.php');
} catch (Throwable $e) {
    $ref = app_log('error', 'Delete student error: ' . $e->getMessage(), ['student_id' => $student_id]);
    abort_html(500, 'Delete Failed', 'Failed to delete student. They may have existing exam attempts. Ref: ' . $ref);
}
