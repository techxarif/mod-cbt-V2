<?php

// ============================================================
// MODUS CBT — Database Connection
// Uses constants defined in config/database.php.
// Errors go through error_handler — no DSN/password leaks.
// ============================================================

try {

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

} catch (PDOException $e) {

    // Log real error, show generic message — never expose credentials
    if (function_exists('app_log')) {
        $ref = app_log('error', 'Database connection failed: ' . $e->getMessage());
        if (function_exists('abort_html')) {
            abort_html(500, 'Service Unavailable', 'Could not connect to the database. Ref: ' . $ref);
        }
    }

    // Fallback in case error_handler wasn't loaded yet
    http_response_code(500);
    die('Service unavailable. Please try again later.');
}