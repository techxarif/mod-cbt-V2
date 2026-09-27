<?php

// ============================================================
// MODUS CBT — Bootstrap
// Single entry point loaded by EVERY page.
// Order matters: config → session → db → errors → helpers → csrf → auth
// ============================================================

// Compute the project root regardless of where this file is included from
define('APP_ROOT', dirname(__DIR__));

// 1. Load environment + constants
require_once APP_ROOT . '/config/database.php';

// 2. Start session securely (must be before any output)
require_once APP_ROOT . '/includes/session.php';

// 3. Error handling (must be early so subsequent requires are covered)
require_once APP_ROOT . '/includes/error_handler.php';

// 4. Database connection
require_once APP_ROOT . '/includes/db.php';

// 5. Helper functions (e(), int_param, etc.)
require_once APP_ROOT . '/includes/helpers.php';

// 6. CSRF protection (needs session)
require_once APP_ROOT . '/includes/csrf.php';

// 7. Auth guards (needs session + helpers)
require_once APP_ROOT . '/includes/auth.php';

// 8. Rate limiting
require_once APP_ROOT . '/includes/rate_limit.php';

// 9. Upload helpers
require_once APP_ROOT . '/includes/upload.php';

// 10. Cryptographic helpers (two-way symmetric encryption for .modus packages)
require_once APP_ROOT . '/includes/crypto.php';

// 11. Shared test query functions
require_once APP_ROOT . '/includes/tests.php';

// 12. Shared layout functions (layout_header, layout_footer, alert_box)
require_once APP_ROOT . '/includes/layout.php';

// ----------------------------------------------------------
// Security headers (applied to every page)
// ----------------------------------------------------------

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content-Security-Policy: allow same-origin + inline styles/scripts
// (inline CSS/JS still present during migration — tighten after full extraction)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:;");

// HSTS — only on HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
