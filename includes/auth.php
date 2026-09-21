<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireTeacher()
{
    if (!isset($_SESSION['teacher_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function teacherLoggedIn()
{
    return isset($_SESSION['teacher_id']);
}