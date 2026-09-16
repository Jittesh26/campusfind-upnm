<?php
// delete_report.php – Delete report & redirect back to My Reports
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';
require_once 'csrf.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_reports.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: my_reports.php?error=csrf');
    exit;
}

$report_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($report_id <= 0) {
    header('Location: my_reports.php');
    exit;
}

// Fetch report to check ownership and delete images
$stmt = $conn->prepare("SELECT user_id, image_path FROM item_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: my_reports.php');
    exit;
}

$report = $result->fetch_assoc();

// Check if user owns the report (or is admin)
if ($report['user_id'] != $user_id && ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: my_reports.php?error=not_owner');
    exit;
}

// 1. Delete primary image
if (!empty($report['image_path']) && file_exists($report['image_path'])) {
    unlink($report['image_path']);
}

// 2. Delete additional images from item_images table & disk
$img_del_stmt = $conn->prepare("SELECT image_path FROM item_images WHERE item_report_id = ?");
$img_del_stmt->bind_param("i", $report_id);
$img_del_stmt->execute();
$img_result = $img_del_stmt->get_result();

while ($img_row = $img_result->fetch_assoc()) {
    if (!empty($img_row['image_path']) && file_exists($img_row['image_path'])) {
        unlink($img_row['image_path']);
    }
}
$img_del_stmt->close();

$clean_stmt = $conn->prepare("DELETE FROM item_images WHERE item_report_id = ?");
$clean_stmt->bind_param("i", $report_id);
$clean_stmt->execute();
$clean_stmt->close();

// 3. Delete the report
$delete_stmt = $conn->prepare("DELETE FROM item_reports WHERE id = ?");
$delete_stmt->bind_param("i", $report_id);
if ($delete_stmt->execute()) {
    // 🔥 REDIRECT TO MY REPORTS WITH SUCCESS MESSAGE
    header('Location: my_reports.php?deleted=1');
} else {
    header('Location: my_reports.php?error=delete_failed');
}
exit;
?>