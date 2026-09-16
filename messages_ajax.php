<?php
// messages_ajax.php – PRIVATE PAIR-BASED AJAX HANDLER
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
require 'db_connect.php';
require 'encryption.php';
require_once 'notifications.php';

$user_id = intval($_SESSION['user_id']);
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Ensure `receiver_id` column exists (if not, add it)
$check_col = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'receiver_id'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE chat_messages ADD COLUMN receiver_id INT NOT NULL DEFAULT 0 AFTER sender_id");
}

// ============================================
// 1. GET MESSAGES (pair-based)
// ============================================
if ($action === 'get_messages') {
    $item_id = intval($_GET['item_id'] ?? 0);
    $other_id = intval($_GET['other_id'] ?? 0);

    if ($item_id <= 0 || $other_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // Fetch messages where sender and receiver match the pair
    $stmt = $conn->prepare("SELECT cm.*, u.unique_id 
                            FROM chat_messages cm 
                            JOIN users u ON cm.sender_id = u.id 
                            WHERE cm.item_report_id = ? 
                              AND ((cm.sender_id = ? AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = ?))
                            ORDER BY cm.sent_at ASC");
    $stmt->bind_param("iiiii", $item_id, $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $decrypted = decryptMessage($row['message'], $row['iv']);
        $messages[] = [
            'id' => intval($row['id']),
            'sender_id' => intval($row['sender_id']),
            'unique_id' => $row['unique_id'],
            'message' => $decrypted,
            'sent_at' => date('H:i', strtotime($row['sent_at'])),
            'is_read' => intval($row['is_read'] ?? 0)
        ];
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

// ============================================
// 2. SEND MESSAGE (pair-based)
// ============================================
if ($action === 'send') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $other_id = intval($_POST['other_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($item_id <= 0 || $other_id <= 0 || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    // Verify item exists and get item name for notification
    $check = $conn->prepare("SELECT id, user_id, item_name FROM item_reports WHERE id = ?");
    $check->bind_param("i", $item_id);
    $check->execute();
    $item = $check->get_result()->fetch_assoc();
    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }

    $encrypted = encryptMessage($message);
    $stmt = $conn->prepare("INSERT INTO chat_messages (item_report_id, sender_id, receiver_id, message, iv) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $item_id, $user_id, $other_id, $encrypted['data'], $encrypted['iv']);

    if ($stmt->execute()) {
        // Clear typing status
        $clear_typing = $conn->prepare("DELETE FROM chat_typing WHERE item_report_id = ? AND user_id = ?");
        $clear_typing->bind_param("ii", $item_id, $user_id);
        $clear_typing->execute();

        // Notify the receiver
        addNotification(
            $other_id,
            'message',
            "New anonymous message regarding: " . $item['item_name'],
            'messages.php?item=' . $item_id . '&other=' . $user_id
        );

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// ============================================
// 3. TYPING STATUS (DISABLED FOR SERVER PERFORMANCE)
// ============================================
if ($action === 'typing') {
    // Return empty success so the frontend doesn't throw errors
    echo json_encode(['success' => true, 'typing' => false, 'user' => null]);
    exit;
}
// ============================================
// 4. MARK AS READ (per pair)
// ============================================
if ($action === 'mark_read') {
    $item_id = intval($_GET['item_id'] ?? 0);
    $other_id = intval($_GET['other_id'] ?? 0);
    if ($item_id <= 0 || $other_id <= 0) {
        echo json_encode(['success' => false]);
        exit;
    }
    $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE item_report_id = ? AND sender_id = ? AND receiver_id = ?");
    $stmt->bind_param("iii", $item_id, $other_id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// 5. UNREAD COUNT (global navbar badge)
// ============================================
if ($action === 'get_unread_count') {
    $unread_query = "SELECT COUNT(*) as count 
                    FROM chat_messages 
                    WHERE receiver_id = ? AND is_read = 0";
    $u_stmt = $conn->prepare($unread_query);
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $count = $u_stmt->get_result()->fetch_assoc()['count'] ?? 0;

    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>