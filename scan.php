<?php
// scan.php – PUBLIC QR SCAN RECEIVER PAGE (DIRECT CHAT FIX)
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require 'db_connect.php';

$tag_id = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$found_user = null;
$error = '';

if (!empty($tag_id)) {
    // Look up the user by their unique_id securely
    $stmt = $conn->prepare("SELECT u.id, u.unique_id, ct.faculty 
                            FROM users u 
                            JOIN campus_tag ct ON u.id = ct.user_id 
                            WHERE u.unique_id = ?");
    $stmt->bind_param("s", $tag_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $found_user = $result->fetch_assoc();
    } else {
        $error = "This QR code is invalid or unregistered.";
    }
} else {
    $error = "No tag ID provided.";
}

// Ensure the redirect is saved if they are not logged in
if (!isset($_SESSION['user_id']) && $found_user) {
    $_SESSION['redirect_after_login'] = 'scan.php?tag=' . urlencode($tag_id);
}

// Direct Chat Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_chat'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    
    $finder_id = $_SESSION['user_id'];
    $owner_id = $found_user['id'];
    
    // Prevent owner from scanning their own tag
    if ($finder_id == $owner_id) {
        $error = "This is your own Campus Tag.";
    } else {
        // Check if a hidden "Campus Tag Chat" room already exists for this owner
        $tag_check = $conn->prepare("SELECT id FROM item_reports WHERE user_id = ? AND category = 'Campus Tag' LIMIT 1");
        $tag_check->bind_param("i", $owner_id);
        $tag_check->execute();
        $res = $tag_check->get_result();
        
        if($res->num_rows > 0) {
            $tag_item = $res->fetch_assoc();
            $item_id = $tag_item['id'];
        } else {
            // Create a SINGLE hidden chat room for this owner's tag.
            $date = date('Y-m-d');
            $ins = $conn->prepare("INSERT INTO item_reports (user_id, report_type, item_name, category, description, location, status, report_date) VALUES (?, 'lost', 'Campus Tag Chat', 'Campus Tag', 'Secure chat for QR Tag scans.', 'Hidden', 'returned', ?)");
            $ins->bind_param("is", $owner_id, $date);
            $ins->execute();
            $item_id = $ins->insert_id;
        }
        
        // Redirect straight to the chat room!
        header("Location: messages.php?item=$item_id&other=$owner_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusFind – Scanned Tag</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body { background: linear-gradient(135deg, #0F172A, #1E293B); color: white; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 20px; }
        .scan-card { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 40px 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); text-align: center; max-width: 450px; width: 100%; animation: slideUp 0.6s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .icon-circle { width: 80px; height: 80px; background: rgba(16, 185, 129, 0.15); border: 2px solid rgba(16, 185, 129, 0.4); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #10B981; margin: 0 auto 20px; }
        .btn-gradient { background: linear-gradient(135deg, #4F46E5, #06B6D4); border: none; border-radius: 14px; padding: 14px 24px; font-weight: 700; color: white; width: 100%; text-decoration: none; display: inline-block; transition: all 0.3s; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4); cursor: pointer;}
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(79, 70, 229, 0.6); color: white;}
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 14px 24px; color: rgba(255,255,255,0.8); font-weight: 600; width: 100%; text-decoration: none; display: inline-block; transition: all 0.3s; margin-top: 12px; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; }
        .info-box { background: rgba(0,0,0,0.2); border-radius: 12px; padding: 16px; margin-bottom: 24px; border: 1px solid rgba(255,255,255,0.05); }
        .glass-alert { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.25); color: #FCA5A5; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="scan-card">
        <?php if ($error): ?>
            <div class="glass-alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($found_user): ?>
            <div class="icon-circle"><i class="bi bi-shield-check"></i></div>
            
            <h2 class="fw-bold mb-2">Item Found!</h2>
            <p class="text-white-50 mb-4">You have scanned a secure CampusFind tag.</p>

            <div class="info-box">
                <div class="small text-white-50 text-uppercase fw-bold mb-1">Registered Owner ID</div>
                <div class="fs-4 fw-bold text-white mb-3"><?php echo htmlspecialchars($found_user['unique_id']); ?></div>
                
                <div class="small text-white-50 text-uppercase fw-bold mb-1">Owner Faculty</div>
                <div class="fw-semibold text-white"><?php echo htmlspecialchars($found_user['faculty']); ?></div>
            </div>

            <p class="small text-white-50 mb-4">
                To protect the owner's privacy, their phone number is hidden. Please use the secure chat to coordinate a return.
            </p>

            <!-- Action Buttons -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST">
                    <button type="submit" name="start_chat" class="btn-gradient">
                        <i class="bi bi-chat-dots-fill me-2"></i> Open Secure Chat
                    </button>
                </form>
                <a href="dashboard.php" class="btn-outline-glass border-0 mt-2 p-2 fs-6">Cancel</a>
            <?php else: ?>
                <a href="index.php" class="btn-gradient">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Login to Chat
                </a>
                <a href="register.php" class="btn-outline-glass">
                    New User? Create Account
                </a>
            <?php endif; ?>

        <?php elseif(empty($error)): ?>
            
            <div class="icon-circle" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.4); color: #EF4444;">
                <i class="bi bi-x-octagon"></i>
            </div>
            <h3 class="fw-bold mb-3 text-white">Invalid Tag</h3>
            <p class="text-white-50 mb-4"><?php echo $error; ?></p>
            <a href="index.php" class="btn-gradient">Return to Home</a>

        <?php endif; ?>
    </div>

</body>
</html>