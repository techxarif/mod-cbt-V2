<?php

session_start();

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];
$studentUid = $_SESSION['student_uid'];


/*
|--------------------------------------------------------------------------
| Find Current Exam
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        duration_minutes,
        total_marks,
        negative_marks,
        start_time,
        status
    FROM tests
    WHERE status IN ('waiting', 'active')
    ORDER BY id DESC
    LIMIT 1
");

$test = $stmt->fetch();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Waiting Room - MODUS CBT</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    background: #f5f6f8;

    font-family: Arial, sans-serif;

    color: #111;
}

.topbar {

    height: 60px;

    background: #111;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 25px;
}

.logo {

    font-size: 20px;

    font-weight: 700;
}

.topbar a {

    color: white;

    text-decoration: none;
}

.container {

    max-width: 700px;

    margin: 60px auto;

    padding: 0 20px;
}

.card {

    background: white;

    border-radius: 15px;

    padding: 40px;

    text-align: center;

    box-shadow:
        0 10px 35px rgba(0,0,0,0.07);
}

.icon {

    width: 70px;

    height: 70px;

    margin: 0 auto 20px;

    border-radius: 50%;

    background: #f0f0f0;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;
}

h1 {

    margin: 0 0 10px;
}

.subtitle {

    color: #777;

    margin-bottom: 30px;
}

.test-info {

    background: #f7f7f7;

    border-radius: 10px;

    padding: 20px;

    text-align: left;

    margin-bottom: 25px;
}

.test-title {

    font-size: 20px;

    font-weight: 700;

    margin-bottom: 15px;
}

.info-row {

    display: flex;

    justify-content: space-between;

    padding: 9px 0;

    border-bottom: 1px solid #e5e5e5;

    color: #555;
}

.info-row:last-child {

    border-bottom: none;
}

.status {

    display: inline-block;

    padding: 9px 16px;

    border-radius: 30px;

    background: #fff3cd;

    color: #856404;

    font-weight: 600;

    margin-bottom: 20px;
}

.waiting {

    color: #777;

    margin-top: 15px;
}

.user {

    font-size: 13px;

    color: #999;

    margin-top: 25px;
}

</style>

</head>

<body>


<div class="topbar">

    <div class="logo">
        MODUS CBT
    </div>

    <div>

        UID:
        <?= htmlspecialchars($studentUid) ?>

        &nbsp; | &nbsp;

        <a href="logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">


    <div class="card">


        <?php if (!$test): ?>


            <div class="icon">
                ⏳
            </div>


            <h1>
                Waiting for Exam
            </h1>


            <div class="subtitle">

                Your teacher has not started an exam yet.

            </div>


            <div class="waiting">

                Please keep this page open.
                The exam will appear automatically.

            </div>


        <?php else: ?>


            <div class="icon">
                📝
            </div>


            <h1>
                <?= htmlspecialchars($test['title']) ?>
            </h1>


            <?php if ($test['status'] === 'waiting'): ?>


                <div class="status">
                    Waiting for Teacher
                </div>


            <?php else: ?>


                <div class="status">
                    Exam Starting...
                </div>


            <?php endif; ?>


            <div class="test-info">


                <div class="test-title">
                    Exam Information
                </div>


                <div class="info-row">

                    <span>
                        Duration
                    </span>

                    <strong>
                        <?= htmlspecialchars($test['duration_minutes']) ?>
                        minutes
                    </strong>

                </div>


                <div class="info-row">

                    <span>
                        Total Marks
                    </span>

                    <strong>
                        <?= htmlspecialchars($test['total_marks']) ?>
                    </strong>

                </div>


                <div class="info-row">

                    <span>
                        Negative Marking
                    </span>

                    <strong>
                        <?= htmlspecialchars($test['negative_marks']) ?>
                    </strong>

                </div>


            </div>


            <p class="waiting">

                Keep this page open.

                When your teacher starts the exam,
                the exam screen will open automatically.

            </p>


        <?php endif; ?>


        <div class="user">

            Logged in as:
            <?= htmlspecialchars($studentUid) ?>

        </div>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| Check Exam Status Every 2 Seconds
|--------------------------------------------------------------------------
*/

setInterval(function () {

    fetch('../api/exam_status.php')
        .then(response => response.json())
        .then(data => {

            if (data.status === 'active') {

                window.location.href = 'exam.php';

            }

        })
        .catch(error => {

            console.log(
                'Exam status check failed'
            );

        });

}, 2000);

</script>


</body>

</html>