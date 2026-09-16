<?php
// db_connect.php - Core Database Connection & Utilities
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// 1. Load the Global Error Handler FIRST
require_once 'error_handler.php';

// 2. Load the secure configuration file
if (!file_exists(__DIR__ . '/config.env.php')) {
    die("<div style='font-family:sans-serif; padding:20px; background:#1e293b; color:#f8fafc;'><strong>Setup Required:</strong> <code>config.env.php</code> is missing. Please copy <code>config.env.example.php</code> to <code>config.env.php</code> and insert your local database credentials and API keys.</div>");
}
require_once 'config.env.php';

// 3. Establish Database Connection using the constants
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    throw new Exception('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// 4. Set Malaysia Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn->query("SET time_zone = '+08:00'");

// 5. Global Utility Function for Safe Dates
if (!function_exists('formatDateSafe')) {
    function formatDateSafe($date_string, $format = 'd M Y') {
        if (empty($date_string) || $date_string === '0000-00-00' || $date_string === '0000-00-00 00:00:00') {
            return 'N/A';
        }
        $timestamp = strtotime($date_string);
        if ($timestamp === false || $timestamp < 0) { return 'N/A'; }
        return date($format, $timestamp);
    }
}

// 6. Start Session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>