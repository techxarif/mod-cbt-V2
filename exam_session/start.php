<?php
// exam_session/start.php — Start Exam Attempt Endpoint
require_once __DIR__ . '/../includes/bootstrap.php';
requireStudent();

$student_id = (int)$_SESSION['student_id'];
$test_id    = int_param('test_id', 0);

if ($test_id <= 0) {
    redirect('index.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT st.id, st.status AS student_test_status, st.started_at,
               t.status AS test_status, t.duration_minutes
        FROM student_tests st
        INNER JOIN tests t ON t.id = st.test_id
        WHERE st.student_id = ? AND st.test_id = ?
        LIMIT 1
    ");
    $stmt->execute([$student_id, $test_id]);
    $st = $stmt->fetch();

    if (!$st || $st['test_status'] !== 'active') {
        redirect('index.php');
    }

    if ($st['student_test_status'] === 'submitted') {
        redirect("finish.php?test_id=$test_id");
    }

    // Set started_at once (P0 timer fix: never overwrite once set)
    if (empty($st['started_at'])) {
        $stmt = $pdo->prepare("
            UPDATE student_tests
            SET status = 'started', started_at = NOW()
            WHERE id = ? AND started_at IS NULL
        ");
        $stmt->execute([$st['id']]);
    }

    redirect("exam.php?test_id=$test_id");
} catch (Throwable $e) {
    $ref = app_log('error', 'Start attempt error: ' . $e->getMessage(), ['student_id' => $student_id, 'test_id' => $test_id]);
    abort_html(500, 'Unable to Start Exam', 'Ref: ' . $ref);
}
