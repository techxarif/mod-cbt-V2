<?php

// ============================================================
// MODUS CBT — Save Answer API
// Was: api/save_answer.php
// Now: answers/save.php
//
// Fixes applied:
//   - CSRF header verification (S1)
//   - Time check uses st.started_at (P0.2 — not global start_time)
//   - Error responses no longer expose $e->getMessage() (E1)
//   - Wrapped in BEGIN/SELECT FOR UPDATE/COMMIT (D2)
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// ----------------------------------------------------------
// CSRF header check for JSON API (S1)
// ----------------------------------------------------------

csrf_header_verify();

// ----------------------------------------------------------
// Student must be logged in
// ----------------------------------------------------------

if (!studentLoggedIn()) {
    json_response(['success' => false, 'message' => 'Student login required.'], 401);
}

$student_id = (int) $_SESSION['student_id'];

// ----------------------------------------------------------
// Parse JSON body
// ----------------------------------------------------------

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    json_response(['success' => false, 'message' => 'Invalid request.'], 400);
}

$test_id         = isset($input['test_id'])       ? (int)    $input['test_id']       : 0;
$question_id     = isset($input['question_id'])   ? (int)    $input['question_id']   : 0;
$selected_option = isset($input['selected_option'])
    ? strtoupper(trim($input['selected_option']))
    : '';

// ----------------------------------------------------------
// Validate inputs
// ----------------------------------------------------------

if ($test_id <= 0 || $question_id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid test or question.'], 400);
}

if (
    $selected_option !== '' &&
    !in_array($selected_option, ['A', 'B', 'C', 'D'], true)
) {
    json_response(['success' => false, 'message' => 'Invalid answer option.'], 400);
}

try {

    // ----------------------------------------------------------
    // Begin transaction — lock the row (D2 — wrap save in transaction)
    // ----------------------------------------------------------

    $pdo->beginTransaction();

    // ----------------------------------------------------------
    // Find and lock student's exam record
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            st.id               AS student_test_id,
            st.status           AS student_test_status,
            st.started_at       AS student_started_at,
            t.status            AS test_status,
            t.duration_minutes
        FROM student_tests st
        INNER JOIN tests t ON t.id = st.test_id
        WHERE st.student_id = ?
          AND st.test_id    = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$student_id, $test_id]);
    $student_test = $stmt->fetch();

    if (!$student_test) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Not assigned to this exam.'], 403);
    }

    // ----------------------------------------------------------
    // Exam must be active
    // ----------------------------------------------------------

    if ($student_test['test_status'] !== 'active') {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Exam is not active.'], 409);
    }

    // ----------------------------------------------------------
    // Must not already be submitted
    // ----------------------------------------------------------

    if ($student_test['student_test_status'] === 'submitted') {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Exam already submitted.'], 409);
    }

    // ----------------------------------------------------------
    // Time check — P0.2 use st.started_at, NOT global start_time
    // ----------------------------------------------------------

    if (!empty($student_test['student_started_at'])) {

        $duration_seconds = ((int) $student_test['duration_minutes']) * 60;
        $started_at       = strtotime($student_test['student_started_at']);
        $end_at           = $started_at + $duration_seconds;

        if (time() >= $end_at + 30) { // 30s grace
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Exam time has expired.'], 409);
        }
    }

    // ----------------------------------------------------------
    // Verify question belongs to this test
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT q.id
        FROM questions q
        INNER JOIN test_questions tq ON tq.question_id = q.id
        WHERE tq.test_id = ? AND q.id = ?
        LIMIT 1
    ");
    $stmt->execute([$test_id, $question_id]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Question not in this exam.'], 400);
    }

    // ----------------------------------------------------------
    // Save / update answer
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        INSERT INTO answers (
            student_test_id, question_id, selected_option,
            is_correct, marks_awarded, answered_at
        )
        VALUES (?, ?, NULLIF(?, ''), NULL, 0, NOW())
        ON DUPLICATE KEY UPDATE
            selected_option = NULLIF(VALUES(selected_option), ''),
            is_correct      = NULL,
            marks_awarded   = 0,
            answered_at     = NOW()
    ");

    $stmt->execute([
        $student_test['student_test_id'],
        $question_id,
        $selected_option,
    ]);

    // ----------------------------------------------------------
    // Mark exam as started if still 'assigned'
    // ----------------------------------------------------------

    if ($student_test['student_test_status'] === 'assigned') {

        $stmt = $pdo->prepare("
            UPDATE student_tests
            SET
                status     = 'started',
                started_at = COALESCE(started_at, NOW())
            WHERE id = ?
        ");

        $stmt->execute([$student_test['student_test_id']]);
    }

    $pdo->commit();

    json_response([
        'success'         => true,
        'message'         => 'Answer saved.',
        'question_id'     => $question_id,
        'selected_option' => $selected_option,
    ]);


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $ref = app_log('error', 'save_answer error: ' . $e->getMessage(), [
        'student_id'  => $student_id,
        'test_id'     => $test_id,
        'question_id' => $question_id,
    ]);

    // Never expose $e->getMessage() to client (E1)
    json_response(['success' => false, 'message' => 'Server error. Ref: ' . $ref], 500);
}
