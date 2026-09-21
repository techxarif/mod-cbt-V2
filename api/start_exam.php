<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireTeacher();

try {

    /*
    |--------------------------------------------------------------------------
    | Get test ID
    |--------------------------------------------------------------------------
    */

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (is_array($input)) {

        $test_id = isset($input['test_id'])
            ? (int)$input['test_id']
            : 0;

    } else {

        $test_id = isset($_POST['test_id'])
            ? (int)$_POST['test_id']
            : 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if ($test_id <= 0) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid test ID.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Get test
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            status,
            duration_minutes
        FROM tests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $test_id
    ]);

    $test = $stmt->fetch();


    if (!$test) {

        echo json_encode([
            'success' => false,
            'message' => 'Test not found.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Test must be waiting
    |--------------------------------------------------------------------------
    */

    if ($test['status'] !== 'waiting') {

        echo json_encode([
            'success' => false,
            'message' =>
                'Test must be in WAITING status before starting.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Make sure every active student is assigned
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT id
        FROM students
        WHERE status = 'active'
        ORDER BY id ASC
    ");

    $students = $stmt->fetchAll();


    $insert = $pdo->prepare("
        INSERT IGNORE INTO student_tests (
            student_id,
            test_id,
            status
        )
        VALUES (
            ?,
            ?,
            'assigned'
        )
    ");


    foreach ($students as $student) {

        $insert->execute([
            $student['id'],
            $test_id
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Start test
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE tests
        SET
            status = 'active',
            start_time = NOW()
        WHERE id = ?
          AND status = 'waiting'
    ");

    $stmt->execute([
        $test_id
    ]);


    if ($stmt->rowCount() === 0) {

        $pdo->rollBack();

        echo json_encode([
            'success' => false,
            'message' => 'Unable to start the test.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Count assigned students
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM student_tests
        WHERE test_id = ?
    ");

    $stmt->execute([
        $test_id
    ]);

    $count = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'message' =>
            'Exam started successfully.',

        'test_id' =>
            $test_id,

        'title' =>
            $test['title'],

        'students_assigned' =>
            (int)$count['total'],

        'start_time' =>
            date('Y-m-d H:i:s')

    ]);

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Server error.',

        'error' =>
            $e->getMessage()

    ]);

    exit;
}