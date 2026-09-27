<?php
// exam_session/finish.php — Exam Completed Summary Screen
require_once __DIR__ . '/../includes/bootstrap.php';
requireStudent();

$student_id = (int)$_SESSION['student_id'];
$test_id    = int_param('test_id', 0, 'get');

if ($test_id <= 0) {
    redirect('index.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT st.score, st.correct_answers, st.wrong_answers, st.unanswered, st.submitted_at,
               t.title, t.total_marks, t.duration_minutes
        FROM student_tests st
        INNER JOIN tests t ON t.id = st.test_id
        WHERE st.student_id = ? AND st.test_id = ? AND st.status = 'submitted'
        LIMIT 1
    ");
    $stmt->execute([$student_id, $test_id]);
    $result = $stmt->fetch();

    if (!$result) {
        abort_html(404, 'Result Not Found', 'No submitted examination attempt was found.');
    }
} catch (Throwable $e) {
    $ref = app_log('error', 'Finish screen error: ' . $e->getMessage());
    abort_html(500, 'Error Loading Result', 'Ref: ' . $ref);
}

$page_title = 'Examination Completed — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/exam.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        body {
            background: #090e17;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .result-card {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            padding: 36px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(59, 130, 246, 0.2));
            border: 2px solid #10b981;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 20px auto;
        }
        .score-number {
            font-size: 32px;
            font-weight: 800;
            color: #10b981;
            line-height: 1;
        }
        .score-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin: 24px 0;
        }
        .stat-box {
            background: #1e293b;
            padding: 12px;
            border-radius: 8px;
        }
        .stat-val {
            font-size: 20px;
            font-weight: 700;
        }
        .stat-name {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }
    </style>
</head>
<body>

<div class="result-card">
    <div style="font-size: 40px; margin-bottom: 8px;">🎉</div>
    <h1 style="font-size: 22px; font-weight: 700; color: #f8fafc; margin-bottom: 4px;">Examination Submitted</h1>
    <p style="font-size: 14px; color: #94a3b8;"><?= e($result['title']) ?></p>

    <div class="score-circle">
        <div class="score-number"><?= format_score($result['score']) ?></div>
        <div class="score-label">Final Score</div>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-val" style="color: #10b981;"><?= (int)$result['correct_answers'] ?></div>
            <div class="stat-name">Correct</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color: #ef4444;"><?= (int)$result['wrong_answers'] ?></div>
            <div class="stat-name">Incorrect</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color: #f59e0b;"><?= (int)$result['unanswered'] ?></div>
            <div class="stat-name">Skipped</div>
        </div>
    </div>

    <p style="font-size: 12px; color: #64748b; margin-bottom: 24px;">
        Submitted at <?= date('H:i:s, M j, Y', strtotime($result['submitted_at'])) ?>
    </p>

    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-secondary" style="flex: 1;">Return to Portal</a>
        <a href="logout.php" class="btn btn-danger" style="flex: 1;">Logout</a>
    </div>
</div>

<script src="../assets/js/ui.js"></script>
</body>
</html>
