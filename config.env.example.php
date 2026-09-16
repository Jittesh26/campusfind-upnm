<?php
// config.env.php - KEEP THIS SECURE. DO NOT UPLOAD TO GITHUB.
// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'campusfind_db');

// API Keys
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('RESEND_API_KEY', 'YOUR_RESEND_API_KEY_HERE');

// Encryption & System Setup
define('ENCRYPTION_KEY', 'YOUR_32_BYTE_AES_KEY_HERE'); // 32-byte AES key
define('BASE_URL', 'http://localhost/campusfind/');
?>