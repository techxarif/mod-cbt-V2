<?php

// ============================================================
// MODUS CBT — Helpers
// Safe escaping, input helpers, formatting.
// Loaded via bootstrap.php
// ============================================================


// ----------------------------------------------------------
// e() — safe HTML output (replaces bare echo / htmlspecialchars)
// ----------------------------------------------------------

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


// ----------------------------------------------------------
// int_param() — safe integer from GET or POST
// ----------------------------------------------------------

function int_param(string $key, int $default = 0, string $from = 'request'): int
{
    $val = match ($from) {
        'get'  => $_GET[$key]  ?? null,
        'post' => $_POST[$key] ?? null,
        default => $_POST[$key] ?? ($_GET[$key] ?? null),
    };

    return $val !== null ? (int) $val : $default;
}


// ----------------------------------------------------------
// str_param() — safe trimmed string from GET or POST
// ----------------------------------------------------------

function str_param(string $key, string $default = '', string $from = 'request'): string
{
    $val = match ($from) {
        'get'  => $_GET[$key]  ?? null,
        'post' => $_POST[$key] ?? null,
        default => $_POST[$key] ?? ($_GET[$key] ?? null),
    };

    return $val !== null ? trim((string) $val) : $default;
}


// ----------------------------------------------------------
// format_score() — display score
// ----------------------------------------------------------

function format_score(mixed $score): string
{
    return number_format((float) $score, 2);
}


// ----------------------------------------------------------
// status_label() — human-readable test/student status
// ----------------------------------------------------------

function status_label(string $status): string
{
    return match ($status) {
        'draft'     => 'Draft',
        'waiting'   => 'Waiting',
        'active'    => 'Active',
        'completed' => 'Completed',
        'assigned'  => 'Assigned',
        'started'   => 'In Progress',
        'submitted' => 'Submitted',
        'inactive'  => 'Inactive',
        default     => ucfirst($status),
    };
}


// ----------------------------------------------------------
// avatar_initial() — safe first letter for avatars
// ----------------------------------------------------------

function avatar_initial(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    return e(strtoupper(mb_substr($name, 0, 1, 'UTF-8')));
}


// ----------------------------------------------------------
// redirect() — safe redirect
// ----------------------------------------------------------

function redirect(string $url, int $code = 302): never
{
    header('Location: ' . $url, true, $code);
    exit;
}


// ----------------------------------------------------------
// json_response() — send JSON and exit
// ----------------------------------------------------------

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}


// ----------------------------------------------------------
// require_method() — enforce HTTP method
// ----------------------------------------------------------

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        header('Allow: ' . strtoupper($method));
        if (defined('is_json_request') && is_json_request()) {
            abort_json(405, 'Method Not Allowed');
        }
        abort_html(405, 'Method Not Allowed');
    }
}


// ----------------------------------------------------------
// is_json_request() — detect JSON API calls
// ----------------------------------------------------------

function is_json_request(): bool
{
    return (
        (isset($_SERVER['HTTP_ACCEPT'])       && str_contains($_SERVER['HTTP_ACCEPT'],       'application/json')) ||
        (isset($_SERVER['CONTENT_TYPE'])      && str_contains($_SERVER['CONTENT_TYPE'],      'application/json')) ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    );
}


// ----------------------------------------------------------
// app_base_url() — returns normalized root-relative base path
// Ensures assets & internal links work reliably across localhost,
// LAN IPs (192.168.x.x), and custom domains without hardcoded hosts.
// ----------------------------------------------------------

function app_base_url(): string
{
    $app_url = defined('APP_URL') ? APP_URL : '/modus-cbt/';
    if (preg_match('#^https?://[^/]+(/.*)?$#i', $app_url, $m)) {
        return !empty($m[1]) ? rtrim($m[1], '/') . '/' : '/';
    }
    return '/' . ltrim(rtrim($app_url, '/'), '/') . '/';
}

