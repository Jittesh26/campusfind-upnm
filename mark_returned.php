<?php
// mark_returned.php – Validates Handover PIN & Redirects to Receipt
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';
require_once 'csrf.php';
require_once 'notifications.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: dashboard.php?error=csrf');
    exit;
}

$item_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$submitted_pin = isset($_POST['handover_pin']) ? trim($_POST['handover_pin']) : '';

if ($item_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $conn->prepare("SELECT user_id, item_name, status, handover_pin FROM item_reports WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header('Location: dashboard.php');
    exit;
}

$is_owner = ($item['user_id'] == $user_id);
$is_admin = ($role === 'admin');

if (!$is_owner && !$is_admin) {
    header('Location: item_detail.php?id=' . $item_id . '&error=not_owner');
    exit;
}

if ($item['status'] === 'returned') {
    header('Location: item_detail.php?id=' . $item_id . '&error=already_returned');
    exit;
}

// 🔒 PIN Validation Check
if (!$is_admin && $submitted_pin !== $item['handover_pin']) {
    header('Location: item_detail.php?id=' . $item_id . '&error=invalid_pin');
    exit;
}

// Proceed to update status
$update = $conn->prepare("UPDATE item_reports SET status = 'returned' WHERE id = ?");
$update->bind_param("i", $item_id);

if ($update->execute()) {
    // Notify the report owner if an admin did it
    if ($item['user_id'] != $user_id) {
        addNotification(
            $item['user_id'], 
            'returned', 
            "Your item '" . $item['item_name'] . "' was authorized and marked as returned by an Admin.", 
            'item_detail.php?id=' . $item_id
        );
    }
    
    // 🔥 SUCCESS: Instantly generate and auto-download the PDF Receipt!
    header('Location: generate_receipt.php?id=' . $item_id . '&download=1');
} else {
    header('Location: item_detail.php?id=' . $item_id . '&error=update_failed');
}
exit;
?>