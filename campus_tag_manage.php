<?php
// campus_tag_manage.php – QR TAGS (Aeon Campus Design System)
ini_set('display_errors', 0);
error_reporting(0);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

$user_id = $_SESSION['user_id'];
$message = '';
$qr_data = null;

// Fetch user info
$user_stmt = $conn->prepare("SELECT full_name, email, unique_id FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Fetch existing tag data
$tag_stmt = $conn->prepare("SELECT * FROM campus_tag WHERE user_id = ?");
$tag_stmt->bind_param("i", $user_id);
$tag_stmt->execute();
$tag = $tag_stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_number = trim($_POST['contact_number']);
    $faculty = trim($_POST['faculty']);
    $room_number = trim($_POST['room_number']);
    $qr_type = isset($_POST['qr_type']) ? $_POST['qr_type'] : 'secure_link';

    if (empty($contact_number) || empty($faculty) || empty($room_number)) {
        $message = 'All fields are required.';
    } else {
        if ($tag) {
            $stmt = $conn->prepare("UPDATE campus_tag SET contact_number = ?, faculty = ?, room_number = ?, qr_type = ? WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssssi", $contact_number, $faculty, $room_number, $qr_type, $user_id);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO campus_tag (user_id, contact_number, faculty, room_number, qr_type) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issss", $user_id, $contact_number, $faculty, $room_number, $qr_type);
            }
        }
        
        if ($stmt && $stmt->execute()) {
            $message = '✅ Campus Tag generated successfully!';
            $tag = ['contact_number' => $contact_number, 'faculty' => $faculty, 'room_number' => $room_number, 'qr_type' => $qr_type];
        } else {
            $message = '❌ Error saving data.';
        }
    }
}

// Generate QR Payload based on selection
if ($tag) {
    $contact_number = $tag['contact_number'];
    
    $current_type = $tag['qr_type'] ?? 'secure_link';

    if ($current_type === 'whatsapp') {
        // Format for WhatsApp (Needs country code)
        $clean_phone = preg_replace('/[^0-9]/', '', $contact_number);
        if (substr($clean_phone, 0, 1) === '0') {
            $clean_phone = '60' . substr($clean_phone, 1);
        }
        $qr_data = "https://wa.me/" . $clean_phone . "?text=" . urlencode("Hi, I found an item that belongs to you via CampusFind!");
    
    } elseif ($current_type === 'call') {
        // Format for native Phone Dialer (Strip spaces/dashes, use exact number)
        $call_number = preg_replace('/[^0-9\+]/', '', $contact_number);
        $qr_data = "tel:" . $call_number;
    
    } else {
        // secure_link (Hidden Identity)
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://' . $_SERVER['HTTP_HOST'];
        $qr_data = $base . "/scan.php?tag=" . urlencode($user['unique_id']);
    }
}

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
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - Campus Tag</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    
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
                        "surface-container-lowest": "#0b0e15", "success": "#10B981"
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; font-family: 'Inter', sans-serif;}
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .input-glass { background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(140, 144, 159, 0.3); transition: all 0.3s ease; }
        .input-glass:focus-within, .input-glass.active-dropdown { border-color: #adc6ff !important; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2) !important; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 0 20px rgba(77, 142, 255, 0.2); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 0 30px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .btn-success-glass { background: linear-gradient(135deg, #10B981, #059669); border: none; box-shadow: 0 4px 16px rgba(16, 185, 129, 0.35); transition: all 0.3s ease;}
        .btn-success-glass:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; cursor: pointer;}
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }

        /* 🔥 HIDE PRINT CONTAINER NORMALLY 🔥 */
        #print-container { display: none; }

        /* ==========================================================================
           ROCK-SOLID PRINT CSS
           ========================================================================== */
        @media print {
            /* Hide headers/footers auto-injected by browsers */
            @page { margin: 0; size: auto; }
            
            body, html { 
                background: #ffffff !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                width: 100% !important; 
                height: 100% !important; 
            }
            
            /* Hide the normal website completely */
            body > *:not(#print-container) { display: none !important; }
            
            /* Show ONLY the print container */
            #print-container { 
                display: flex !important; 
                flex-direction: column !important; 
                align-items: center !important; 
                justify-content: center !important; 
                width: 100% !important; 
                height: 100vh !important; 
                background: white !important;
                visibility: visible !important;
            }

            /* The Perfect Print Sticker Card */
            .qr-print-area { 
                width: 350px !important; 
                padding: 30px !important; 
                border: 2px dashed #94a3b8 !important; /* Cut-out line */
                border-radius: 20px !important; 
                text-align: center !important; 
                background: #f8fafc !important; /* Light gray background */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin: 0 auto !important;
            }

            /* Make sure child elements render colors properly */
            .qr-print-area * { visibility: visible !important; color: #0f172a !important; }
            
            /* Typography specific to print */
            .print-brand { font-size: 28px !important; font-weight: 800 !important; color: #0f172a !important; margin-bottom: 2px !important; }
            .print-sub { font-size: 11px !important; font-weight: bold !important; text-transform: uppercase !important; letter-spacing: 2px !important; color: #4f46e5 !important; margin-bottom: 20px !important; display: block !important; }
            
            /* Clean QR Code Container */
            .print-qr-bg { 
                background: #ffffff !important; 
                border: 1px solid #cbd5e1 !important; 
                padding: 15px !important; 
                border-radius: 12px !important; 
                display: inline-block !important;
                margin: 0 auto 15px !important;
                box-shadow: none !important; 
                transform: none !important; 
            }

            /* Badge pill */
            .print-badge { 
                background-color: #e0e7ff !important; 
                color: #4f46e5 !important; 
                border: 1px solid #c7d2fe !important; 
                padding: 6px 16px !important; 
                border-radius: 20px !important;
                font-size: 11px !important;
                font-weight: bold !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px !important;
            }
            .print-badge span { color: #4f46e5 !important; }
            .print-desc { font-size: 12px !important; color: #64748b !important; margin-bottom: 10px !important; display: block !important;}
        }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-white relative min-h-screen pb-20 flex flex-col">

<!-- Global Background Shader -->
<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60 no-print">
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

<div class="no-print">
    <?php include 'navbar.php'; ?>
</div>

<main class="w-full max-w-6xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible no-print">

    <div class="glass-panel p-6 sm:p-10 rounded-2xl relative overflow-hidden">
        
        <div class="mb-8 border-b border-white/10 pb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="font-headline-lg text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-success text-4xl">qr_code_scanner</span> Campus Tag Settings
                </h1>
                <p class="text-on-surface-variant font-body-sm mt-2">Attach this QR code to your keys, bag, or wallet so finders can reach you.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl no-print <?php echo strpos($message, '✅') !== false ? 'bg-success/20 border-success/50 text-success' : 'bg-error-container/20 border-error/50 text-error'; ?> border text-sm font-medium flex items-center gap-2">
                <span class="material-symbols-outlined text-lg"><?php echo strpos($message, '✅') !== false ? 'check_circle' : 'error'; ?></span> 
                <?php echo str_replace(['✅', '❌'], '', $message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            
            <!-- FORM (Left) -->
            <div class="lg:col-span-7 no-print space-y-6">
                <form method="POST" class="space-y-6">
                    
                    <div>
                        <label class="block font-label-md text-on-surface-variant mb-2">Full Name</label>
                        <div class="relative input-glass rounded-xl flex items-center px-4 py-3 bg-white/5 opacity-70">
                            <span class="material-symbols-outlined text-outline mr-3">person</span>
                            <input type="text" class="w-full bg-transparent border-none text-on-surface focus:ring-0 p-0 cursor-not-allowed" value="<?php echo htmlspecialchars($user['full_name']); ?>" disabled>
                        </div>
                    </div>

                    <!-- Custom Dropdown: Scan Action Target -->
                    <div class="relative custom-dropdown" id="actionDropdownWrapper">
                        <label class="block font-label-md text-on-surface-variant mb-2">⚡ Scan Action Target <span class="text-error">*</span></label>
                        <input type="hidden" name="qr_type" id="actionInput" value="<?php echo htmlspecialchars($tag['qr_type'] ?? 'secure_link'); ?>">
                        
                        <div class="input-glass rounded-xl flex items-center px-4 py-3 cursor-pointer h-full border-l-4 border-l-primary" id="actionBtn">
                            <span class="material-symbols-outlined text-outline mr-3 pointer-events-none">touch_app</span>
                            <span id="actionSelectedText" class="w-full text-on-surface font-body-md select-none truncate pointer-events-none">
                                <?php 
                                    $current = $tag['qr_type'] ?? 'secure_link';
                                    if($current == 'secure_link') echo '🔒 Open System Chat (Hidden Identity)';
                                    elseif($current == 'whatsapp') echo '💬 Open Direct WhatsApp (Exposed Identity)';
                                    elseif($current == 'call') echo '📞 Direct Phone Call (Exposed Identity)';
                                ?>
                            </span>
                            <span class="material-symbols-outlined text-outline transition-transform duration-200 pointer-events-none" id="actionArrow">expand_more</span>
                        </div>
                        <p class="text-xs text-on-surface-variant mt-2">Select how you want finders to contact you when they scan the code.</p>

                        <div id="actionMenu" class="absolute left-0 right-0 top-[80px] mt-2 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden shadow-glass border border-white/10" style="transform: translateY(-10px);">
                            <div class="py-2">
                                <div class="px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors custom-option-action <?php echo $current == 'secure_link' ? 'bg-primary/20' : ''; ?>" data-value="secure_link" data-text="🔒 Open System Chat (Hidden Identity)">
                                    <div class="font-bold text-sm <?php echo $current == 'secure_link' ? 'text-primary' : 'text-white'; ?>">🔒 Open System Chat</div>
                                    <div class="text-[11px] text-on-surface-variant mt-0.5">Finders chat with you securely via CampusFind. Your number is hidden.</div>
                                </div>
                                <div class="px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors custom-option-action <?php echo $current == 'whatsapp' ? 'bg-primary/20' : ''; ?>" data-value="whatsapp" data-text="💬 Open Direct WhatsApp (Exposed Identity)">
                                    <div class="font-bold text-sm <?php echo $current == 'whatsapp' ? 'text-primary' : 'text-white'; ?>">💬 Open Direct WhatsApp</div>
                                    <div class="text-[11px] text-warning mt-0.5">Finders message your WhatsApp directly. Exposes phone number.</div>
                                </div>
                                <div class="px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors custom-option-action <?php echo $current == 'call' ? 'bg-primary/20' : ''; ?>" data-value="call" data-text="📞 Direct Phone Call (Exposed Identity)">
                                    <div class="font-bold text-sm <?php echo $current == 'call' ? 'text-primary' : 'text-white'; ?>">📞 Direct Phone Call</div>
                                    <div class="text-[11px] text-warning mt-0.5">Finders call you directly. Exposes phone number.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Dropdown: Faculty -->
                    <div class="relative custom-dropdown" id="facultyDropdownWrapper">
                        <label class="block font-label-md text-on-surface-variant mb-2">Faculty <span class="text-error">*</span></label>
                        <input type="hidden" name="faculty" id="facultyInput" value="<?php echo htmlspecialchars($tag['faculty'] ?? ''); ?>">
                        
                        <div class="input-glass rounded-xl flex items-center px-4 py-3 cursor-pointer h-full" id="facultyBtn">
                            <span class="material-symbols-outlined text-outline mr-3 pointer-events-none">school</span>
                            <span id="facultySelectedText" class="w-full text-on-surface font-body-md select-none truncate pointer-events-none <?php echo empty($tag['faculty']) ? 'text-outline-variant' : ''; ?>">
                                <?php echo empty($tag['faculty']) ? 'Select Faculty' : htmlspecialchars($tag['faculty']); ?>
                            </span>
                            <span class="material-symbols-outlined text-outline transition-transform duration-200 pointer-events-none" id="facultyArrow">expand_more</span>
                        </div>

                        <div id="facultyMenu" class="absolute left-0 right-0 top-full mt-2 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden shadow-glass border border-white/10" style="transform: translateY(-10px);">
                            <div class="max-h-56 overflow-y-auto custom-scrollbar py-2">
                                <?php
                                $faculties = [
                                    'Pusat Asasi Pertahanan', 
                                    'Fakulti Perubatan dan Kesihatan Pertahanan', 
                                    'Fakulti Kejuruteraan', 
                                    'Fakulti Sains dan Teknologi Pertahanan', 
                                    'Fakulti Pengajian dan Pengurusan Pertahanan', 
                                    'Pusat Bahasa'
                                ];
                                foreach($faculties as $fac) {
                                    $active = (($tag['faculty'] ?? '') == $fac) ? 'bg-primary/20 text-primary' : 'text-on-surface';
                                    echo "<div class='px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors custom-option-fac text-sm font-medium $active' data-value='$fac'>$fac</div>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block font-label-md text-on-surface-variant mb-2">Contact Number <span class="text-error">*</span></label>
                            <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                                <span class="material-symbols-outlined text-outline mr-3">call</span>
                                <input type="tel" name="contact_number" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 p-0" placeholder="e.g. 01155592611" value="<?php echo htmlspecialchars($tag['contact_number'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div>
                            <label class="block font-label-md text-on-surface-variant mb-2">Room / Office <span class="text-error">*</span></label>
                            <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                                <span class="material-symbols-outlined text-outline mr-3">meeting_room</span>
                                <input type="text" name="room_number" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 p-0" placeholder="e.g. Room 232" value="<?php echo htmlspecialchars($tag['room_number'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="btn-primary w-full py-4 rounded-xl font-bold text-white flex justify-center items-center gap-2 shadow-glow text-lg">
                            <span class="material-symbols-outlined text-[24px]">qr_code_scanner</span> Save & Generate Tag
                        </button>
                    </div>
                </form>
            </div>

            <!-- UI DISPLAY (Right / Non-Printable Preview) -->
            <div class="lg:col-span-5 flex flex-col items-center justify-center pt-8 lg:pt-0 border-t lg:border-t-0 lg:border-l border-white/10 lg:pl-10">
                <?php if ($tag && $qr_data): ?>
                    
                    <div class="flex flex-col items-center">
                        <div class="bg-white p-5 rounded-2xl shadow-[0_0_40px_rgba(255,255,255,0.15)] border-4 border-white/20 mb-6 transition-transform hover:scale-105">
                            <div id="qrcode-ui"></div>
                        </div>

                        <div class="text-center">
                            <h5 class="font-display-lg text-2xl font-bold text-white mb-1">CampusFind Tag</h5>
                            <p class="text-primary text-sm font-bold uppercase tracking-widest mb-3">Scan to return to owner</p>
                            
                            <?php if($tag['qr_type'] == 'secure_link'): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-success/20 text-success border border-success/30 text-xs font-bold"><span class="material-symbols-outlined text-[14px]">shield_lock</span> Secure Chat</span>
                            <?php elseif($tag['qr_type'] == 'whatsapp'): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/20 text-primary border border-primary/30 text-xs font-bold"><span class="material-symbols-outlined text-[14px]">forum</span> WhatsApp</span>
                            <?php elseif($tag['qr_type'] == 'call'): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/20 text-primary border border-primary/30 text-xs font-bold"><span class="material-symbols-outlined text-[14px]">call</span> Phone Call</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-8 flex gap-3 w-full">
                        <button onclick="window.print()" class="btn-success-glass flex-1 py-3.5 rounded-xl font-bold text-white flex justify-center items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">print</span> Print Tag
                        </button>
                        <button onclick="goBack('dashboard.php')" class="btn-outline-glass flex-1 py-3.5 rounded-xl font-bold text-center flex justify-center items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">arrow_back</span> Back
                        </button>
                    </div>
                
                <?php else: ?>
                    <div class="text-center text-white/40 py-12">
                        <span class="material-symbols-outlined text-6xl opacity-30 mb-4">qr_code_2</span>
                        <p class="text-sm font-medium max-w-[200px] mx-auto">Fill out the form and click "Save" to generate your printable QR tag.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</main>

<!-- ======================================================= -->
<!-- HIDDEN PRINT CONTAINER (ONLY VISIBLE DURING CTRL+P) -->
<!-- ======================================================= -->
<?php if ($tag && $qr_data): ?>
<div id="print-container">
    <div class="qr-print-area">
        <span class="print-desc">✂️ Cut along dashed line</span>
        <h5 class="print-brand">CampusFind</h5>
        <span class="print-sub">Smart Return Tag</span>
        
        <div class="print-qr-bg">
            <div id="qrcode-print"></div>
        </div>
        
        <span class="print-desc" style="font-weight:600;">Scan with camera to contact owner</span>
        
        <?php if($tag['qr_type'] == 'secure_link'): ?>
            <div class="print-badge">🔒 Secure Chat</div>
        <?php elseif($tag['qr_type'] == 'whatsapp'): ?>
            <div class="print-badge">💬 WhatsApp Link</div>
        <?php elseif($tag['qr_type'] == 'call'): ?>
            <div class="print-badge">📞 Phone Call</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
    // --- SMART BACK BUTTON SCRIPT ---
    function goBack(defaultUrl) {
        if (document.referrer && document.referrer.includes(window.location.hostname)) {
            if (document.referrer === window.location.href) {
                window.location.href = defaultUrl;
            } else {
                window.history.back();
            }
        } else {
            window.location.href = defaultUrl;
        }
    }

    // --- Profile Dropdown Setup (from navbar) ---
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isClosed = profileMenu.classList.contains('opacity-0');
            if (isClosed) {
                profileMenu.classList.remove('opacity-0', 'invisible');
                profileMenu.style.transform = 'translateY(0)';
            } else {
                profileMenu.classList.add('opacity-0', 'invisible');
                profileMenu.style.transform = 'translateY(-10px)';
            }
        });
        document.addEventListener('click', (e) => {
            if (!profileMenu.contains(e.target) && !profileBtn.contains(e.target)) {
                profileMenu.classList.add('opacity-0', 'invisible');
                profileMenu.style.transform = 'translateY(-10px)';
            }
        });
    }

    // --- CUSTOM DROPDOWN LOGIC ---
    function setupCustomDropdown(btnId, menuId, arrowId, inputId, textId, optionClass, activeClasses) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        const arrow = document.getElementById(arrowId);
        const input = document.getElementById(inputId);
        const text = document.getElementById(textId);
        const options = document.querySelectorAll(optionClass);
        const activeClassArray = activeClasses.split(' ');

        if(!btn) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            
            document.querySelectorAll('.glass-panel.absolute').forEach(m => {
                if(m.id !== menuId && m.id !== 'profileMenu' && m.id !== 'navReportMenu' && m.id !== 'navNotifMenu' && !m.classList.contains('opacity-0')) {
                    m.classList.add('opacity-0', 'invisible');
                    m.style.transform = 'translateY(-10px)';
                }
            });

            const isClosed = menu.classList.contains('opacity-0');
            if (isClosed) {
                menu.classList.remove('opacity-0', 'invisible');
                menu.style.transform = 'translateY(0)';
                if(arrow) arrow.style.transform = 'rotate(180deg)';
                btn.classList.add('border-primary');
            } else {
                closeMenu();
            }
        });

        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const value = option.getAttribute('data-value');
                input.value = value;
                
                let displayTxt = value;
                if(option.getAttribute('data-text')) {
                    displayTxt = option.getAttribute('data-text');
                } else if(option.querySelector('.font-bold')) {
                    displayTxt = option.querySelector('.font-bold').textContent;
                } else {
                    displayTxt = option.textContent.trim();
                }
                
                text.textContent = displayTxt;
                text.classList.remove('text-outline-variant');
                
                options.forEach(opt => {
                    opt.classList.remove(...activeClassArray);
                    const title = opt.querySelector('.font-bold');
                    if(title) { title.classList.remove('text-primary'); title.classList.add('text-white'); }
                    else { opt.classList.add('text-on-surface'); }
                });
                
                option.classList.add(...activeClassArray);
                const title = option.querySelector('.font-bold');
                if(title) { title.classList.remove('text-white'); title.classList.add('text-primary'); }
                else { option.classList.remove('text-on-surface'); }

                closeMenu();
            });
        });

        function closeMenu() {
            menu.classList.add('opacity-0', 'invisible');
            menu.style.transform = 'translateY(-10px)';
            if(arrow) arrow.style.transform = 'rotate(0deg)';
            btn.classList.remove('border-primary');
        }

        document.addEventListener('click', (e) => {
            if (!btn.contains(e.target) && !menu.contains(e.target)) {
                closeMenu();
            }
        });
    }

    setupCustomDropdown('actionBtn', 'actionMenu', 'actionArrow', 'actionInput', 'actionSelectedText', '.custom-option-action', 'bg-primary/20');
    setupCustomDropdown('facultyBtn', 'facultyMenu', 'facultyArrow', 'facultyInput', 'facultySelectedText', '.custom-option-fac', 'bg-primary/20 text-primary');

    // Fade in animations
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('visible'); } });
        }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in-up').forEach((el) => { observer.observe(el); });
    });
</script>

<?php if ($tag && $qr_data): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const qrPayload = `<?php echo addslashes($qr_data); ?>`;
        
        // Render UI version
        new QRCode(document.getElementById("qrcode-ui"), {
            text: qrPayload,
            width: 200,
            height: 200,
            colorDark: "#0F172A",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        // Render Print version
        new QRCode(document.getElementById("qrcode-print"), {
            text: qrPayload,
            width: 240,
            height: 240,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    });
</script>
<?php endif; ?>
</body>
</html>