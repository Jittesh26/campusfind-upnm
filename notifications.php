<?php
// notifications.php – Exception-Safe Helper Functions
require_once 'db_connect.php';

if (!function_exists('addNotification')) {
    function addNotification($user_id, $type, $message, $link = null) {
        global $conn;
        if (!$conn) return false;
        try {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
            if (!$stmt) return false;
            $stmt->bind_param("isss", $user_id, $type, $message, $link);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount($user_id) {
        global $conn;
        if (!$conn) return 0;
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            if (!$stmt) return 0;
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) return 0;
            $result = $stmt->get_result();
            return $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
        } catch (Exception $e) {
            return 0; // Return 0 safely if query fails
        }
    }
}

if (!function_exists('getNotifications')) {
    function getNotifications($user_id, $limit = 10) {
        global $conn;
        if (!$conn) return false;
        try {
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
            if (!$stmt) return false;
            $stmt->bind_param("ii", $user_id, $limit);
            if (!$stmt->execute()) return false;
            return $stmt->get_result();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('markNotificationRead')) {
    function markNotificationRead($notification_id, $user_id) {
        global $conn;
        if (!$conn) return false;
        try {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            if (!$stmt) return false;
            $stmt->bind_param("ii", $notification_id, $user_id);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('markAllNotificationsRead')) {
    function markAllNotificationsRead($user_id) {
        global $conn;
        if (!$conn) return false;
        try {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            if (!$stmt) return false;
            $stmt->bind_param("i", $user_id);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}
?>