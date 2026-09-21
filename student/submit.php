<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int) $_SESSION['student_id'];

$test_id = isset($_GET['test_id'])
    ? (int) $_GET['test_id']
    : 0;

if ($test_id <= 0) {
    die("Invalid test.");
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
            t.id AS test_id,
            t.title,
            t.status AS test_status,
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
        die("Exam attempt not found.");
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate submission
    |--------------------------------------------------------------------------
    */

    if ($student_test['student_test_status'] === 'submitted') {

        header(
            'Location: completed.php?test_id=' .
            $test_id
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Get all questions and answers
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            q.id,
            q.correct_option,
            q.marks,
            q.negative_marks,
            a.selected_option
        FROM test_questions tq

        INNER JOIN questions q
            ON q.id = tq.question_id

        LEFT JOIN answers a
            ON a.question_id = q.id
            AND a.student_test_id = ?

        WHERE tq.test_id = ?

        ORDER BY tq.question_order ASC
    ");

    $stmt->execute([
        $student_test['student_test_id'],
        $test_id
    ]);

    $questions = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Calculate result
    |--------------------------------------------------------------------------
    */

    $correct = 0;
    $wrong = 0;
    $unanswered = 0;

    $positive_marks = 0;
    $negative_marks = 0;
    $final_score = 0;


    /*
    |--------------------------------------------------------------------------
    | Begin transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    foreach ($questions as $question) {

        $question_id =
            (int)$question['id'];

        $selected =
            $question['selected_option'];

        $correct_option =
            $question['correct_option'];

        $marks =
            (float)$question['marks'];

        $negative =
            (float)$question['negative_marks'];


        /*
        | Unanswered
        */

        if (
            $selected === null ||
            $selected === ''
        ) {

            $unanswered++;

            $update = $pdo->prepare("
                UPDATE answers
                SET
                    is_correct = NULL,
                    marks_awarded = 0
                WHERE student_test_id = ?
                  AND question_id = ?
            ");

            $update->execute([
                $student_test['student_test_id'],
                $question_id
            ]);

            continue;
        }


        /*
        | Correct
        */

        if ($selected === $correct_option) {

            $correct++;

            $positive_marks += $marks;

            $final_score += $marks;


            $update = $pdo->prepare("
                UPDATE answers
                SET
                    is_correct = 1,
                    marks_awarded = ?
                WHERE student_test_id = ?
                  AND question_id = ?
            ");

            $update->execute([
                $marks,
                $student_test['student_test_id'],
                $question_id
            ]);

        }


        /*
        | Wrong
        */

        else {

            $wrong++;

            $negative_marks += $negative;

            $final_score -= $negative;


            $update = $pdo->prepare("
                UPDATE answers
                SET
                    is_correct = 0,
                    marks_awarded = ?
                WHERE student_test_id = ?
                  AND question_id = ?
            ");

            $update->execute([
                -$negative,
                $student_test['student_test_id'],
                $question_id
            ]);

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Prevent negative final score if required
    |--------------------------------------------------------------------------
    |
    | Most CBT systems allow negative scores.
    | Therefore we intentionally DO NOT force the score to zero.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | Update student_tests
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE student_tests
        SET
            status = 'submitted',
            submitted_at = NOW(),
            score = ?,
            correct_answers = ?,
            wrong_answers = ?,
            unanswered = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $final_score,
        $correct,
        $wrong,
        $unanswered,
        $student_test['student_test_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Close active exam session
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE exam_sessions
        SET
            status = 'submitted',
            submitted_at = NOW()
        WHERE student_test_id = ?
          AND status = 'active'
    ");

    $stmt->execute([
        $student_test['student_test_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Store result temporarily in session
    |--------------------------------------------------------------------------
    */

    $_SESSION['last_exam_result'] = [

        'test_id' => $test_id,

        'title' =>
            $student_test['title'],

        'correct' =>
            $correct,

        'wrong' =>
            $wrong,

        'unanswered' =>
            $unanswered,

        'positive_marks' =>
            $positive_marks,

        'negative_marks' =>
            $negative_marks,

        'score' =>
            $final_score

    ];


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: completed.php?test_id=' .
        $test_id
    );

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        "Submission error: " .
        htmlspecialchars($e->getMessage())
    );
}