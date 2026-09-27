<?php

// ============================================================
// MODUS CBT — Student Logout
// Was: student/logout.php
// Now: exam_session/logout.php
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

// Delete session cookie explicitly (S0 — session hardening fix)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

$_SESSION = [];
session_destroy();

redirect('login.php');
