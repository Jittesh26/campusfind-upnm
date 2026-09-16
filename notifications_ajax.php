<?php
// notifications_ajax.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
require 'db_connect.php';
require_once 'notifications.php';

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// ============================================
// MARK ALL AS READ
// ============================================
if ($action === 'mark_all_read') {
    $result = markAllNotificationsRead($user_id);
    echo json_encode(['success' => $result]);
    exit;
}

// ============================================
// MARK SINGLE AS READ
// ============================================
if ($action === 'mark_read') {
    $notif_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($notif_id > 0) {
        $result = markNotificationRead($notif_id, $user_id);
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
    exit;
}

// ============================================
// GET UNREAD COUNT (for live badge)
// ============================================
if ($action === 'get_unread_count') {
    $count = getUnreadNotificationCount($user_id);
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

// ============================================
// GET NOTIFICATIONS HTML (for dropdown)
// ============================================
if ($action === 'get_notifications_html') {
    $notif_result = getNotifications($user_id, 10);
    ob_start();
    if (!$notif_result || $notif_result->num_rows === 0) {
        echo '<li class="dropdown-item text-white-50">No new notifications</li>';
    } else {
        while ($notif = $notif_result->fetch_assoc()) {
            $bg = $notif['is_read'] ? '' : 'bg-primary bg-opacity-25';
            $link = !empty($notif['link']) ? $notif['link'] : '#';
            echo '<li><a class="dropdown-item text-white ' . $bg . '" href="' . $link . '" data-notif-id="' . $notif['id'] . '" data-notif-link="' . $link . '">';
            echo '<div>' . htmlspecialchars($notif['message']) . '</div>';
            echo '<small class="text-white-50">' . date('d M H:i', strtotime($notif['created_at'])) . '</small>';
            echo '</a></li>';
        }
        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li><a class="dropdown-item text-center text-primary" href="#" id="markAllRead">Mark all as read</a></li>';
    }
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>