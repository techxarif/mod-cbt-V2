<?php

session_start();

if (isset($_SESSION['teacher_id'])) {

    header('Location: admin/dashboard.php');
    exit;

}

if (isset($_SESSION['student_id'])) {

    header('Location: student/waiting.php');
    exit;

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

    <title>MODUS CBT</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #111827;
        }

        .box {
            width: 420px;
            max-width: 90%;
            background: white;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
        }

        h1 {
            margin: 0 0 10px;
        }

        p {
            color: #6b7280;
            margin-bottom: 30px;
        }

        a {
            display: block;
            text-decoration: none;
            padding: 13px;
            margin-top: 12px;
            border-radius: 8px;
            background: #111827;
            color: white;
        }

        a:hover {
            background: #000;
        }

    </style>

</head>

<body>

<div class="box">

    <h1>MODUS CBT</h1>

    <p>
        Computer Based Testing Platform
    </p>

    <a href="login.php">
        Teacher Login
    </a>

    <a href="student/login.php">
        Student Login
    </a>

</div>

</body>

</html>