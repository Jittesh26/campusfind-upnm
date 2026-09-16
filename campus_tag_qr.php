<?php
// campus_tag_qr.php – MODERN GLASSMORPHISM QR CODE VIEW
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

$user_id = $_SESSION['user_id'];

// Get unread count for navbar badge
$unread_query = "SELECT COUNT(*) as count 
                FROM chat_messages cm 
                JOIN item_reports ir ON cm.item_report_id = ir.id 
                WHERE cm.sender_id != ? 
                  AND cm.is_read = 0 
                  AND (ir.user_id = ? OR ir.id IN (
                      SELECT item_report_id FROM chat_messages WHERE sender_id = ?
                  ))";
$u_stmt = $conn->prepare($unread_query);
$u_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$u_stmt->execute();
$unreadCount = $u_stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $conn->prepare("SELECT u.full_name, u.email, t.contact_number, t.faculty, t.room_number 
                        FROM users u 
                        JOIN campus_tag t ON u.id = t.user_id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('No tag data found. Please set up your Campus Tag first.');
}
$data = $result->fetch_assoc();

$vcard = "BEGIN:VCARD\n";
$vcard .= "VERSION:3.0\n";
$vcard .= "FN:" . $data['full_name'] . "\n";
$vcard .= "TEL:" . $data['contact_number'] . "\n";
$vcard .= "ORG:" . $data['faculty'] . "\n";
$vcard .= "EMAIL:" . $data['email'] . "\n";
$vcard .= "NOTE:Room " . $data['room_number'] . "\n";
$vcard .= "END:VCARD\n";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusFind – QR Code</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Font: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- QRCode.js -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <!-- Custom UI Stylesheet -->
    <link rel="stylesheet" href="css/custom-ui.css?v=<?php echo time(); ?>">

    <style>
        .qr-bg {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(-45deg, #0F172A, #1E293B, #312E81, #0F172A);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: 0;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            z-index: 0;
            animation: floatOrb 20s ease-in-out infinite;
        }

        .orb-1 { width: 400px; height: 400px; background: #6366F1; top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: #10B981; bottom: -50px; left: -50px; animation-delay: -5s; }
        .orb-3 { width: 200px; height: 200px; background: #EF4444; top: 50%; left: 50%; transform: translate(-50%, -50%); animation-delay: -10s; }

        .qr-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            padding: 30px 20px 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }

        .glass-card h1 {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .glass-card .subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        #qrcode {
            display: inline-block;
            padding: 16px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .contact-info {
            margin-top: 20px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .contact-info p {
            color: rgba(255, 255, 255, 0.7);
            margin: 4px 0;
            font-size: 0.9rem;
        }

        .contact-info strong {
            color: white;
        }

        .btn-glass-cancel {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 10px 22px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-glass-cancel:hover {
            background: rgba(255, 255, 255, 0.18);
            color: white;
        }

        .btn-glass-primary {
            background: linear-gradient(135deg, #6366F1, #4F46E5);
            border: none;
            border-radius: 12px;
            padding: 10px 22px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-glass-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(99, 102, 241, 0.5);
            color: white;
        }

        @media print {
            .btn, .no-print { display: none !important; }
            body { background: white; }
            .glass-card { 
                background: white !important;
                backdrop-filter: none !important;
                border: none !important;
                box-shadow: none !important;
                padding: 20px !important;
            }
            .glass-card h1 { color: #0F172A !important; }
            .glass-card .subtitle { color: #6c757d !important; }
            .contact-info { background: #f8f9fa !important; border-color: #dee2e6 !important; }
            .contact-info p { color: #495057 !important; }
            .contact-info strong { color: #0F172A !important; }
        }

        @media (max-width: 768px) {
            .glass-card { padding: 24px; margin: 10px; }
            #qrcode { padding: 12px; }
            #qrcode canvas { width: 200px !important; height: 200px !important; }
        }
    </style>
</head>
<body>

    <div class="qr-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- ============================================ -->
    <!-- GLASS NAVBAR (Included from navbar.php)      -->
    <!-- ============================================ -->
    <?php include 'navbar.php'; ?>

    <div class="qr-wrapper">
        <div class="container">
            <div class="glass-card">
                <h1>🏷️ Campus Tag</h1>
                <div class="subtitle">Scan this QR code to save <?php echo htmlspecialchars($data['full_name']); ?>'s contact</div>

                <div id="qrcode"></div>

                <div class="contact-info">
                    <p><strong><?php echo htmlspecialchars($data['full_name']); ?></strong></p>
                    <p>📞 <?php echo htmlspecialchars($data['contact_number']); ?></p>
                    <p>🏛️ <?php echo htmlspecialchars($data['faculty']); ?></p>
                    <p>📬 Room <?php echo htmlspecialchars($data['room_number']); ?></p>
                    <p>✉️ <?php echo htmlspecialchars($data['email']); ?></p>
                </div>

                <div class="mt-3 d-flex gap-2 justify-content-center no-print">
                    <a href="campus_tag_manage.php" class="btn-glass-cancel">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <button onclick="window.print()" class="btn-glass-primary">
                        <i class="bi bi-printer"></i> Print QR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const vcard = <?php echo json_encode($vcard); ?>;
        new QRCode(document.getElementById("qrcode"), {
            text: vcard,
            width: 250,
            height: 250,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>