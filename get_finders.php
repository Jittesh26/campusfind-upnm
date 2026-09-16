<?php
// get_finders.php – STOPS 'undefined' IN JAVASCRIPT
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
require 'db_connect.php';

$item_id = intval($_GET['item_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);

if ($item_id <= 0) {
    echo json_encode(['success' => false, 'finders' => []]);
    exit;
}

$sql = "SELECT DISTINCT u.id, u.unique_id 
        FROM chat_messages cm 
        JOIN users u ON (
            CASE WHEN cm.sender_id = ? THEN cm.receiver_id ELSE cm.sender_id END = u.id
        ) 
        WHERE cm.item_report_id = ? AND u.id != ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $item_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$finders = [];
while ($row = $result->fetch_assoc()) {
    $unique = trim($row['unique_id'] ?? '');
    if (!empty($unique)) {
        $finders[] = [
            'id' => intval($row['id']),
            'unique_id' => $unique,
            'full_name' => '', // 👈 Blank string prevents JS 'undefined'
            'name' => ''      // 👈 Blank string prevents JS 'undefined'
        ];
    }
}

echo json_encode(['success' => true, 'finders' => $finders]);
?>