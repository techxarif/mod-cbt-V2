<?php

// ============================================================
// MODUS CBT — CSRF Protection
// Hand-rolled token: random_bytes(32) + hash_equals verify.
// Supports both form hidden fields and X-CSRF-Token header.
// ============================================================


// ----------------------------------------------------------
// csrf_token() — get or create token for this session
// ----------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}


// ----------------------------------------------------------
// csrf_field() — hidden input for HTML forms
// ----------------------------------------------------------

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}


// ----------------------------------------------------------
// csrf_verify() — verify form submission (POST)
// Call at top of every POST handler.
// Sends 419 + HTML error on failure.
// ----------------------------------------------------------

function csrf_verify(): void
{
    $token      = csrf_token();
    $submitted  = $_POST['_csrf'] ?? '';

    if (!hash_equals($token, $submitted)) {

        if (function_exists('app_log')) {
            app_log('security', 'CSRF token mismatch', [
                'uri'  => $_SERVER['REQUEST_URI'] ?? '',
                'ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        }

        http_response_code(419);

        if (function_exists('abort_html')) {
            abort_html(419, 'Session Expired', 'Your form session has expired. Please go back and try again.');
        }

        die('CSRF token mismatch. Please go back and try again.');
    }
}


// ----------------------------------------------------------
// csrf_header_verify() — verify JSON / XHR requests
// Checks X-CSRF-Token request header.
// Call at top of every JSON API endpoint.
// ----------------------------------------------------------

function csrf_header_verify(): void
{
    $token     = csrf_token();
    $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!hash_equals($token, $submitted)) {

        if (function_exists('app_log')) {
            app_log('security', 'CSRF header mismatch', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        }

        if (function_exists('abort_json')) {
            abort_json(419, 'CSRF token invalid. Please refresh the page.');
        }

        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid.']);
        exit;
    }
}
