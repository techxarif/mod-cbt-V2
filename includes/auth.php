<?php

// ============================================================
// MODUS CBT — Auth Guards
// requireTeacher(), requireStudent(), currentStudent()
// ============================================================


// ----------------------------------------------------------
// Teacher (admin) guard
// ----------------------------------------------------------

function requireTeacher(): void
{
    if (!isset($_SESSION['teacher_id'])) {
        header('Location: ' . _auth_base() . '/auth/login.php');
        exit;
    }
}

function teacherLoggedIn(): bool
{
    return isset($_SESSION['teacher_id']);
}


// ----------------------------------------------------------
// Student guard
// ----------------------------------------------------------

function requireStudent(): void
{
    if (!isset($_SESSION['student_id'])) {
        header('Location: ' . _auth_base() . '/exam_session/login.php');
        exit;
    }
}

function studentLoggedIn(): bool
{
    return isset($_SESSION['student_id']);
}


// ----------------------------------------------------------
// currentStudent() — returns session data array
// ----------------------------------------------------------

function currentStudent(): array
{
    return [
        'id'   => (int)   ($_SESSION['student_id']   ?? 0),
        'name' =>          $_SESSION['student_name']  ?? '',
        'uid'  =>          $_SESSION['student_uid']   ?? '',
    ];
}


// ----------------------------------------------------------
// currentTeacher() — returns session data array
// ----------------------------------------------------------

function currentTeacher(): array
{
    global $pdo;
    $id = (int)($_SESSION['teacher_id'] ?? 0);
    $avatar = $_SESSION['teacher_avatar'] ?? null;

    if ($id > 0 && (!isset($_SESSION['teacher_avatar']) || !isset($_SESSION['teacher_name']))) {
        try {
            $stmt = $pdo->prepare("SELECT name, username, avatar FROM teachers WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $_SESSION['teacher_name']   = $row['name'];
                $_SESSION['username']       = $row['username'];
                $_SESSION['teacher_avatar'] = $row['avatar'];
                $avatar = $row['avatar'];
            }
        } catch (Throwable $e) {
            // fallback gracefully
        }
    }

    return [
        'id'       => $id,
        'name'     => $_SESSION['teacher_name']  ?? ($_SESSION['username'] ?? 'Administrator'),
        'username' => $_SESSION['username']       ?? '',
        'avatar'   => $avatar,
    ];
}


// ----------------------------------------------------------
// _auth_base() — root of the app (strips trailing slash)
// ----------------------------------------------------------

function _auth_base(): string
{
    // Detect app root relative to the current file
    // Works whether called from includes/ or any subfolder
    $root = dirname(__DIR__);
    $doc  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $rel  = str_replace('\\', '/', substr($root, strlen($doc)));
    return rtrim($rel, '/');
}