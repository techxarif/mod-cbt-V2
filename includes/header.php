<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        <?= isset($page_title)
            ? htmlspecialchars($page_title)
            : 'MODUS CBT' ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body>

<header class="main-header">

    <div class="brand">
        MODUS CBT
    </div>

    <nav>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="students.php">
            Students
        </a>

        <a href="tests.php">
            Tests
        </a>

        <a href="questions.php">
            Questions
        </a>

        <a href="assign_students.php">
            Assign
        </a>

        <a href="exam_control.php">
            Exam Control
        </a>

        <a href="results.php">
            Results
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </nav>

</header>

<main class="page-container">