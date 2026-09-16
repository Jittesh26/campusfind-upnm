<?php
// encryption.php
// Require db_connect.php to load the secure config file
require_once 'db_connect.php';

function encryptMessage($plaintext) {
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    return [
        'data' => base64_encode($ciphertext),
        'iv' => bin2hex($iv)
    ];
}

function decryptMessage($ciphertext_b64, $iv_hex) {
    $ciphertext = base64_decode($ciphertext_b64);
    $iv = hex2bin($iv_hex);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
}
?>