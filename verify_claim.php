<?php
// verify_claim.php – Automated Gatekeeper Challenge with AI Safety Net
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please log in first.']);
    exit;
}
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
$answer = isset($_POST['answer']) ? trim($_POST['answer']) : '';

if ($item_id <= 0 || empty($answer)) {
    echo json_encode(['success' => false, 'error' => 'Invalid answer submission.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, user_id, verification_question, verification_answer FROM item_reports WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    echo json_encode(['success' => false, 'error' => 'Item not found.']);
    exit;
}

if ($item['user_id'] == $user_id) {
    echo json_encode(['success' => false, 'error' => 'You cannot claim an item you reported.']);
    exit;
}

// If item has no challenge set, allow instant access
if (empty($item['verification_question']) || empty($item['verification_answer'])) {
    $_SESSION['verified_chat'][$item_id] = time(); // Store timestamp for 24h expiry
    echo json_encode(['success' => true, 'verified' => true]);
    exit;
}

// Check database attempt logs
$attempt_stmt = $conn->prepare("SELECT attempts_left, is_blocked, block_expiry FROM claim_attempts WHERE user_id = ? AND item_report_id = ?");
$attempt_stmt->bind_param("ii", $user_id, $item_id);
$attempt_stmt->execute();
$attempt_data = $attempt_stmt->get_result()->fetch_assoc();

if ($attempt_data) {
    if ($attempt_data['is_blocked'] == 1) {
        $block_expiry = strtotime($attempt_data['block_expiry']);
        if ($block_expiry > time()) {
            $remaining = ceil(($block_expiry - time()) / 60);
            echo json_encode(['success' => false, 'error' => "3 Failed attempts reached. Blocked for $remaining minute(s).", 'blocked' => true]);
            exit;
        } else {
            // Block expired, reset attempts
            $reset_stmt = $conn->prepare("DELETE FROM claim_attempts WHERE user_id = ? AND item_report_id = ?");
            $reset_stmt->bind_param("ii", $user_id, $item_id);
            $reset_stmt->execute();
            $attempt_data = null;
        }
    }
}

$attempts_left = $attempt_data ? intval($attempt_data['attempts_left']) : 3;

// === STAGE 1: FAST PHP EXACT/CASE-INSENSITIVE MATCH ===
if (strtolower(trim($answer)) === strtolower(trim($item['verification_answer']))) {
    $_SESSION['verified_chat'][$item_id] = time(); // Store timestamp for 24h expiry
    $del_stmt = $conn->prepare("DELETE FROM claim_attempts WHERE user_id = ? AND item_report_id = ?");
    $del_stmt->bind_param("ii", $user_id, $item_id);
    $del_stmt->execute();
    echo json_encode(['success' => true, 'verified' => true, 'method' => 'exact']);
    exit;
}

// === STAGE 2: AI SEMANTIC FALLBACK (Google Gemini) ===
// Load key from our new config.env.php (required via db_connect.php)
if (!defined('GEMINI_API_KEY')) {
    echo json_encode(['success' => false, 'error' => 'Server Configuration Error: API key missing.']);
    exit;
}
$GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . GEMINI_API_KEY;

$prompt = "Compare these two answers to a lost item verification challenge:\n";
$prompt .= "Official Ground Truth Answer: \"" . $item['verification_answer'] . "\"\n";
$prompt .= "User's Submitted Claim Answer: \"" . $answer . "\"\n\n";
$prompt .= "Evaluate whether the user's claim captures the core intent/meaning despite minor typos, phrasing, or synonyms.\n";
$prompt .= "Return ONLY a valid JSON object in this format: {\"match\": true, \"confidence\": 85, \"reason\": \"matching meaning\"}\n";
$prompt .= "DO NOT USE MARKDOWN CODE BLOCKS. RAW JSON ONLY.";

$payload = [
    'contents' => [
        ['parts' => [['text' => $prompt]]]
    ]
];

$ch = curl_init($GEMINI_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 🔥 SAFETY NET: If Gemini fails, abort WITHOUT deducting an attempt
if ($http_code !== 200) {
    echo json_encode([
        'success' => false, 
        'error' => 'AI Verification service is temporarily busy. Please try again or type the exact answer.',
        'attempts_left' => $attempts_left
    ]);
    exit;
}

$semantic_match = false;
$data = json_decode($response, true);
if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    $raw_text = $data['candidates'][0]['content']['parts'][0]['text'];
    $clean_text = trim(preg_replace('/```json\s*|```\s*/', '', $raw_text));
    $result = json_decode($clean_text, true);
    
    if (is_array($result) && isset($result['match']) && $result['match'] === true && ($result['confidence'] ?? 0) >= 70) {
        $semantic_match = true;
    }
}

if ($semantic_match) {
    $_SESSION['verified_chat'][$item_id] = time(); // Store timestamp for 24h expiry
    $del_stmt = $conn->prepare("DELETE FROM claim_attempts WHERE user_id = ? AND item_report_id = ?");
    $del_stmt->bind_param("ii", $user_id, $item_id);
    $del_stmt->execute();
    echo json_encode(['success' => true, 'verified' => true, 'method' => 'ai_semantic']);
    exit;
}

// === STAGE 3: CLAIM FAILED ===
$attempts_left--;

if ($attempts_left <= 0) {
    $block_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    if ($attempt_data) {
        $update_stmt = $conn->prepare("UPDATE claim_attempts SET attempts_left = 0, is_blocked = 1, block_expiry = ? WHERE user_id = ? AND item_report_id = ?");
        $update_stmt->bind_param("sii", $block_expiry, $user_id, $item_id);
        $update_stmt->execute();
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO claim_attempts (user_id, item_report_id, attempts_left, is_blocked, block_expiry) VALUES (?, ?, 0, 1, ?)");
        $insert_stmt->bind_param("iis", $user_id, $item_id, $block_expiry);
        $insert_stmt->execute();
    }
    echo json_encode(['success' => false, 'error' => 'All 3 verification attempts failed. You are blocked from claiming this item for 24 hours.', 'blocked' => true]);
    exit;
} else {
    if ($attempt_data) {
        $update_stmt = $conn->prepare("UPDATE claim_attempts SET attempts_left = ? WHERE user_id = ? AND item_report_id = ?");
        $update_stmt->bind_param("iii", $attempts_left, $user_id, $item_id);
        $update_stmt->execute();
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO claim_attempts (user_id, item_report_id, attempts_left) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("iii", $user_id, $item_id, $attempts_left);
        $insert_stmt->execute();
    }
    echo json_encode(['success' => false, 'error' => "Incorrect answer. You have $attempts_left attempt(s) remaining.", 'attempts_left' => $attempts_left]);
    exit;
}
?>