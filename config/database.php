<?php

// ============================================================
// MODUS CBT — Database / App Configuration
// Loads from .env, defines constants.
// Never commit credentials. Add .env to .gitignore.
// ============================================================

$_env_path = dirname(__DIR__) . '/.env';

if (file_exists($_env_path)) {
    $_env = parse_ini_file($_env_path);
} else {
    $_env = [];
}

/**
 * Helper: read from loaded .env, fall back to getenv(),
 * then to a hard-coded default.
 */
function _env(string $key, mixed $default = ''): string
{
    global $_env;
    if (isset($_env[$key]) && $_env[$key] !== '') {
        return $_env[$key];
    }
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return (string) $default;
}


// ----------------------------------------------------------
// Database
// ----------------------------------------------------------

define('DB_HOST', _env('DB_HOST', 'localhost'));
define('DB_NAME', _env('DB_NAME', 'md_cbt'));
define('DB_USER', _env('DB_USER', 'root'));
define('DB_PASS', _env('DB_PASS', ''));


// ----------------------------------------------------------
// Application
// ----------------------------------------------------------

define('APP_NAME', _env('APP_NAME', 'MODUS CBT'));
define('APP_URL',  _env('APP_URL',  '/modus-cbt/'));
define('APP_DEBUG', _env('APP_DEBUG', 'false') === 'true');
define('APP_TIMEZONE', _env('APP_TIMEZONE', 'Asia/Kolkata'));
date_default_timezone_set(APP_TIMEZONE);


// ----------------------------------------------------------
// Exam scoring
// ----------------------------------------------------------

define('MARKS_CORRECT', (float) _env('MARKS_CORRECT', 4));
define('MARKS_WRONG',   (float) _env('MARKS_WRONG',   1));


// ----------------------------------------------------------
// Uploads
// ----------------------------------------------------------

define('UPLOAD_MAX_BYTES', (int) _env('UPLOAD_MAX_BYTES', 5 * 1024 * 1024));
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_QUESTIONS_DIR', dirname(__DIR__) . '/uploads/questions/');
define('UPLOAD_QUESTIONS_URL', 'uploads/questions/');


// ----------------------------------------------------------
// Paths
// ----------------------------------------------------------

define('STORAGE_DIR',    dirname(__DIR__) . '/storage/');
define('LOGS_DIR',       STORAGE_DIR . 'logs/');
define('RATE_LIMIT_DIR', STORAGE_DIR . 'rate_limit/');
