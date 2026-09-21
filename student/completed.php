<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$test_id = isset($_GET['test_id'])
    ? (int)$_GET['test_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Load result directly from database
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        st.score,
        st.correct_answers,
        st.wrong_answers,
        st.unanswered,
        st.submitted_at,
        t.title,
        t.total_marks
    FROM student_tests st
    INNER JOIN tests t
        ON t.id = st.test_id
    WHERE st.student_id = ?
      AND st.test_id = ?
      AND st.status = 'submitted'
    LIMIT 1
");

$stmt->execute([
    $student_id,
    $test_id
]);

$result = $stmt->fetch();


if (!$result) {
    die("Result not found.");
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Exam Submitted - MODUS CBT
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
    color: #111827;
}

.container {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.card {
    background: white;
    width: 100%;
    max-width: 650px;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.check {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background: #dcfce7;
    color: #16a34a;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 35px;
    font-weight: 700;
}

h1 {
    margin-bottom: 10px;
}

.exam-title {
    color: #6b7280;
    margin-bottom: 30px;
}

.score {
    font-size: 42px;
    font-weight: 800;
    margin-bottom: 30px;
}

.score-label {
    font-size: 14px;
    color: #6b7280;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat {
    background: #f9fafb;
    border-radius: 10px;
    padding: 18px;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
}

.stat-label {
    margin-top: 5px;
    color: #6b7280;
    font-size: 13px;
}

.info {
    color: #6b7280;
    font-size: 14px;
}

.logout {
    display: inline-block;
    margin-top: 25px;
    background: #111827;
    color: white;
    text-decoration: none;
    padding: 12px 22px;
    border-radius: 8px;
    font-weight: 700;
}

@media (max-width: 600px) {

    .card {
        padding: 25px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="check">
            ✓
        </div>

        <h1>
            Exam Submitted
        </h1>

        <div class="exam-title">
            <?= htmlspecialchars($result['title']) ?>
        </div>


        <div class="score">

            <?= htmlspecialchars($result['score']) ?>

            <div class="score-label">
                Score
            </div>

        </div>


        <div class="stats">

            <div class="stat">

                <div class="stat-number">
                    <?= (int)$result['correct_answers'] ?>
                </div>

                <div class="stat-label">
                    Correct
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?= (int)$result['wrong_answers'] ?>
                </div>

                <div class="stat-label">
                    Wrong
                </div>

            </div>


            <div class="stat">

                <div class="stat-number">
                    <?= (int)$result['unanswered'] ?>
                </div>

                <div class="stat-label">
                    Unanswered
                </div>

            </div>

        </div>


        <div class="info">

            Submitted at:

            <?= htmlspecialchars($result['submitted_at']) ?>

        </div>


        <a
            href="logout.php"
            class="logout"
        >
            Finish
        </a>

    </div>

</div>

</body>

</html>