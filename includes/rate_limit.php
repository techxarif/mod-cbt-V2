<?php

// ============================================================
// MODUS CBT — Rate Limiting (file-based, no DB table)
// Storage: storage/rate_limit/<ip_hash>.json
// Default: 5 attempts per 15 minutes per action per IP
// ============================================================


// ----------------------------------------------------------
// Ensure rate-limit storage dir exists
// ----------------------------------------------------------

if (defined('RATE_LIMIT_DIR') && !is_dir(RATE_LIMIT_DIR)) {
    mkdir(RATE_LIMIT_DIR, 0750, true);
}


// ----------------------------------------------------------
// check_rate_limit()
//
// Returns: ['blocked' => bool, 'remaining' => int, 'retry_after' => int]
//
// $max    = max attempts before block
// $window = window in seconds (default 15s)
// ----------------------------------------------------------

function check_rate_limit(string $action = 'login', int $max = 5, int $window = 15): array
{
    if (!defined('RATE_LIMIT_DIR')) {
        return ['blocked' => false, 'remaining' => $max, 'retry_after' => 0];
    }

    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key      = hash('sha256', $action . '|' . $ip);
    $file     = RATE_LIMIT_DIR . $key . '.json';

    $now      = time();
    $data     = ['attempts' => 0, 'window_start' => $now, 'blocked_until' => 0];

    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    // Reset if window expired
    if ($now - $data['window_start'] >= $window) {
        $data = ['attempts' => 0, 'window_start' => $now, 'blocked_until' => 0];
    }

    // Still blocked?
    if ($data['blocked_until'] > $now) {
        return [
            'blocked'     => true,
            'remaining'   => 0,
            'retry_after' => $data['blocked_until'] - $now,
        ];
    }

    $remaining = max(0, $max - $data['attempts']);

    return [
        'blocked'     => false,
        'remaining'   => $remaining,
        'retry_after' => 0,
        '_file'       => $file,
        '_data'       => $data,
        '_max'        => $max,
        '_window'     => $window,
        '_now'        => $now,
    ];
}


// ----------------------------------------------------------
// record_failed_attempt()
//
// Call after a failed login / authentication attempt.
// Must be called with the result of check_rate_limit().
// ----------------------------------------------------------

function record_failed_attempt(array $rl_result, int $block_duration = 15): void
{
    if (!isset($rl_result['_file'])) {
        return; // rate limiting not available
    }

    $file  = $rl_result['_file'];
    $data  = $rl_result['_data'];
    $max   = $rl_result['_max'];
    $now   = $rl_result['_now'];

    $data['attempts']++;

    // Block if over limit
    if ($data['attempts'] >= $max) {
        $data['blocked_until'] = $now + $block_duration;
    }

    @file_put_contents(
        $file,
        json_encode($data),
        LOCK_EX
    );
}


// ----------------------------------------------------------
// clear_rate_limit()
//
// Call after a successful login to reset the counter.
// ----------------------------------------------------------

function clear_rate_limit(string $action = 'login'): void
{
    if (!defined('RATE_LIMIT_DIR')) {
        return;
    }

    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key  = hash('sha256', $action . '|' . $ip);
    $file = RATE_LIMIT_DIR . $key . '.json';

    if (file_exists($file)) {
        @unlink($file);
    }
}


// ----------------------------------------------------------
// enforce_rate_limit()
//
// Convenience wrapper: check + send 429 response if blocked.
// Call at top of login handlers.
// ----------------------------------------------------------

function enforce_rate_limit(string $action = 'login', int $max = 5, int $window = 15): array
{
    $rl = check_rate_limit($action, $max, $window);

    if ($rl['blocked']) {

        if (function_exists('app_log')) {
            app_log('security', 'Rate limit hit', [
                'action' => $action,
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                'retry'  => $rl['retry_after'],
            ]);
        }

        $secs = max(1, (int)$rl['retry_after']);
        $timeStr = $secs . ' second' . ($secs !== 1 ? 's' : '');

        if (is_json_request()) {
            abort_json(429, 'Too many attempts. Try again in ' . $timeStr . '.');
        }

        abort_html(429, 'Too Many Attempts',
            'Too many failed login attempts. Please wait ' . $timeStr . ' before trying again.'
        );
    }

    return $rl;
}
