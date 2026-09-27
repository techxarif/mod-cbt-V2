<?php

// ============================================================
// MODUS CBT — Error Handler
// Central error management: logging, generic messages.
// Must be loaded early (Phase 0 bootstrap).
// ============================================================

// ----------------------------------------------------------
// PHP display_errors OFF in production
// ----------------------------------------------------------

if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

// ----------------------------------------------------------
// Ensure log directory exists
// ----------------------------------------------------------

if (defined('LOGS_DIR') && !is_dir(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0750, true);
}


// ----------------------------------------------------------
// app_log() — write to storage/logs/app.log
// ----------------------------------------------------------

function app_log(string $level, string $message, array $context = []): string
{
    $error_id = substr(bin2hex(random_bytes(4)), 0, 8);
    $timestamp = date('Y-m-d H:i:s');

    $line = sprintf(
        "[%s] [%s] [ref:%s] %s %s\n",
        $timestamp,
        strtoupper($level),
        $error_id,
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );

    if (defined('LOGS_DIR')) {
        @file_put_contents(LOGS_DIR . 'app.log', $line, FILE_APPEND | LOCK_EX);
    }

    return $error_id;
}


// ----------------------------------------------------------
// abort_html() — HTML error page, no stack trace
// ----------------------------------------------------------

function abort_html(int $code, string $title, string $detail = ''): never
{
    http_response_code($code);

    if ($code === 404 && file_exists(dirname(__DIR__) . '/404.php')) {
        include dirname(__DIR__) . '/404.php';
        exit;
    }

    $app_name = defined('APP_NAME') ? APP_NAME : 'MODUS CBT';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= $code ?> — <?= htmlspecialchars($title) ?></title>
        <style>
            body { font-family: sans-serif; background: #0a0f1a; color: #e2e8f0;
                   display: flex; align-items: center; justify-content: center;
                   min-height: 100vh; margin: 0; }
            .box { text-align: center; max-width: 420px; padding: 40px 20px; }
            h1 { font-size: 72px; margin: 0 0 8px; color: #3b82f6; }
            h2 { margin: 0 0 16px; font-size: 22px; }
            p  { color: #94a3b8; line-height: 1.6; }
            a  { color: #3b82f6; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1><?= $code ?></h1>
            <h2><?= htmlspecialchars($title) ?></h2>
            <?php if ($detail): ?>
                <p><?= htmlspecialchars($detail) ?></p>
            <?php endif; ?>
            <p><a href="javascript:history.back()">← Go back</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}


// ----------------------------------------------------------
// abort_json() — JSON error response, no stack trace
// ----------------------------------------------------------

function abort_json(int $code, string $message, array $extra = []): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => false,
        'message' => $message,
    ], $extra));
    exit;
}


// ----------------------------------------------------------
// Global exception handler
// ----------------------------------------------------------

set_exception_handler(function (Throwable $e) {

    $is_json = (
        isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
    ) || (
        isset($_SERVER['CONTENT_TYPE']) &&
        str_contains($_SERVER['CONTENT_TYPE'], 'application/json')
    );

    $ref = app_log('error', get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if ($is_json) {
        abort_json(500, 'An unexpected error occurred. Ref: ' . $ref);
    } else {
        abort_html(500, 'Something went wrong', 'An unexpected error occurred. Ref: ' . $ref);
    }
});


// ----------------------------------------------------------
// Global error handler (non-exception PHP errors)
// ----------------------------------------------------------

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {

    if (!(error_reporting() & $errno)) {
        return false; // respect @ operator
    }

    app_log('php_error', $errstr, [
        'errno' => $errno,
        'file'  => $errfile,
        'line'  => $errline,
    ]);

    return false; // let PHP handle it too
});
