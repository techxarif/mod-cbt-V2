<?php

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';


if (!isset($_SESSION['student_id'])) {

    echo json_encode([
        'status' => 'login_required'
    ]);

    exit;
}


$stmt = $pdo->query("
    SELECT
        id,
        status
    FROM tests
    WHERE status IN ('waiting', 'active')
    ORDER BY id DESC
    LIMIT 1
");

$test = $stmt->fetch();


if (!$test) {

    echo json_encode([
        'status' => 'waiting'
    ]);

    exit;
}


echo json_encode([
    'status' => $test['status'],
    'test_id' => (int)$test['id']
]);

exit;