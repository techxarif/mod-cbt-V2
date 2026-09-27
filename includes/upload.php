<?php

// ============================================================
// MODUS CBT — Upload Safety
// safe_unlink(): path containment
// upload_question_image(): validated upload
// ============================================================


// ----------------------------------------------------------
// safe_unlink()
//
// Deletes a file only if it's inside UPLOAD_QUESTIONS_DIR.
// Prevents directory traversal via stored DB paths.
// ----------------------------------------------------------

function safe_unlink(string $relative_path): bool
{
    if ($relative_path === '' || $relative_path === null) {
        return false;
    }

    $base = realpath(defined('UPLOAD_QUESTIONS_DIR') ? UPLOAD_QUESTIONS_DIR : __DIR__ . '/../uploads/questions/');

    if ($base === false) {
        return false;
    }

    $full = realpath(dirname(__DIR__) . '/' . ltrim($relative_path, '/'));

    if ($full === false || !str_starts_with($full, $base)) {
        if (function_exists('app_log')) {
            app_log('security', 'Path traversal attempt blocked in safe_unlink', [
                'relative_path' => $relative_path,
                'resolved'      => $full,
            ]);
        }
        return false;
    }

    if (!file_exists($full)) {
        return false;
    }

    return unlink($full);
}


// ----------------------------------------------------------
// upload_question_image()
//
// Validates and moves an uploaded image for a question.
// Returns: relative path (for DB storage) or false on failure.
//
// $error_out = set by reference, human-readable error
// ----------------------------------------------------------

function upload_question_image(array $file, string &$error_out = ''): string|false
{
    // No file selected
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return false;
    }

    // Upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_out = 'File upload error (code: ' . $file['error'] . ').';
        return false;
    }

    // Size check
    $max_bytes = defined('UPLOAD_MAX_BYTES') ? UPLOAD_MAX_BYTES : 5 * 1024 * 1024;
    if ($file['size'] > $max_bytes) {
        $error_out = 'Image is too large. Maximum allowed: ' . round($max_bytes / 1024 / 1024, 1) . ' MB.';
        return false;
    }

    // MIME type check (both server-reported and finfo)
    $allowed_types = defined('UPLOAD_ALLOWED_TYPES')
        ? UPLOAD_ALLOWED_TYPES
        : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file['tmp_name']);

    if (!in_array($real_mime, $allowed_types, true)) {
        $error_out = 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.';
        return false;
    }

    // Extension from MIME
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $ext_map[$real_mime] ?? 'jpg';

    // Destination
    $upload_dir = defined('UPLOAD_QUESTIONS_DIR')
        ? UPLOAD_QUESTIONS_DIR
        : dirname(__DIR__) . '/uploads/questions/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename     = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest_full    = $upload_dir . $filename;
    $dest_relative = 'uploads/questions/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest_full)) {
        $error_out = 'Failed to save the uploaded file. Please try again.';
        return false;
    }

    chmod($dest_full, 0644);

    return $dest_relative;
}


// ----------------------------------------------------------
// question_image_url()
//
// Returns the web URL for a stored question image path.
// Returns '' if no image.
// ----------------------------------------------------------

function question_image_url(string|null $relative_path): string
{
    if (empty($relative_path)) {
        return '';
    }

    $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    return $base . '/' . ltrim($relative_path, '/');
}
