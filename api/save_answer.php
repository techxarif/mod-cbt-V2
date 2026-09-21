<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Student login required.'
    ]);
    exit;
}

$student_id = (int) $_SESSION['student_id'];

/*
|--------------------------------------------------------------------------
| Read JSON request
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$test_id = isset($input['test_id'])
    ? (int) $input['test_id']
    : 0;

$question_id = isset($input['question_id'])
    ? (int) $input['question_id']
    : 0;

$selected_option = isset($input['selected_option'])
    ? strtoupper(trim($input['selected_option']))
    : '';

/*
|--------------------------------------------------------------------------
| Validate
|--------------------------------------------------------------------------
*/

if ($test_id <= 0 || $question_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid test or question.'
    ]);
    exit;
}

if (
    $selected_option !== '' &&
    !in_array(
        $selected_option,
        ['A', 'B', 'C', 'D'],
        true
    )
) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid answer option.'
    ]);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | Find student's exam
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            st.id AS student_test_id,
            st.status AS student_test_status,
            t.status AS test_status,
            t.start_time,
            t.duration_minutes
        FROM student_tests st

        INNER JOIN tests t
            ON t.id = st.test_id

        WHERE st.student_id = ?
          AND st.test_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $student_id,
        $test_id
    ]);

    $student_test = $stmt->fetch();

    if (!$student_test) {

        echo json_encode([
            'success' => false,
            'message' => 'Student is not assigned to this exam.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Exam must be active
    |--------------------------------------------------------------------------
    */

    if ($student_test['test_status'] !== 'active') {

        echo json_encode([
            'success' => false,
            'message' => 'Exam is not active.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Exam must not be submitted
    |--------------------------------------------------------------------------
    */

    if ($student_test['student_test_status'] === 'submitted') {

        echo json_encode([
            'success' => false,
            'message' => 'Exam has already been submitted.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify question belongs to test
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT q.id
        FROM questions q

        INNER JOIN test_questions tq
            ON tq.question_id = q.id

        WHERE tq.test_id = ?
          AND q.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $test_id,
        $question_id
    ]);

    if (!$stmt->fetch()) {

        echo json_encode([
            'success' => false,
            'message' => 'Question does not belong to this exam.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Check exam time on server
    |--------------------------------------------------------------------------
    */

    if (!empty($student_test['start_time'])) {

        $start_time =
            strtotime($student_test['start_time']);

        $duration =
            ((int)$student_test['duration_minutes']) * 60;

        $end_time =
            $start_time + $duration;

        if (time() >= $end_time) {

            echo json_encode([
                'success' => false,
                'message' => 'Exam time has expired.'
            ]);

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save / update answer
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO answers (
            student_test_id,
            question_id,
            selected_option,
            is_correct,
            marks_awarded,
            answered_at
        )

        VALUES (
            ?,
            ?,
            NULLIF(?, ''),
            NULL,
            0,
            NOW()
        )

        ON DUPLICATE KEY UPDATE

            selected_option =
                NULLIF(VALUES(selected_option), ''),

            is_correct = NULL,

            marks_awarded = 0,

            answered_at = NOW()
    ");

    $stmt->execute([
        $student_test['student_test_id'],
        $question_id,
        $selected_option
    ]);


    /*
    |--------------------------------------------------------------------------
    | Mark exam as started
    |--------------------------------------------------------------------------
    */

    if (
        $student_test['student_test_status']
        === 'assigned'
    ) {

        $stmt = $pdo->prepare("
            UPDATE student_tests

            SET
                status = 'started',
                started_at = COALESCE(
                    started_at,
                    NOW()
                )

            WHERE id = ?
        ");

        $stmt->execute([
            $student_test['student_test_id']
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Return success
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'message' => 'Answer saved successfully.',
        'question_id' => $question_id,
        'selected_option' => $selected_option
    ]);

    exit;


} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'error' => $e->getMessage()
    ]);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Server error.',
        'error' => $e->getMessage()
    ]);

    exit;
}