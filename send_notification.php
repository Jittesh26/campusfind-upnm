<?php
// send_notification.php – IMPROVED DELIVERY & CLICKABLE LINK
require_once 'db_connect.php';

function sendEmail($to_email, $to_name, $subject, $html_body, $text_body = '') {
    $url = 'https://api.resend.com/emails';

    // 🔥 FIX: Free Resend accounts MUST use this exact 'From' address!
    $from_email = 'onboarding@resend.dev';
    $from_name  = 'CampusFind AI';

    $payload = [
        'from'    => $from_name . ' <' . $from_email . '>',
        'to'      => [$to_email],
        'subject' => $subject,
        'html'    => $html_body,
        'text'    => $text_body, // Plain text for better deliverability
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . RESEND_API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log for debugging
    $log = date('Y-m-d H:i:s') . " - HTTP $http_code - " . substr($response, 0, 200) . "\n";
    file_put_contents('email_debug.log', $log, FILE_APPEND);

    return ($http_code === 200 || $http_code === 201);
}

function notifyMatch($user_email, $user_name, $item_name, $match_id) {
    $subject = "🔍 Match Found: " . $item_name;

    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : 'http://' . $_SERVER['HTTP_HOST'] . '/';
    $link = $base . 'item_detail.php?id=' . $match_id;

    // 🔥 SIMPLE, CLEAN HTML – LESS LIKELY TRIGGER SPAM
    $html_body = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 10px; padding: 30px;'>
            <h2 style='color: #1e293b;'>Hello " . htmlspecialchars($user_name) . ",</h2>
            <p style='font-size: 16px; color: #334155;'>A potential match for <strong>" . htmlspecialchars($item_name) . "</strong> has been reported.</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . $link . "' target='_blank' style='background: #6366F1; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;'>🔍 View Match Details</a>
            </p>
            <p style='font-size: 14px; color: #64748b;'>Or copy this link into your browser:</p>
            <p style='font-size: 14px; word-break: break-all;'><a href='" . $link . "' style='color: #6366F1;'>" . $link . "</a></p>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 12px; color: #94a3b8;'>This is an automated message from CampusFind AI.</p>
        </div>
    </body>
    </html>
    ";

    // Plain text fallback
    $text_body = "Hello " . $user_name . ",\n\n";
    $text_body .= "A potential match for \"" . $item_name . "\" has been reported.\n\n";
    $text_body .= "View Match Details: " . $link . "\n\n";
    $text_body .= "This is an automated message from CampusFind AI.";

    return sendEmail($user_email, $user_name, $subject, $html_body, $text_body);
}
?>