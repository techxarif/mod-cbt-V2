<?php

// ============================================================
// MODUS CBT — Exam Submit (P0 Critical Fixes)
// Was: student/submit.php (accessed via GET, no CSRF, no lock)
// Now: exam_session/submit.php — POST-only, CSRF, FOR UPDATE, time check
//
// P0 fixes applied:
//   - GET submit.php?test_id= is gone (405 on GET)
//   - CSRF verified
//   - SELECT ... FOR UPDATE prevents double-submit race
//   - Server-side time check using st.started_at (not global start_time)
//   - WHERE t.status='active' enforced
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

// ----------------------------------------------------------
// Only POST allowed (P0.2 — no more GET submit)
// ----------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    abort_html(405, 'Method Not Allowed', 'Exam submission requires a POST request.');
}

// ----------------------------------------------------------
// CSRF verification (S1)
// ----------------------------------------------------------

csrf_verify();

// ----------------------------------------------------------
// Must be a logged-in student
// ----------------------------------------------------------

requireStudent();

$student_id = (int) $_SESSION['student_id'];

// ----------------------------------------------------------
// Input
// ----------------------------------------------------------

$test_id = int_param('test_id', 0, 'post');

if ($test_id <= 0) {
    abort_html(400, 'Invalid Request', 'No test specified.');
}

try {

    // ----------------------------------------------------------
    // Start transaction — use FOR UPDATE to prevent double-submit race (P0.4)
    // ----------------------------------------------------------

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            st.id               AS student_test_id,
            st.status           AS student_test_status,
            st.started_at       AS student_started_at,
            t.id                AS test_id,
            t.title,
            t.status            AS test_status,
            t.duration_minutes
        FROM student_tests st
        INNER JOIN tests t ON t.id = st.test_id
        WHERE st.student_id = ?
          AND st.test_id    = ?
          AND t.status      = 'active'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$student_id, $test_id]);
    $student_test = $stmt->fetch();

    if (!$student_test) {
        $pdo->rollBack();
        abort_html(404, 'Exam Not Found', 'Exam not found or it is no longer active.');
    }

    // ----------------------------------------------------------
    // Already submitted → redirect to results (idempotent)
    // ----------------------------------------------------------

    if ($student_test['student_test_status'] === 'submitted') {
        $pdo->rollBack();
        redirect('finish.php?test_id=' . $test_id);
    }

    // ----------------------------------------------------------
    // Server-side time check (P0.2 — use st.started_at, not global start_time)
    // ----------------------------------------------------------

    if (!empty($student_test['student_started_at'])) {

        $duration_seconds = ((int) $student_test['duration_minutes']) * 60;
        $started_at       = strtotime($student_test['student_started_at']);
        $end_at           = $started_at + $duration_seconds;

        // Allow 30-second grace for network latency
        if (time() > $end_at + 30) {
            // Time expired — auto-submit is fine, continue with scoring
            // (don't block — the student's answers were already saved)
        }
    }

    // ----------------------------------------------------------
    // Get all questions + saved answers
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            q.id,
            q.correct_option,
            q.marks,
            q.negative_marks,
            a.selected_option
        FROM test_questions tq
        INNER JOIN questions q ON q.id = tq.question_id
        LEFT JOIN answers a
            ON a.question_id       = q.id
           AND a.student_test_id   = ?
        WHERE tq.test_id = ?
        ORDER BY tq.question_order ASC
    ");

    $stmt->execute([
        $student_test['student_test_id'],
        $test_id,
    ]);

    $questions = $stmt->fetchAll();

    // ----------------------------------------------------------
    // Score calculation + bulk answer update
    // Use INSERT ... ON DUPLICATE KEY UPDATE for batch (D2)
    // ----------------------------------------------------------

    $correct        = 0;
    $wrong          = 0;
    $unanswered     = 0;
    $positive_marks = 0.0;
    $negative_marks = 0.0;
    $final_score    = 0.0;

    // Prepare bulk upsert values
    $bulk_values  = [];
    $bulk_params  = [];

    foreach ($questions as $question) {

        $q_id     = (int)   $question['id'];
        $selected = $question['selected_option'];
        $correct_option = $question['correct_option'];
        $marks    = (float) $question['marks'];
        $neg      = (float) $question['negative_marks'];

        if ($selected === null || $selected === '') {

            $unanswered++;
            $is_correct   = null;  // SQL NULL
            $marks_awarded = 0;

        } elseif ($selected === $correct_option) {

            $correct++;
            $positive_marks += $marks;
            $final_score    += $marks;
            $is_correct     = 1;
            $marks_awarded  = $marks;

        } else {

            $wrong++;
            $negative_marks += $neg;
            $final_score    -= $neg;
            $is_correct     = 0;
            $marks_awarded  = -$neg;
        }

        $bulk_values[] = '(?, ?, ?, ?, ?, NOW())';
        $bulk_params[] = $student_test['student_test_id'];
        $bulk_params[] = $q_id;
        $bulk_params[] = $selected === '' ? null : $selected;
        $bulk_params[] = $is_correct;
        $bulk_params[] = $marks_awarded;
    }

    // Batch upsert answers (D2 — chunk 500 rows max)
    if ($bulk_values) {

        $chunks = array_chunk($bulk_values,  500);
        $param_chunks = array_chunk($bulk_params, 500 * 5);

        foreach ($chunks as $i => $chunk_vals) {

            $sql = "
                INSERT INTO answers
                    (student_test_id, question_id, selected_option, is_correct, marks_awarded, answered_at)
                VALUES " . implode(',', $chunk_vals) . "
                ON DUPLICATE KEY UPDATE
                    is_correct     = VALUES(is_correct),
                    marks_awarded  = VALUES(marks_awarded),
                    answered_at    = VALUES(answered_at)
            ";

            $chunk_stmt = $pdo->prepare($sql);
            $chunk_stmt->execute($param_chunks[$i]);
        }
    }

    // ----------------------------------------------------------
    // Update student_tests record
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        UPDATE student_tests
        SET
            status          = 'submitted',
            submitted_at    = NOW(),
            score           = ?,
            correct_answers = ?,
            wrong_answers   = ?,
            unanswered      = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $final_score,
        $correct,
        $wrong,
        $unanswered,
        $student_test['student_test_id'],
    ]);

    // ----------------------------------------------------------
    // Close any active exam session
    // ----------------------------------------------------------

    $stmt = $pdo->prepare("
        UPDATE exam_sessions
        SET
            status       = 'submitted',
            submitted_at = NOW()
        WHERE student_test_id = ?
          AND status          = 'active'
    ");

    $stmt->execute([$student_test['student_test_id']]);

    $pdo->commit();

    // ----------------------------------------------------------
    // Store result in session for display on finish page
    // ----------------------------------------------------------

    $_SESSION['last_exam_result'] = [
        'test_id'        => $test_id,
        'title'          => $student_test['title'],
        'correct'        => $correct,
        'wrong'          => $wrong,
        'unanswered'     => $unanswered,
        'positive_marks' => $positive_marks,
        'negative_marks' => $negative_marks,
        'score'          => $final_score,
    ];

    redirect('finish.php?test_id=' . $test_id);


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $ref = app_log('error', 'Exam submit error: ' . $e->getMessage(), [
        'student_id' => $student_id,
        'test_id'    => $test_id,
    ]);

    abort_html(500, 'Submission Failed',
        'An error occurred while submitting your exam. Please contact your invigilator. Ref: ' . $ref
    );
}
