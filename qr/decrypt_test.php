<?php
// qr/decrypt_test.php — AJAX endpoint to test/verify .modus package decryption
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

$encryptedInput = '';

// Check if file was uploaded
if (isset($_FILES['modus_file']) && $_FILES['modus_file']['error'] === UPLOAD_ERR_OK) {
    $encryptedInput = file_get_contents($_FILES['modus_file']['tmp_name']);
} else {
    // Check raw POST or JSON body
    $rawInput = file_get_contents('php://input');
    $jsonBody = json_decode($rawInput, true);
    if (is_array($jsonBody) && !empty($jsonBody['payload'])) {
        $encryptedInput = $jsonBody['payload'];
    } elseif (!empty($_POST['payload'])) {
        $encryptedInput = $_POST['payload'];
    }
}

$encryptedInput = trim($encryptedInput);
if ($encryptedInput === '') {
    echo json_encode(['success' => false, 'error' => 'No .modus content or file provided for decryption.']);
    exit;
}

try {
    $decrypted = modus_decrypt_package($encryptedInput);
    if ($decrypted === null) {
        echo json_encode([
            'success' => false,
            'error'   => 'Decryption failed: Invalid key, corrupted payload, or fixed salt "MODUS CBT" mismatch.'
        ]);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'message'   => 'Successfully decrypted and verified .modus package.',
        'data'      => $decrypted
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Error during decryption: ' . $e->getMessage()
    ]);
}
exit;
