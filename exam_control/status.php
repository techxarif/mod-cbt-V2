<?php

// ============================================================
// MODUS CBT — Exam Status API
// Was: api/exam_status.php
// Now: exam_control/status.php
//
// P1 fix: IDOR — now scopes to student-assigned tests only.
// Previously returned the global latest test for any student.
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!studentLoggedIn()) {
    json_response(['status' => 'login_required']);
}

$student_id = (int) $_SESSION['student_id'];

try {

    // ----------------------------------------------------------
    // P1 IDOR FIX:
    // Previously: SELECT FROM tests WHERE status IN ('waiting','active') ORDER BY id DESC LIMIT 1
    // That returned the latest test for ANY student, even non-assigned ones.
    //
    // Now: JOIN student_tests to scope to assigned tests only.
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.status
        FROM tests t
        INNER JOIN student_tests st
            ON st.test_id = t.id
        WHERE st.student_id = ?
          AND t.status IN ('waiting', 'active')
          AND st.status NOT IN ('submitted')
        ORDER BY t.id DESC
        LIMIT 1
    ");

    $stmt->execute([$student_id]);
    $test = $stmt->fetch();

    if (!$test) {
        json_response(['status' => 'waiting']);
    }

    json_response([
        'status'  => $test['status'],
        'test_id' => (int) $test['id'],
    ]);

} catch (Throwable $e) {
    $ref = app_log('error', 'exam_status error: ' . $e->getMessage());
    json_response(['status' => 'error', 'ref' => $ref], 500);
}
