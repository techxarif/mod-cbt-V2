<?php
// includes/crypto.php — Two-Way Symmetric Encryption for MODUS Packages (.modus)

if (!defined('MODUS_SALT')) {
    define('MODUS_SALT', 'MODUS CBT');
}

/**
 * Encrypt a JSON payload into the MODUS encrypted file format:
 * 1. Convert data to JSON
 * 2. Sandwich with Fixed Salt: "MODUS CBT" . $json . "MODUS CBT"
 * 3. Encrypt using AES-256-CBC
 *    - Key: SHA-256 hash of "MODUS CBT" (32 bytes binary)
 *    - IV: Cryptographically secure random 16 bytes
 * 4. Package as Base64 string of (IV + Ciphertext)
 *
 * @param array|string $data Raw associative array or valid JSON string
 * @return string Base64-encoded encrypted package
 */
function modus_encrypt_package(array|string $data): string
{
    $salt = MODUS_SALT;
    $json = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // Add Fixed Salt called "MODUS CBT" at the start and end of the json
    $saltedPlaintext = $salt . $json . $salt;
    
    $cipher = 'AES-256-CBC';
    $key = hash('sha256', $salt, true); // 32-byte binary key
    $iv = openssl_random_pseudo_bytes(16); // 16-byte initialization vector
    
    $ciphertext = openssl_encrypt($saltedPlaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed: ' . openssl_error_string());
    }
    
    // Store IV (16 bytes) + Ciphertext in Base64 encoding
    return base64_encode($iv . $ciphertext);
}

/**
 * Decrypt a MODUS encrypted package string back to original PHP array:
 * 1. Base64 decode to binary
 * 2. Extract 16-byte IV and Ciphertext
 * 3. Decrypt using AES-256-CBC with SHA-256(MODUS_SALT)
 * 4. Verify and strip Fixed Salt "MODUS CBT" from start and end
 * 5. Decode JSON to array
 *
 * @param string $encryptedData Base64-encoded string from .modus file
 * @return array|null Returns associative array on success, or null on failure/tampering
 */
function modus_decrypt_package(string $encryptedData): ?array
{
    $salt = MODUS_SALT;
    $key = hash('sha256', $salt, true);
    
    $raw = base64_decode(trim($encryptedData), true);
    if ($raw === false || strlen($raw) <= 16) {
        // Fallback: check if raw binary was passed
        $raw = $encryptedData;
        if (strlen($raw) <= 16) {
            return null;
        }
    }
    
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    
    $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        return null;
    }
    
    $saltLen = strlen($salt);
    if (str_starts_with($decrypted, $salt) && str_ends_with($decrypted, $salt)) {
        $json = substr($decrypted, $saltLen, -$saltLen);
        $result = json_decode($json, true);
        return is_array($result) ? $result : null;
    }
    
    return null;
}
