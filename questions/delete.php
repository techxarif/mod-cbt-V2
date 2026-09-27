<?php
// questions/delete.php — Delete Question Endpoint
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort_html(405, 'Method Not Allowed');
}

csrf_verify();

$question_id = int_param('question_id', 0, 'post');
$test_id     = int_param('test_id', 0, 'post');

if ($question_id <= 0) {
    redirect('index.php');
}

try {
    $pdo->beginTransaction();

    // Fetch image path for safe removal
    $stmt = $pdo->prepare("SELECT question_image FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();

    // Delete linking
    $stmt = $pdo->prepare("DELETE FROM test_questions WHERE question_id = ?");
    $stmt->execute([$question_id]);

    // Delete question
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);

    $pdo->commit();

    if ($question && !empty($question['question_image'])) {
        safe_unlink($question['question_image']);
    }

    $redirect_url = $test_id > 0 ? "index.php?test_id=$test_id" : "index.php";
    redirect($redirect_url);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $ref = app_log('error', 'Delete question error: ' . $e->getMessage(), ['question_id' => $question_id]);
    abort_html(500, 'Delete Failed', 'Failed to delete question. Ref: ' . $ref);
}
