<?php
// item_detail.php – WITH HANDOVER PIN, DRY ARCHITECTURE & SMART BACK BUTTON
ini_set('display_errors', 0);
error_reporting(0);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';
require_once 'csrf.php';

$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';

// --- DB UPGRADE: Ensure handover_pin column exists ---
$check_pin = $conn->query("SHOW COLUMNS FROM item_reports LIKE 'handover_pin'");
if ($check_pin && $check_pin->num_rows == 0) {
    $conn->query("ALTER TABLE item_reports ADD COLUMN handover_pin VARCHAR(10) NULL AFTER status");
}

$verify_error = isset($_GET['error']) && $_GET['error'] === 'verify';
$pin_error = isset($_GET['error']) && $_GET['error'] === 'invalid_pin';

$stmt = $conn->prepare("SELECT ir.*, u.full_name, u.username, u.unique_id 
                        FROM item_reports ir 
                        JOIN users u ON ir.user_id = u.id 
                        WHERE ir.id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header('Location: dashboard.php');
    exit;
}

// Generate Handover PIN if it doesn't exist
if (empty($item['handover_pin'])) {
    $new_pin = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    $conn->query("UPDATE item_reports SET handover_pin = '$new_pin' WHERE id = $item_id");
    $item['handover_pin'] = $new_pin;
}

// Fetch all images
$img_stmt = $conn->prepare("SELECT image_path FROM item_images WHERE item_report_id = ? ORDER BY sort_order");
$img_stmt->bind_param("i", $item_id);
$img_stmt->execute();
$extra_images = $img_stmt->get_result();

$all_images = [];
if (!empty($item['image_path']) && file_exists($item['image_path'])) {
    $all_images[] = $item['image_path'];
}
while ($img = $extra_images->fetch_assoc()) {
    if (!empty($img['image_path']) && file_exists($img['image_path'])) {
        $all_images[] = $img['image_path'];
    }
}
$all_images = array_unique($all_images);

// Determine verification status
$is_verified = false;
if (isset($_SESSION['verified_chat'][$item_id])) {
    $verified_time = intval($_SESSION['verified_chat'][$item_id]);
    if (time() - $verified_time < 86400) { 
        $is_verified = true;
    } else {
        unset($_SESSION['verified_chat'][$item_id]); 
    }
}

$is_owner = ($item['user_id'] == $user_id);
$has_question = !empty($item['verification_question']);
$is_admin = ($role === 'admin');

// Status Styling Logic
$statusColor = 'bg-surface-container-highest text-on-surface-variant border-white/10';
if ($item['status'] === 'returned') $statusColor = 'bg-primary/20 text-primary border-primary/30';
elseif ($item['status'] === 'matched') $statusColor = 'bg-tertiary/20 text-tertiary border-tertiary/30';
elseif ($item['status'] === 'verifying') $statusColor = 'bg-warning/20 text-warning border-warning/30';

$typeColor = $item['report_type'] === 'lost' ? 'bg-error/20 text-error border-error/30' : 'bg-secondary/20 text-secondary border-secondary/30';
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - <?php echo htmlspecialchars($item['item_name']); ?></title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
                        "success": "#10B981", "warning": "#F59E0B"
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
        .btn-success-glass { background: linear-gradient(135deg, #10B981, #059669); border: none; box-shadow: 0 4px 16px rgba(16, 185, 129, 0.35); transition: all 0.3s ease;}
        .btn-success-glass:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        
        /* SweetAlert Dark Theme Overrides */
        .swal2-popup.dark-glass-modal { background: rgba(30, 41, 59, 0.95) !important; backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: #e1e2ec; font-family: 'Inter', sans-serif !important; }
        .swal2-title, .swal2-html-container { font-family: 'Inter', sans-serif !important; color: #ffffff !important; }
        .swal2-input { background: rgba(0,0,0,0.3) !important; border: 1px solid rgba(255,255,255,0.1) !important; color: white !important; }
        .swal2-input:focus { border-color: #6366F1 !important; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important; outline: none !important; }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-white relative min-h-screen pb-20 flex flex-col">

<!-- Global Background Shader -->
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

<!-- Tailwind Internal Navbar -->
<?php include 'navbar.php'; ?>

<!-- Main Content -->
<main class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">
    
    <!-- Top Nav / Breadcrumbs -->
    <div class="mb-6 flex justify-between items-center">
        <button onclick="goBack('dashboard.php')" class="btn-outline-glass px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back
        </button>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full border text-[10px] font-bold uppercase tracking-wider <?php echo $typeColor; ?>">
                <?php echo htmlspecialchars($item['report_type']); ?> Item
            </span>
            <span class="text-white/50 text-xs font-medium">Report #<?php echo $item['id']; ?></span>
        </div>
    </div>

    <!-- Main Card -->
    <div class="glass-panel p-6 sm:p-8 rounded-2xl relative overflow-hidden mb-8">
        
        <!-- Alerts -->
        <?php if ($verify_error): ?>
            <div class="mb-6 p-4 rounded-xl bg-warning/20 border border-warning/40 text-warning text-sm font-medium flex items-center gap-2 relative z-10">
                <span class="material-symbols-outlined text-lg">shield_lock</span> 
                Please answer the verification question to contact the owner.
            </div>
        <?php endif; ?>
        
        <?php if ($pin_error): ?>
            <div class="mb-6 p-4 rounded-xl bg-error/20 border border-error/40 text-error text-sm font-medium flex items-center gap-2 relative z-10">
                <span class="material-symbols-outlined text-lg">cancel</span> 
                Incorrect Handover PIN. Transaction unauthorized.
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
            
            <!-- Left: Image Gallery -->
            <div class="lg:col-span-5 flex flex-col gap-4">
                <div class="w-full aspect-square rounded-2xl bg-surface-container border border-white/10 flex items-center justify-center overflow-hidden relative shadow-glass">
                    <?php if (count($all_images) > 0): ?>
                        <img id="mainGalleryImage" src="<?php echo htmlspecialchars($all_images[0]); ?>" class="w-full h-full object-contain p-2" alt="Item Image">
                    <?php else: ?>
                        <span class="material-symbols-outlined text-6xl text-white/20">image_not_supported</span>
                        <p class="absolute bottom-6 text-white/40 text-sm">No Images Provided</p>
                    <?php endif; ?>
                </div>

                <!-- Thumbnails -->
                <?php if (count($all_images) > 1): ?>
                    <div class="flex gap-3 overflow-x-auto custom-scrollbar pb-2">
                        <?php foreach ($all_images as $index => $img): ?>
                            <button onclick="changeMainImage('<?php echo htmlspecialchars($img); ?>', this)" class="thumbnail-btn shrink-0 w-16 h-16 rounded-lg border-2 overflow-hidden transition-all <?php echo $index === 0 ? 'border-primary opacity-100' : 'border-transparent opacity-60 hover:opacity-100'; ?>">
                                <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover" alt="Thumbnail">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Details & Actions -->
            <div class="lg:col-span-7 flex flex-col">
                <div class="flex justify-between items-start mb-2">
                    <h2 class="font-display-lg text-3xl font-bold text-white tracking-tight leading-tight"><?php echo htmlspecialchars($item['item_name']); ?></h2>
                </div>
                
                <div class="mb-6 flex">
                    <span class="px-3 py-1 rounded-full border text-xs font-bold uppercase tracking-wider <?php echo $statusColor; ?>">
                        Status: <?php echo htmlspecialchars($item['status']); ?>
                    </span>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 gap-y-6 gap-x-4 mb-8">
                    <div>
                        <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-1">Category</div>
                        <div class="text-white font-medium flex items-center gap-1.5 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-primary">category</span>
                            <?php echo htmlspecialchars($item['category']); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-1">Date Logged</div>
                        <div class="text-white font-medium flex items-center gap-1.5 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-primary">calendar_month</span>
                            <?php echo formatDateSafe($item['report_date']); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-1">Location</div>
                        <div class="text-white font-medium flex items-center gap-1.5 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-tertiary">location_on</span>
                            <?php echo htmlspecialchars($item['location'] ?: 'Pinned on Map'); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-1">Reported By</div>
                        <div class="text-white font-medium flex items-center gap-1.5 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-secondary">person</span>
                            <?php echo htmlspecialchars($item['unique_id'] ?: $item['full_name']); ?>
                        </div>
                    </div>
                </div>

                <!-- Description Box -->
                <div class="mb-8">
                    <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-2">Description</div>
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4 text-white/90 text-sm leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                    </div>
                </div>

                <!-- Owner Handover PIN Display -->
                <?php if ($is_owner && $item['status'] !== 'returned'): ?>
                    <div class="bg-secondary/10 border-2 border-dashed border-secondary/40 rounded-xl p-5 text-center mb-8 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-r from-transparent via-secondary/10 to-transparent animate-[shimmer_2s_infinite]"></div>
                        <div class="text-[10px] text-secondary uppercase tracking-widest font-bold mb-1 flex justify-center items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">key</span> Your Handover PIN
                        </div>
                        <h2 class="font-display-lg text-4xl text-white tracking-[0.2em] font-bold mb-1"><?php echo htmlspecialchars($item['handover_pin']); ?></h2>
                        <p class="text-xs text-secondary/70">Share this PIN only with the finder to authorize the return.</p>
                    </div>
                <?php endif; ?>

                <!-- Verification Question Box -->
                <?php if ($has_question): ?>
                    <div class="bg-primary/10 border-l-4 border-primary rounded-xl p-4 mb-8">
                        <div class="text-primary font-bold text-sm mb-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">shield_lock</span> Verification Challenge
                        </div>
                        <p class="italic text-white/90 text-sm m-0">"<?php echo htmlspecialchars($item['verification_question']); ?>"</p>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="mt-auto space-y-3">
                    <?php if (!$is_owner): ?>
                        
                        <?php if ($has_question && !$is_verified): ?>
                            <button type="button" onclick="openVerificationModal()" class="btn-primary w-full py-4 rounded-xl font-bold text-white flex justify-center items-center gap-2 shadow-glow">
                                <span class="material-symbols-outlined text-[20px]">lock_open</span> Answer to Contact Owner
                            </button>
                        <?php else: ?>
                            <a href="messages.php?item=<?php echo $item['id']; ?>&amp;other=<?php echo $item['user_id']; ?>" class="btn-primary w-full py-4 rounded-xl font-bold text-white flex justify-center items-center gap-2 shadow-glow">
                                <span class="material-symbols-outlined text-[20px]">chat</span> Secure Chat with Owner
                            </a>
                        <?php endif; ?>

                    <?php else: ?>
                        
                        <button type="button" onclick="openFinderSelector()" class="btn-primary w-full py-4 rounded-xl font-bold text-white flex justify-center items-center gap-2 shadow-glow">
                            <span class="material-symbols-outlined text-[20px]">chat</span> Chat with a Finder
                        </button>
                    
                    <?php endif; ?>

                    <?php if (($is_owner || $is_admin) && $item['status'] !== 'returned'): ?>
                        <form id="returnForm" method="POST" action="mark_returned.php" class="hidden">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="handover_pin" id="handover_pin_input" value="">
                            <?php csrfInput(); ?>
                        </form>
                        <button type="button" onclick="markReturned(<?php echo $is_admin ? 'true' : 'false'; ?>)" class="btn-success-glass w-full py-4 rounded-xl font-bold text-white flex justify-center items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">task_alt</span> Authorize Return
                        </button>
                    <?php endif; ?>

                    <?php if ($is_owner): ?>
                        <form method="POST" action="delete_report.php" onsubmit="return confirmDelete(event, this);">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <?php csrfInput(); ?>
                            <button type="submit" class="btn-outline-glass w-full py-3.5 rounded-xl font-bold text-error border-error/20 hover:bg-error/10 hover:border-error hover:text-error transition-all flex justify-center items-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">delete</span> Delete Report
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($item['status'] === 'returned'): ?>
                        <a href="generate_receipt.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn-primary w-full py-4 rounded-xl font-bold text-white flex justify-center items-center gap-2 shadow-glow">
                            <span class="material-symbols-outlined text-[20px]">receipt_long</span> Download Official Receipt
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</main>

<script>
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

    // --- SMART BACK BUTTON ---
    function goBack(defaultUrl) {
        defaultUrl = defaultUrl || 'dashboard.php';
        const ref = document.referrer;
        if (ref && ref.includes(window.location.hostname)) {
            if (ref.includes('generate_receipt.php') || ref.includes('mark_returned.php') || ref.includes('verify_claim.php')) {
                window.location.href = defaultUrl;
            } else {
                window.history.back();
            }
        } else {
            window.location.href = defaultUrl;
        }
    }

    // --- IMAGE GALLERY LOGIC ---
    function changeMainImage(src, btn) {
        document.getElementById('mainGalleryImage').src = src;
        document.querySelectorAll('.thumbnail-btn').forEach(el => {
            el.classList.remove('border-primary', 'opacity-100');
            el.classList.add('border-transparent', 'opacity-60');
        });
        btn.classList.remove('border-transparent', 'opacity-60');
        btn.classList.add('border-primary', 'opacity-100');
    }

    // --- SWEETALERT CONFIRMATIONS ---
    function confirmDelete(e, form) {
        e.preventDefault();
        Swal.fire({
            title: 'Delete this report?',
            text: "This action cannot be undone. All attached images will be permanently removed.",
            icon: 'warning',
            iconColor: '#EF4444',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Yes, delete it!',
            background: '#1E293B',
            color: '#FFFFFF',
            customClass: { popup: 'dark-glass-modal' }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
        return false;
    }

    // 🔒 HANDOVER PIN LOGIC
    function markReturned(isAdmin) {
        let htmlContent = "<div class='text-sm text-white/70 mb-4'>Confirm that this item has been successfully returned to its rightful owner.</div>";
        
        if (isAdmin !== 'true') {
            htmlContent += "<div class='bg-warning/10 border border-warning/30 p-3 rounded-lg text-warning text-xs text-left mb-4'>Please enter the 4-digit <b>Handover PIN</b> (shown above) to authorize this transaction.</div>";
        }

        Swal.fire({
            title: 'Authorize Handover',
            html: htmlContent,
            input: isAdmin === 'true' ? null : 'text',
            inputPlaceholder: 'Enter 4-digit PIN',
            icon: 'shield',
            showCancelButton: true,
            confirmButtonColor: '#10B981',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Authorize & Receipt',
            background: '#1E293B',
            color: '#FFFFFF',
            customClass: { popup: 'dark-glass-modal' },
            preConfirm: (pin) => {
                if (isAdmin !== 'true' && !pin) {
                    Swal.showValidationMessage('Handover PIN is required!');
                }
                return pin;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('handover_pin_input').value = result.value || '';
                
                Swal.fire({
                    title: 'Generating Receipt...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    background: '#1E293B',
                    color: '#FFFFFF',
                    customClass: { popup: 'dark-glass-modal' },
                    didOpen: () => { Swal.showLoading(); }
                });

                document.getElementById('returnForm').submit();
            }
        });
    }

    function openVerificationModal() {
        Swal.fire({
            title: '<span class="material-symbols-outlined text-3xl text-primary align-middle">shield_lock</span> Verification',
            html: `
                <p class="text-white/60 text-sm mb-4">The owner set a verification challenge to prevent false claims:</p>
                <div class="p-4 mb-4 text-left rounded-xl bg-primary/10 border-l-4 border-primary">
                    <strong class="text-primary text-xs uppercase tracking-wider block mb-1">Question:</strong>
                    <div class="text-white text-sm">"<?php echo htmlspecialchars($item['verification_question'] ?? '', ENT_QUOTES); ?>"</div>
                </div>
                <input type="text" id="claimAnswer" class="swal2-input !w-full !m-0 !text-sm" placeholder="Type your answer here..." autocomplete="off">
            `,
            showCancelButton: true,
            confirmButtonText: 'Verify & Chat',
            confirmButtonColor: '#6366F1',
            cancelButtonColor: '#64748B',
            background: '#1E293B',
            color: '#FFFFFF',
            customClass: { popup: 'dark-glass-modal' },
            didOpen: () => { document.getElementById('claimAnswer').focus(); },
            preConfirm: () => {
                const answer = document.getElementById('claimAnswer').value.trim();
                if (!answer) { Swal.showValidationMessage('Please enter an answer!'); return false; }
                const formData = new FormData();
                formData.append('item_id', '<?php echo $item['id']; ?>');
                formData.append('answer', answer);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

                return fetch('verify_claim.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Incorrect answer. Please try again.');
                    return data;
                })
                .catch(error => { Swal.showValidationMessage(error.message); });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ icon: 'success', title: 'Verified!', text: 'Opening live chat...', timer: 1200, showConfirmButton: false, background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } })
                .then(() => { window.location.href = 'messages.php?item=<?php echo $item['id']; ?>&other=<?php echo $item['user_id']; ?>'; });
            }
        });
    }

    function openFinderSelector() {
        fetch('get_finders.php?item_id=<?php echo $item['id']; ?>')
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.finders || data.finders.length === 0) {
                    Swal.fire({ icon: 'info', title: 'No Finders Yet', text: 'No one has contacted you about this item yet.', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } });
                    return;
                }
                let listHtml = '<div class="text-left space-y-2 mt-4">';
                data.finders.forEach(f => {
                    const displayName = f.unique_id;
                    listHtml += `
                        <button class="w-full text-left bg-white/5 hover:bg-white/10 border border-white/10 hover:border-primary/50 transition-all rounded-xl p-4 flex items-center justify-between group" onclick="window.location.href='messages.php?item=<?php echo $item['id']; ?>&other=${f.id}'">
                            <span class="text-white font-bold text-sm flex items-center gap-2"><span class="material-symbols-outlined text-primary">person</span> User ${displayName}</span>
                            <span class="material-symbols-outlined text-white/30 group-hover:text-white transition-colors">chevron_right</span>
                        </button>`;
                });
                listHtml += '</div>';
                Swal.fire({ title: 'Select a Conversation', html: listHtml, showConfirmButton: false, showCloseButton: true, background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } });
            })
            .catch(() => { Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load finders.', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } }); });
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($verify_error && $has_question && !$is_verified): ?>
            openVerificationModal();
        <?php endif; ?>
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('visible'); } });
        }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in-up').forEach((el) => { observer.observe(el); });
    });
</script>
</body>
</html>