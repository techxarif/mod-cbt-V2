<?php

// ============================================================
// MODUS CBT — Start Exam API
// Was: api/start_exam.php
// Now: exam_control/start.php
//
// Fixes applied:
//   - CSRF header verification (S1)
//   - Batch INSERT IGNORE instead of fetchAll+loop (D2)
//   - Error responses don't expose $e->getMessage() (E1)
//   - requireTeacher() was doing HTML redirect from JSON API — fixed (P2)
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// ----------------------------------------------------------
// Auth check — return JSON 401 (not HTML redirect)
// ----------------------------------------------------------

if (!teacherLoggedIn()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}

// ----------------------------------------------------------
// CSRF header verification (S1)
// ----------------------------------------------------------

csrf_header_verify();

// ----------------------------------------------------------
// Parse input
// ----------------------------------------------------------

$input = json_decode(file_get_contents('php://input'), true);

$test_id = isset($input['test_id'])
    ? (int) $input['test_id']
    : int_param('test_id', 0, 'post');

if ($test_id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid test ID.'], 400);
}

try {

    // ----------------------------------------------------------
    // Get and lock test
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT id, title, status, duration_minutes
        FROM tests
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    // Need a transaction to use FOR UPDATE
    $pdo->beginTransaction();
    $stmt->execute([$test_id]);
    $test = $stmt->fetch();

    if (!$test) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Test not found.'], 404);
    }

    if ($test['status'] !== 'waiting') {
        $pdo->rollBack();
        json_response([
            'success' => false,
            'message' => 'Test must be in WAITING status to start.',
        ], 409);
    }

    // ----------------------------------------------------------
    // D2: Batch INSERT IGNORE (replaces fetchAll+loop)
    // Assigns all active students in one query
    // ----------------------------------------------------------

    $pdo->prepare("
        INSERT IGNORE INTO student_tests (student_id, test_id, status)
        SELECT id, :tid, 'assigned'
        FROM students
        WHERE status = 'active'
    ")->execute([':tid' => $test_id]);

    // ----------------------------------------------------------
    // Activate the test
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        UPDATE tests
        SET
            status     = 'active',
            start_time = NOW()
        WHERE id     = ?
          AND status = 'waiting'
    ");

    $stmt->execute([$test_id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Unable to start the test.'], 409);
    }

    $pdo->commit();

    // ----------------------------------------------------------
    // Count assigned students
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_tests WHERE test_id = ?");
    $stmt->execute([$test_id]);
    $count = (int) $stmt->fetchColumn();

    json_response([
        'success'           => true,
        'message'           => 'Exam started successfully.',
        'test_id'           => $test_id,
        'title'             => $test['title'],
        'students_assigned' => $count,
        'start_time'        => date('Y-m-d H:i:s'),
    ]);


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $ref = app_log('error', 'start_exam error: ' . $e->getMessage(), ['test_id' => $test_id]);
    json_response(['success' => false, 'message' => 'Server error. Ref: ' . $ref], 500);
}
