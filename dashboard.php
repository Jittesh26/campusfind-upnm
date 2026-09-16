<?php
// dashboard.php – PREMIUM SAAS DASHBOARD (Aeon Campus Design System)
ini_set('display_errors', 0);
error_reporting(0);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db_connect.php';
$user_id = $_SESSION['user_id'];
$userInitial = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 1)) : 'U';

// --- DB UPGRADE: Ensure ai_match_logs table exists ---
$conn->query("CREATE TABLE IF NOT EXISTS ai_match_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_item_id INT NOT NULL,
    matched_item_id INT NOT NULL,
    score INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// -------------------------------
// Data Fetching Logic
// -------------------------------
$image_column = 'image_path';
$check = $conn->query("SHOW COLUMNS FROM item_reports LIKE 'image_path'");
if ($check && $check->num_rows == 0) {
    $image_column = 'image';
    $check2 = $conn->query("SHOW COLUMNS FROM item_reports LIKE 'image'");
    if ($check2 && $check2->num_rows == 0) $image_column = '';
}

// Global KPIs
$counts = ['lost' => 0, 'found' => 0, 'my_matches' => 0, 'messages' => 0];
$res = $conn->query("SELECT COUNT(*) FROM item_reports WHERE report_type = 'lost'");
if ($res) $counts['lost'] = $res->fetch_row()[0] ?? 0;

$res = $conn->query("SELECT COUNT(*) FROM item_reports WHERE report_type = 'found'");
if ($res) $counts['found'] = $res->fetch_row()[0] ?? 0;

// User's AI Matches
$stmt = $conn->prepare("SELECT COUNT(DISTINCT aml.id) FROM ai_match_logs aml 
                        JOIN item_reports ir ON (aml.original_item_id = ir.id OR aml.matched_item_id = ir.id) 
                        WHERE ir.user_id = ? AND aml.status = 'pending'");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($counts['my_matches']);
    $stmt->fetch();
    $stmt->close();
}

// User's Unread Messages
$stmt = $conn->prepare("SELECT COUNT(*) FROM chat_messages cm JOIN item_reports ir ON cm.item_report_id = ir.id WHERE cm.sender_id != ? AND cm.is_read = 0 AND (ir.user_id = ? OR ir.id IN (SELECT item_report_id FROM chat_messages WHERE sender_id = ?))");
if ($stmt) {
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($counts['messages']);
    $stmt->fetch();
    $stmt->close();
}

// My Active Reports
$my_active_reports = [];
$selectFields = "id, item_name, report_type, status, created_at";
if (!empty($image_column)) $selectFields .= ", " . $image_column . " as image_path";
$stmt = $conn->prepare("SELECT $selectFields FROM item_reports WHERE user_id = ? AND status != 'returned' ORDER BY created_at DESC LIMIT 6");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!isset($row['image_path'])) $row['image_path'] = '';
        $my_active_reports[] = $row;
    }
    $stmt->close();
}

// Activity Timeline
$activities = [];
$stmt = $conn->prepare("SELECT 'report' as type, CONCAT('Reported item: ', item_name) as title, created_at FROM item_reports WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { while ($row = $res->fetch_assoc()) { $activities[] = $row; } }
    $stmt->close();
}

$dateCol = 'sent_at';
$stmt = $conn->prepare("SELECT DISTINCT 'message' as type, 'Sent a chat message' as title, cm.$dateCol as created_at FROM chat_messages cm JOIN item_reports ir ON cm.item_report_id = ir.id WHERE cm.sender_id = ? OR ir.user_id = ? ORDER BY cm.$dateCol DESC LIMIT 10");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { while ($row = $res->fetch_assoc()) { $activities[] = $row; } }
    $stmt->close();
}

usort($activities, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
$activities = array_slice($activities, 0, 10);

function groupActivities($activities) {
    $groups = [];
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    foreach ($activities as $act) {
        $date = date('Y-m-d', strtotime($act['created_at']));
        if ($date == $today) $groups['Today'][] = $act;
        elseif ($date == $yesterday) $groups['Yesterday'][] = $act;
        else $groups[formatDateSafe($act['created_at'])][] = $act;
    }
    return $groups;
}
$activityGroups = groupActivities($activities);
$full_name = $_SESSION['full_name'] ?? 'User';

// Helper for status badges
function getStatusBadge($status) {
    switch(strtolower($status)) {
        case 'matched': return 'bg-primary/20 text-primary border-primary/30';
        case 'verifying': return 'bg-tertiary/20 text-tertiary border-tertiary/30';
        case 'returned': return 'bg-secondary/20 text-secondary border-secondary/30';
        default: return 'bg-surface-container-high text-on-surface-variant border-outline/30';
    }
}
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - Dashboard</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface-container-low": "#191b23", "surface": "#10131a", "on-background": "#e1e2ec",
                        "primary-fixed-dim": "#adc6ff", "outline": "#8c909f", "primary-container": "#4d8eff",
                        "on-surface": "#e1e2ec", "surface-container-highest": "#32353c", "primary-fixed": "#d8e2ff",
                        "surface-container": "#1d2027", "inverse-primary": "#005ac2", "secondary-fixed": "#62fae3",
                        "on-surface-variant": "#c2c6d6", "surface-container-high": "#272a31", "error-container": "#93000a",
                        "on-primary": "#002e6a", "background": "#10131a", "primary": "#adc6ff", "outline-variant": "#424754",
                        "error": "#ffb4ab", "secondary-container": "#03c6b2", "tertiary": "#ffb786", "secondary": "#44e2cd",
                        "surface-container-lowest": "#0b0e15"
                    },
                    "fontFamily": {
                        "headline-md": ["Inter"], "body-lg": ["Inter"], "label-md": ["Inter"],
                        "label-sm": ["Inter"], "headline-lg": ["Inter"], "body-md": ["Inter"], "display-lg": ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; }
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 0 20px rgba(77, 142, 255, 0.2); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 0 30px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        .pulse-dot { animation: pulse-glow 2s infinite; }
        @keyframes pulse-glow { 0% { box-shadow: 0 0 0 0 rgba(77, 142, 255, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(77, 142, 255, 0); } 100% { box-shadow: 0 0 0 0 rgba(77, 142, 255, 0); } }
        .hover-glow:hover { box-shadow: 0 0 30px rgba(173, 198, 255, 0.15), 0 8px 32px 0 rgba(0, 0, 0, 0.37); border-color: rgba(173, 198, 255, 0.3); transform: translateY(-2px); }
        
        /* Banner Animation */
        .alert-banner { animation: alertPulse 3s infinite alternate; }
        @keyframes alertPulse { 0% { box-shadow: 0 0 0 rgba(223, 116, 18, 0); } 100% { box-shadow: 0 0 25px rgba(223, 116, 18, 0.3); } }

        /* 🔥 FIX: Custom Scrollbar (Sleek & Thin) 🔥 */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(140, 144, 159, 0.4) transparent;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent; 
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(140, 144, 159, 0.3); 
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(173, 198, 255, 0.5); /* primary glow on hover */
        }

        /* Map Override */
        .leaflet-container { background: #191b23; font-family: 'Inter', sans-serif; }
        .leaflet-popup-content-wrapper { background: rgba(30, 41, 59, 0.95); color: #e1e2ec; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); border-radius: 12px; }
        .leaflet-popup-tip { background: rgba(30, 41, 59, 0.95); }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-on-primary-container relative min-h-screen pb-20">

<!-- Global Background Shader (Moving Animation 8) -->
<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60">
    <div class="absolute inset-0 w-full h-full" style="display:block;">
        <canvas id="shader-canvas-ANIMATION_8" style="display:block;width:100%;height:100%"></canvas>
        <script>
        (function() {
          const canvas = document.getElementById('shader-canvas-ANIMATION_8');
          function syncSize() {
            const w = canvas.clientWidth  || 1280;
            const h = canvas.clientHeight || 720;
            if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
          }
          if (typeof ResizeObserver !== 'undefined') { new ResizeObserver(syncSize).observe(canvas); }
          syncSize();
          const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
          if (!gl) return;
          const vs = `attribute vec2 a_position; varying vec2 v_texCoord; void main() { v_texCoord = a_position * 0.5 + 0.5; gl_Position = vec4(a_position, 0.0, 1.0); }`;
          const fs = `precision highp float; uniform float u_time; uniform vec2 u_resolution; varying vec2 v_texCoord;
          vec3 permute(vec3 x) { return mod(((x*34.0)+1.0)*x, 289.0); }
          float snoise(vec2 v){ const vec4 C = vec4(0.211324865405187, 0.366025403784439, -0.577350269189626, 0.024390243902439); vec2 i=floor(v+dot(v, C.yy)); vec2 x0=v-i+dot(i, C.xx); vec2 i1; i1=(x0.x>x0.y)?vec2(1.,0.):vec2(0.,1.); vec4 x12=x0.xyxy+C.xxzz; x12.xy-=i1; i=mod(i, 289.0); vec3 p=permute(permute(i.y+vec3(0.,i1.y,1.))+i.x+vec3(0.,i1.x,1.)); vec3 m=max(0.5-vec3(dot(x0,x0),dot(x12.xy,x12.xy),dot(x12.zw,x12.zw)),0.0); m=m*m; m=m*m; vec3 x=2.0*fract(p*C.www)-1.0; vec3 h=abs(x)-0.5; vec3 ox=floor(x+0.5); vec3 a0=x-ox; m*=1.79284291400159-0.85373472095314*(a0*a0+h*h); vec3 g; g.x=a0.x*x0.x+h.x*x0.y; g.yz=a0.yz*x12.xz+h.yz*x12.yw; return 130.0*dot(m, g); }
          void main() { vec2 uv = v_texCoord; float n1 = snoise(uv * 2.0 + u_time * 0.05); float n2 = snoise(uv * 4.0 - u_time * 0.08); float n3 = snoise(uv * 8.0 + u_time * 0.12); vec3 baseColor = vec3(0.043, 0.055, 0.082); vec3 accentColor1 = vec3(0.231, 0.510, 0.965); vec3 accentColor2 = vec3(0.176, 0.831, 0.749); float mask = smoothstep(-0.2, 0.8, (n1 + n2 * 0.5)); vec3 color = mix(baseColor, accentColor1, mask * 0.4); float lines = sin(uv.y * 50.0 + n3 * 2.0 + u_time) * 0.5 + 0.5; color += accentColor2 * lines * pow(n3, 3.0) * 0.15; gl_FragColor = vec4(color, 1.0); }`;
          function cs(type, src) { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
          const prog = gl.createProgram(); gl.attachShader(prog, cs(gl.VERTEX_SHADER, vs)); gl.attachShader(prog, cs(gl.FRAGMENT_SHADER, fs)); gl.linkProgram(prog); gl.useProgram(prog);
          const buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
          const pos = gl.getAttribLocation(prog, 'a_position'); gl.enableVertexAttribArray(pos); gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);
          const uTime = gl.getUniformLocation(prog, 'u_time'); const uRes = gl.getUniformLocation(prog, 'u_resolution');
          function render(t) { if (typeof ResizeObserver === 'undefined') syncSize(); gl.viewport(0, 0, canvas.width, canvas.height); if (uTime) gl.uniform1f(uTime, t * 0.001); if (uRes) gl.uniform2f(uRes, canvas.width, canvas.height); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); requestAnimationFrame(render); }
          render(0);
        })();
        </script>
    </div>
    <div class="absolute inset-0 bg-gradient-radial from-transparent to-background/90"></div>
</div>

<?php include 'navbar.php'; ?>

<!-- Main Dashboard Content -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-20 fade-in-up visible">
    
    <!-- Hero Section -->
    <div class="mb-10">
        <div class="text-primary font-label-sm tracking-widest uppercase mb-2 flex items-center gap-2">
            <span class="material-symbols-outlined text-[16px]">waving_hand</span> Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </div>
        <h1 class="font-display-lg text-4xl sm:text-5xl font-bold text-white tracking-tight">Smart Command Center</h1>
    </div>

    <!-- Action Required Banner -->
    <?php if ($counts['messages'] > 0 || $counts['my_matches'] > 0): ?>
        <div class="glass-panel alert-banner border-tertiary/40 p-6 rounded-2xl mb-10 flex flex-col sm:flex-row items-center gap-6">
            <div class="w-16 h-16 rounded-full bg-tertiary/20 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-tertiary text-3xl">notifications_active</span>
            </div>
            <div class="flex-1 text-center sm:text-left">
                <h3 class="font-headline-md text-xl text-tertiary mb-1">Action Required</h3>
                <p class="text-on-surface-variant font-body-md">
                    <?php if ($counts['messages'] > 0): ?>
                        You have <strong class="text-white"><?php echo $counts['messages']; ?></strong> unread message(s). <a href="messages_list.php" class="text-primary hover:text-primary-fixed underline ml-2 transition-colors">View Chats</a><br>
                    <?php endif; ?>
                    <?php if ($counts['my_matches'] > 0): ?>
                        AI has found <strong class="text-white"><?php echo $counts['my_matches']; ?></strong> potential match(es) for your items! <a href="review_matches.php" class="text-primary hover:text-primary-fixed underline ml-2 transition-colors">Review Matches</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-10">
        <div class="glass-panel p-6 rounded-2xl hover-glow transition-all">
            <div class="flex justify-between items-start mb-4">
                <span class="material-symbols-outlined text-error text-3xl">local_mall</span>
                <span class="text-outline-variant text-xs uppercase tracking-wider font-bold">Global</span>
            </div>
            <div class="font-display-lg text-4xl text-white mb-1 counter" data-target="<?php echo $counts['lost']; ?>">0</div>
            <div class="font-label-sm text-on-surface-variant uppercase tracking-wider">Lost Reports</div>
        </div>
        <div class="glass-panel p-6 rounded-2xl hover-glow transition-all">
            <div class="flex justify-between items-start mb-4">
                <span class="material-symbols-outlined text-secondary text-3xl">task_alt</span>
                <span class="text-outline-variant text-xs uppercase tracking-wider font-bold">Global</span>
            </div>
            <div class="font-display-lg text-4xl text-white mb-1 counter" data-target="<?php echo $counts['found']; ?>">0</div>
            <div class="font-label-sm text-on-surface-variant uppercase tracking-wider">Found Reports</div>
        </div>
        <div class="glass-panel p-6 rounded-2xl hover-glow transition-all relative overflow-hidden">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary/10 rounded-full blur-xl"></div>
            <div class="flex justify-between items-start mb-4 relative z-10">
                <span class="material-symbols-outlined text-primary text-3xl">memory</span>
                <span class="text-outline-variant text-xs uppercase tracking-wider font-bold">Personal</span>
            </div>
            <div class="font-display-lg text-4xl text-white mb-1 counter relative z-10" data-target="<?php echo count($my_active_reports); ?>">0</div>
            <div class="font-label-sm text-on-surface-variant uppercase tracking-wider relative z-10">My Active Items</div>
        </div>
        <div class="glass-panel p-6 rounded-2xl hover-glow transition-all">
            <div class="flex justify-between items-start mb-4">
                <div class="relative">
                    <span class="material-symbols-outlined text-tertiary text-3xl">mail</span>
                    <?php if($counts['messages'] > 0): ?><div class="absolute top-0 right-0 w-3 h-3 bg-error rounded-full border-2 border-surface pulse-dot"></div><?php endif; ?>
                </div>
                <span class="text-outline-variant text-xs uppercase tracking-wider font-bold">Inbox</span>
            </div>
            <div class="font-display-lg text-4xl text-white mb-1 counter" data-target="<?php echo $counts['messages']; ?>">0</div>
            <div class="font-label-sm text-on-surface-variant uppercase tracking-wider">Unread Messages</div>
        </div>
    </div>

    <!-- Middle Row: Quick Actions & Map -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
        
        <!-- Quick Actions -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl flex flex-col h-full">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-tertiary">bolt</span>
                <h2 class="font-headline-md text-xl text-white">Quick Actions</h2>
            </div>
            <div class="grid grid-cols-2 gap-4 flex-1">
                <a href="report_lost.php" class="bg-surface-container-high/50 border border-white/5 rounded-xl p-5 hover:bg-white/10 hover:border-error/50 transition-all group flex flex-col justify-between">
                    <span class="material-symbols-outlined text-error text-3xl mb-4 group-hover:scale-110 transition-transform">warning</span>
                    <div>
                        <h3 class="font-label-md text-white mb-1">Report Lost</h3>
                        <p class="text-xs text-on-surface-variant">Log a missing item</p>
                    </div>
                </a>
                <a href="report_found.php" class="bg-surface-container-high/50 border border-white/5 rounded-xl p-5 hover:bg-white/10 hover:border-secondary/50 transition-all group flex flex-col justify-between">
                    <span class="material-symbols-outlined text-secondary text-3xl mb-4 group-hover:scale-110 transition-transform">check_circle</span>
                    <div>
                        <h3 class="font-label-md text-white mb-1">Report Found</h3>
                        <p class="text-xs text-on-surface-variant">Log a discovered item</p>
                    </div>
                </a>
                <a href="ai_matching.php" class="bg-surface-container-high/50 border border-white/5 rounded-xl p-5 hover:bg-white/10 hover:border-primary/50 transition-all group flex flex-col justify-between">
                    <span class="material-symbols-outlined text-primary text-3xl mb-4 group-hover:scale-110 transition-transform">memory</span>
                    <div>
                        <h3 class="font-label-md text-white mb-1">AI Match</h3>
                        <p class="text-xs text-on-surface-variant">Run a neural scan</p>
                    </div>
                </a>
                <a href="campus_tag_manage.php" class="bg-surface-container-high/50 border border-white/5 rounded-xl p-5 hover:bg-white/10 hover:border-tertiary/50 transition-all group flex flex-col justify-between">
                    <span class="material-symbols-outlined text-tertiary text-3xl mb-4 group-hover:scale-110 transition-transform">qr_code</span>
                    <div>
                        <h3 class="font-label-md text-white mb-1">Campus Tag</h3>
                        <p class="text-xs text-on-surface-variant">Manage privacy QR</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Live Map -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl flex flex-col h-full">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-secondary">map</span>
                    <h2 class="font-headline-md text-xl text-white">Live Campus Map</h2>
                </div>
                <a href="map.php" class="text-xs font-bold text-primary hover:text-white uppercase tracking-wider transition-colors">Expand</a>
            </div>
            <div id="mapPreview" class="w-full rounded-xl overflow-hidden shadow-inner border border-white/10 flex-1 relative z-0 min-h-[220px]"></div>
            <div class="flex justify-between items-center mt-4 text-sm text-on-surface-variant">
                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-error"></span> <?php echo $counts['lost']; ?> Lost Pins</div>
                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-secondary"></span> <?php echo $counts['found']; ?> Found Pins</div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Data Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- My Active Reports -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl h-[450px] flex flex-col">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">inventory_2</span>
                    <h2 class="font-headline-md text-xl text-white">My Active Reports</h2>
                </div>
                <a href="my_reports.php" class="text-xs font-bold text-primary hover:text-white uppercase tracking-wider transition-colors">View All</a>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-3">
                <?php if (count($my_active_reports) > 0): ?>
                    <?php foreach ($my_active_reports as $report): ?>
                        <a href="item_detail.php?id=<?php echo $report['id']; ?>" class="flex items-center gap-4 p-3 rounded-xl bg-surface-container-high/40 border border-white/5 hover:bg-white/10 transition-all group">
                            <?php if (!empty($report['image_path']) && file_exists($report['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($report['image_path']); ?>" alt="Item" class="w-12 h-12 rounded-lg object-cover border border-white/10 group-hover:scale-105 transition-transform">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-lg bg-black/30 border border-white/10 flex items-center justify-center text-white/30 group-hover:scale-105 transition-transform"><span class="material-symbols-outlined">image</span></div>
                            <?php endif; ?>
                            
                            <div class="flex-1 min-w-0">
                                <h4 class="text-white font-label-md truncate mb-1"><?php echo htmlspecialchars($report['item_name']); ?></h4>
                                <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider">
                                    <span class="px-2 py-0.5 rounded-full border <?php echo $report['report_type'] == 'lost' ? 'bg-error/20 text-error border-error/30' : 'bg-secondary/20 text-secondary border-secondary/30'; ?>">
                                        <?php echo $report['report_type']; ?>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full border <?php echo getStatusBadge($report['status']); ?>">
                                        <?php echo $report['status']; ?>
                                    </span>
                                    <span class="text-on-surface-variant lowercase font-normal tracking-normal"><?php echo formatDateSafe($report['created_at']); ?></span>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-outline group-hover:text-white transition-colors">chevron_right</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center text-center opacity-60">
                        <span class="material-symbols-outlined text-5xl mb-3">inbox</span>
                        <p class="text-on-surface-variant font-body-md">No active reports found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="glass-panel p-6 sm:p-8 rounded-2xl h-[450px] flex flex-col">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-secondary">history</span>
                <h2 class="font-headline-md text-xl text-white">Activity Timeline</h2>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 relative">
                <?php if (!empty($activityGroups)): ?>
                    <div class="absolute left-[19px] top-4 bottom-4 w-px bg-white/10 z-0"></div>
                    <div class="relative z-10">
                        <?php foreach ($activityGroups as $label => $items): ?>
                            <div class="mb-4">
                                <span class="inline-block px-3 py-1 rounded-full bg-surface-container-highest border border-white/5 text-[10px] font-bold text-primary uppercase tracking-wider mb-3 ml-8"><?php echo $label; ?></span>
                                <div class="space-y-4">
                                    <?php foreach ($items as $act): ?>
                                        <div class="flex gap-4 group">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 border border-surface bg-surface-container-highest group-hover:bg-white/10 transition-colors z-10 <?php echo $act['type'] == 'report' ? 'text-tertiary' : 'text-primary'; ?>">
                                                <span class="material-symbols-outlined text-[18px]"><?php echo $act['type'] == 'report' ? 'description' : 'chat'; ?></span>
                                            </div>
                                            <div class="pt-2">
                                                <p class="text-sm font-medium text-white mb-0.5"><?php echo htmlspecialchars($act['title']); ?></p>
                                                <p class="text-xs text-on-surface-variant"><?php echo date('g:i A', strtotime($act['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center text-center opacity-60">
                        <span class="material-symbols-outlined text-5xl mb-3">update</span>
                        <p class="text-on-surface-variant font-body-md">No recent activity.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // --- VANILLA JS DROPDOWN LOGIC ---
    function setupDropdown(btnId, menuId) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        if(!btn || !menu) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isClosed = menu.classList.contains('opacity-0');
            // Close all other menus first
            document.querySelectorAll('.glass-panel.absolute').forEach(m => {
                if(m.id !== menuId) { m.classList.add('opacity-0', 'invisible'); m.style.transform = 'translateY(-10px)'; }
            });
            
            if (isClosed) {
                menu.classList.remove('opacity-0', 'invisible');
                menu.style.transform = 'translateY(0)';
            } else {
                menu.classList.add('opacity-0', 'invisible');
                menu.style.transform = 'translateY(-10px)';
            }
        });
    }

    setupDropdown('reportDesktopBtn', 'reportDesktopMenu');
    setupDropdown('notifBtn', 'notifMenu');
    setupDropdown('profileBtn', 'profileMenu');

    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if(mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if(mobileMenu.classList.contains('opacity-0')) {
                mobileMenu.classList.remove('opacity-0', 'invisible');
            } else {
                mobileMenu.classList.add('opacity-0', 'invisible');
            }
        });
    }

    // Close dropdowns on outside click
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.glass-panel.absolute').forEach(menu => {
            if (!menu.contains(e.target) && menu.id !== 'mobileMenu') {
                menu.classList.add('opacity-0', 'invisible');
                menu.style.transform = 'translateY(-10px)';
            }
        });
        if(mobileMenu && !mobileMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            mobileMenu.classList.add('opacity-0', 'invisible');
        }
    });

    // --- SMART POLLING (Native JS) ---
    const notifBadge = document.getElementById('navNotifBadge');
    const notifList = document.getElementById('notifList');
    const msgBadge = document.getElementById('navMsgBadge');
    
    function fetchNotifications() {
        fetch('notifications_ajax.php?action=get_notifications_html')
            .then(res => res.json())
            .then(data => { if(data.success && notifList) notifList.innerHTML = data.html; })
            .catch(() => {});
        
        fetch('notifications_ajax.php?action=get_unread_count')
            .then(res => res.json())
            .then(data => {
                if(data.success && notifBadge) {
                    if(data.count > 0) { notifBadge.textContent = data.count; notifBadge.classList.remove('hidden'); }
                    else { notifBadge.classList.add('hidden'); }
                }
            }).catch(() => {});
    }

    function fetchMessagesCount() {
        fetch('messages_ajax.php?action=get_unread_count')
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    if(msgBadge) {
                        if(data.count > 0) { msgBadge.textContent = data.count; msgBadge.classList.remove('hidden'); }
                        else { msgBadge.classList.add('hidden'); }
                    }
                    const subBadge = document.getElementById('unreadMessagesCount');
                    if(subBadge) subBadge.textContent = data.count;
                }
            }).catch(() => {});
    }

    if(document.getElementById('notifBtn')) {
        document.getElementById('notifBtn').addEventListener('click', () => {
            fetchNotifications();
            // Mark read API call
            fetch('notifications_ajax.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=mark_all_read' });
        });
    }

    fetchNotifications();
    fetchMessagesCount();
    setInterval(() => { if(!document.hidden) { fetchNotifications(); fetchMessagesCount(); } }, 15000);

    // --- LEAFLET MAP ---
    var map = L.map('mapPreview', { zoomControl: false, attributionControl: false }).setView([3.0480, 101.7250], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.marker([3.0480, 101.7250]).addTo(map).bindPopup('<b class="text-surface">UPNM Campus</b>');
    map.scrollWheelZoom.disable();
    map.dragging.disable(); // Make it a true preview

    // --- NUMBER ANIMATION ---
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = parseInt(counter.dataset.target);
        let current = 0;
        const increment = Math.max(1, target / 30);
        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counter.textContent = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        };
        updateCounter();
    });
</script>
</body>
</html>