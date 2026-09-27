<?php
// results/restart_test.php — Restart a completed test (wipe results, keep students & questions)
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Manual CSRF check — csrf_verify() calls die() with HTML which breaks our JSON response
$submitted_csrf = $_POST['_csrf'] ?? '';
if (!hash_equals(csrf_token(), $submitted_csrf)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please refresh and try again.']);
    exit;
}

$test_id = (int)($_POST['test_id'] ?? 0);
$confirm = trim($_POST['confirm'] ?? '');

if ($test_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid test ID.']);
    exit;
}

if ($confirm !== 'RESTART') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Confirmation word incorrect.']);
    exit;
}

$test = get_test($test_id);
if (!$test) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Test not found.']);
    exit;
}

global $pdo;

try {
    $pdo->beginTransaction();

    // Get all student_test IDs for this test
    $stmt = $pdo->prepare("SELECT id FROM student_tests WHERE test_id = ?");
    $stmt->execute([$test_id]);
    $studentTestIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($studentTestIds)) {
        $placeholders = implode(',', array_fill(0, count($studentTestIds), '?'));

        // Delete all submitted answers
        $pdo->prepare("DELETE FROM answers WHERE student_test_id IN ($placeholders)")
            ->execute($studentTestIds);

        // Delete all exam sessions
        $pdo->prepare("DELETE FROM exam_sessions WHERE student_test_id IN ($placeholders)")
            ->execute($studentTestIds);

        // Reset student_tests back to 'assigned' — keep the row so assignment is preserved
        $pdo->prepare("
            UPDATE student_tests
            SET status = 'assigned',
                started_at = NULL,
                submitted_at = NULL,
                score = 0.00,
                correct_answers = 0,
                wrong_answers = 0,
                unanswered = 0
            WHERE test_id = ?
        ")->execute([$test_id]);
    }

    // Reset the test itself to 'waiting'
    $pdo->prepare("UPDATE tests SET status = 'waiting' WHERE id = ?")
        ->execute([$test_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Test restarted. All results cleared.']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('error', 'Restart test error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
exit;
