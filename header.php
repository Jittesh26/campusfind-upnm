<?php
// header.php - Shared Top Boilerplate (With Mobile Optimization)
$page_title = $page_title ?? 'CampusFind';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Shared Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/custom-ui.css?v=<?php echo time(); ?>">

    <style>
        /* Shared Global Background Animation */
        .shared-bg { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(-45deg, #0F172A, #1E293B, #312E81, #0F172A); background-size: 400% 400%; animation: gradientShift 15s ease infinite; z-index: 0; }
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        /* Shared Floating Orbs */
        .orb { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.3; z-index: 0; pointer-events: none; animation: floatOrb 20s ease-in-out infinite; }
        .orb-1 { width: 400px; height: 400px; background: #6366F1; top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: #10B981; bottom: -50px; left: -50px; animation-delay: -5s; }
        .orb-3 { width: 200px; height: 200px; background: #EF4444; top: 50%; left: 50%; transform: translate(-50%, -50%); animation-delay: -10s; }
        @keyframes floatOrb { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(30px, -30px) scale(1.1); } 66% { transform: translate(-20px, 20px) scale(0.9); } }
        
        /* Master Content Wrapper */
        .main-wrapper { position: relative; z-index: 1; min-height: 100vh; padding-bottom: 30px; }

        /* ===================================================
           📱 GLOBAL MOBILE RESPONSIVENESS OVERRIDES
           =================================================== */
        @media (max-width: 768px) {
            /* 1. Fix Glass Cards (Reduce massive paddings) */
            .glass-card {
                padding: 20px 16px !important;
                margin-left: 10px !important;
                margin-right: 10px !important;
                border-radius: 16px !important;
            }

            /* 2. Stop iOS auto-zoom on forms */
            .form-control, .form-select, textarea {
                font-size: 16px !important; 
            }

            /* 3. Scale down Typography */
            h2 { font-size: 1.5rem !important; }
            h4 { font-size: 1.25rem !important; }

            /* 4. Full-width buttons for easy thumb tapping */
            .d-flex.gap-2.flex-wrap > button,
            .d-flex.gap-2.flex-wrap > a,
            .btn-glass-submit, .btn-glass-cancel, 
            .btn-glass-primary, .btn-glass-success {
                width: 100% !important;
                margin-bottom: 8px !important;
                justify-content: center !important;
            }

            /* 5. Fix Navbar spacing */
            .navbar-custom { padding: 8px 0 !important; }
            .navbar-brand { font-size: 1.2rem !important; }

            /* 6. Dashboard specific mobile fixes */
            .action-required-banner {
                flex-direction: column !important;
                text-align: center !important;
                gap: 12px !important;
                padding: 16px !important;
            }
            .kpi-grid { gap: 10px !important; }
            .kpi-card { padding: 14px !important; }
            .kpi-card .kpi-value { font-size: 1.6rem !important; }
            .kpi-card .kpi-icon { font-size: 1.4rem !important; margin-bottom: 4px !important; }
        }
    </style>
    
    <!-- Inject Page-Specific CSS here (if any) -->
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <!-- Render Background -->
    <div class="shared-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <?php 
    // Automatically include the Navbar if the user is logged in
    if (isset($_SESSION['user_id'])) {
        include 'navbar.php'; 
    } 
    ?>
    
    <!-- Start Main Page Content -->
    <div class="main-wrapper">