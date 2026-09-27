<?php

// ============================================================
// MODUS CBT — Session Hardening
// Call this ONCE before any session_start() anywhere.
// Replaces all bare session_start() calls.
// ============================================================

if (session_status() !== PHP_SESSION_NONE) {
    return; // already started, nothing to do
}

// ----------------------------------------------------------
// Secure cookie parameters
// ----------------------------------------------------------

$_secure = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
);

session_set_cookie_params([
    'lifetime' => 0,           // browser session cookie
    'path'     => '/',
    'domain'   => '',
    'secure'   => $_secure,    // HTTPS-only when available
    'httponly' => true,        // no JS access
    'samesite' => 'Lax',       // CSRF mitigation
]);

// ----------------------------------------------------------
// Strict mode (reject unrecognized session IDs)
// ----------------------------------------------------------

session_start([
    'use_strict_mode'  => 1,
    'use_only_cookies' => 1,
]);

// ----------------------------------------------------------
// Session timeouts
// ----------------------------------------------------------

$_idle_timeout     = 3600;        // 1 hour idle
$_absolute_timeout = 8 * 3600;   // 8 hours max

$_now = time();

if (isset($_SESSION['_last_active'])) {

    // Idle timeout check
    if ($_now - $_SESSION['_last_active'] > $_idle_timeout) {
        session_unset();
        session_destroy();
        session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1]);
    }
}

if (isset($_SESSION['_created_at'])) {

    // Absolute timeout check
    if ($_now - $_SESSION['_created_at'] > $_absolute_timeout) {
        session_unset();
        session_destroy();
        session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1]);
    }

} else {

    $_SESSION['_created_at'] = $_now;
}

$_SESSION['_last_active'] = $_now;

unset($_secure, $_idle_timeout, $_absolute_timeout, $_now);
