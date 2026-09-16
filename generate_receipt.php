<?php
// generate_receipt.php – Modern Client-Side PDF Receipt
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Please log in.');
}

require 'db_connect.php';

$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';

if ($item_id <= 0) {
    die('Invalid item ID.');
}

// Fetch report details
$stmt = $conn->prepare("SELECT ir.*, u.full_name, u.email, u.unique_id 
                        FROM item_reports ir 
                        JOIN users u ON ir.user_id = u.id 
                        WHERE ir.id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    die('Item not found.');
}

// Security Check
if ($item['user_id'] != $user_id && $role !== 'admin') {
    die('Receipt is only available to authorized users or admins.');
}

// Safe Date Formatting
$formattedDate = 'N/A';
if (!empty($item['report_date']) && $item['report_date'] !== '0000-00-00') {
    $timestamp = strtotime($item['report_date']);
    if ($timestamp && $timestamp > 0) {
        $formattedDate = date('d M Y', $timestamp);
    }
} elseif (!empty($item['created_at'])) {
    $timestamp = strtotime($item['created_at']);
    if ($timestamp && $timestamp > 0) {
        $formattedDate = date('d M Y', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusFind Receipt #CF-<?php echo str_pad($item['id'], 6, '0', STR_PAD_LEFT); ?></title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- html2pdf Library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0F172A;
            color: #F8FAFC;
            min-height: 100vh;
            padding: 30px 15px;
        }

        .receipt-wrapper {
            max-width: 720px;
            margin: 0 auto;
        }

        /* Modern Glass Controls Header */
        .controls-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 14px 20px;
            margin-bottom: 20px;
        }

        /* Crisp Official Document Card */
        .receipt-card {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 44px;
            color: #1E293B;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .receipt-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, #6366F1, #10B981);
        }

        .receipt-brand span {
            color: #6366F1;
            font-weight: 800;
        }

        .badge-resolved {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            background: #F8FAFC;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            margin-bottom: 24px;
        }

        .info-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #0F172A;
        }

        .desc-section {
            background: #F8FAFC;
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
        }
    </style>
</head>
<body>

    <div class="receipt-wrapper">
        
        <!-- Controls Bar (Hidden in PDF Output) -->
        <div class="controls-card d-flex justify-content-between align-items-center">
            <button onclick="if(window.opener || window.history.length <= 1){ window.close(); } else { window.location.href='item_detail.php?id=<?php echo $item['id']; ?>'; }" class="btn btn-outline-light btn-sm px-3 rounded-3">
                <i class="bi bi-x-lg me-1"></i> Close Tab
            </button>
            <div class="d-flex gap-2">
                <button onclick="downloadPDF()" class="btn btn-primary btn-sm fw-semibold rounded-3 px-3" style="background: linear-gradient(135deg, #6366F1, #4F46E5); border: none;">
                    <i class="bi bi-download me-1"></i> Save as PDF
                </button>
                <button onclick="window.print()" class="btn btn-light btn-sm fw-semibold rounded-3 px-3">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>

        <!-- RECEIPT CONTAINER (Converted to PDF) -->
        <div id="receiptArea" class="receipt-card">
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4 border-secondary border-opacity-10">
                <div>
                    <h3 class="fw-extrabold receipt-brand mb-1">🔍 Campus<span>Find</span></h3>
                    <div class="small text-muted fw-semibold">Universiti Pertahanan Nasional Malaysia</div>
                </div>
                <div class="text-end">
                    <span class="badge-resolved">
                        <i class="bi bi-check-circle-fill me-1"></i> RESOLVED &amp; RETURNED
                    </span>
                    <div class="small fw-bold text-secondary mt-2">REF: #CF-<?php echo str_pad($item['id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>

            <h5 class="fw-bold mb-3 text-dark">Official Resolution Receipt</h5>

            <!-- Grid Layout Details -->
            <div class="info-grid">
                <div>
                    <div class="info-label">Item Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($item['item_name']); ?></div>
                </div>
                <div>
                    <div class="info-label">Category</div>
                    <div class="info-value"><?php echo htmlspecialchars($item['category']); ?></div>
                </div>
                <div>
                    <div class="info-label">Report Type</div>
                    <div class="info-value"><?php echo strtoupper($item['report_type']); ?></div>
                </div>
                <div>
                    <div class="info-label">Location</div>
                    <div class="info-value"><?php echo htmlspecialchars($item['location'] ?: 'Pinned Location'); ?></div>
                </div>
                <div>
                    <div class="info-label">Date Logged</div>
                    <div class="info-value"><?php echo $formattedDate; ?></div>
                </div>
                <div>
                    <div class="info-label">Reported By</div>
                    <div class="info-value"><?php echo htmlspecialchars($item['unique_id'] . ' (' . $item['full_name'] . ')'); ?></div>
                </div>
            </div>

            <!-- Description -->
            <div class="desc-section mb-4">
                <div class="info-label">Item Description</div>
                <div class="info-value fw-normal text-secondary mt-1"><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
            </div>

            <!-- Verification Footer -->
            <div class="pt-3 border-top text-center text-muted small border-secondary border-opacity-10">
                <p class="mb-1 fw-semibold text-dark"><i class="bi bi-shield-check text-success me-1"></i> Official System Verification Record</p>
                <div style="font-size: 0.78rem;">Generated on: <?php echo date('d M Y, h:i A'); ?></div>
            </div>

        </div>

    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('receiptArea');
            const opt = {
                margin:       10,
                filename:     'CampusFind_Receipt_<?php echo $item['id']; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save();
        }

        // Auto download ONLY if "download=1" is in the URL
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('download')) {
                setTimeout(downloadPDF, 800);
            }
        });
    </script>
</body>
</html>