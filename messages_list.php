<?php
// messages_list.php – PER-PAIR CONVERSATION LIST (Aeon Campus Design System)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

$user_id = intval($_SESSION['user_id']);

// For each item, find all distinct other participants the user has exchanged messages with
$sql = "SELECT 
            ir.id AS item_id,
            ir.item_name,
            ir.report_type,
            ir.status,
            other_user.id AS other_user_id,
            other_user.unique_id AS other_unique_id,
            COUNT(cm.id) AS msg_count,
            SUM(CASE WHEN cm.is_read = 0 AND cm.receiver_id = ? THEN 1 ELSE 0 END) AS unread_count,
            MAX(cm.sent_at) AS last_msg_time
        FROM item_reports ir
        INNER JOIN chat_messages cm ON ir.id = cm.item_report_id
        INNER JOIN users other_user ON (
            CASE 
                WHEN cm.sender_id = ? THEN cm.receiver_id
                ELSE cm.sender_id
            END = other_user.id
        )
        WHERE (? IN (cm.sender_id, cm.receiver_id))
        GROUP BY ir.id, other_user.id
        ORDER BY last_msg_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result();

// Get unread count for navbar badge (global)
$unread_query = "SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = ? AND is_read = 0";
$u_stmt = $conn->prepare($unread_query);
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$unreadCount = $u_stmt->get_result()->fetch_assoc()['count'] ?? 0;
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - My Conversations</title>
    
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
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; cursor: pointer; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-white relative min-h-screen pb-20 flex flex-col">

<!-- Global Background Shader (RESTORED ANIMATION) -->
<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60">
    <div class="absolute inset-0 w-full h-full"><canvas id="shader-canvas-ANIMATION_8" class="w-full h-full"></canvas></div>
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
          const gl = canvas.getContext('webgl');
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
          const uTime = gl.getUniformLocation(prog, 'u_time');
          function render(t) { gl.viewport(0, 0, canvas.width, canvas.height); if (uTime) gl.uniform1f(uTime, t * 0.001); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); requestAnimationFrame(render); }
          render(0);
        })();
    </script>
</div>

<!-- Tailwind Internal Navbar -->
<?php include 'navbar.php'; ?>

<!-- Main Content -->
<main class="w-full max-w-4xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">
    
    <div class="glass-panel p-6 sm:p-8 rounded-2xl relative overflow-hidden">
        
        <!-- Header -->
        <div class="mb-6 border-b border-white/10 pb-4 flex justify-between items-center">
            <div>
                <h1 class="font-headline-lg text-2xl sm:text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-3xl">chat</span> My Conversations
                </h1>
                <p class="text-on-surface-variant font-body-sm mt-1">Manage your active private chats with item finders and owners.</p>
            </div>
        </div>

        <!-- Chat List -->
        <div class="space-y-3">
            <?php if ($conversations->num_rows > 0): ?>
                <?php while ($row = $conversations->fetch_assoc()): 
                    $typeClass = ($row['report_type'] === 'lost') ? 'bg-error/20 text-error border-error/30' : 'bg-secondary/20 text-secondary border-secondary/30';
                    $other_display = htmlspecialchars($row['other_unique_id']);
                    $hasUnread = $row['unread_count'] > 0;
                ?>
                    <a href="messages.php?item=<?php echo $row['item_id']; ?>&other=<?php echo $row['other_user_id']; ?>" class="block p-4 sm:p-5 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 hover:border-primary/50 transition-all group relative">
                        <div class="flex items-center gap-4 sm:gap-5">
                            
                            <!-- Left: Avatar/Icon -->
                            <div class="w-12 h-12 rounded-full bg-surface-container-high border border-white/10 flex items-center justify-center shrink-0 shadow-glass group-hover:scale-105 transition-transform">
                                <span class="material-symbols-outlined text-primary text-[24px]">person</span>
                            </div>

                            <!-- Center: Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-bold text-white text-base sm:text-lg truncate group-hover:text-primary transition-colors">
                                        <?php echo htmlspecialchars($row['item_name']); ?>
                                    </h3>
                                    <?php if ($hasUnread): ?>
                                        <span class="bg-error text-white text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider animate-pulse">
                                            <?php echo $row['unread_count']; ?> New
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-sm text-on-surface-variant">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">chat_bubble</span> 
                                        with <strong class="text-white ml-0.5">User <?php echo $other_display; ?></strong>
                                    </span>
                                    <span class="hidden sm:inline text-white/20">•</span>
                                    <span class="px-2 py-0.5 rounded border text-[9px] font-bold uppercase tracking-wider <?php echo $typeClass; ?>">
                                        <?php echo ucfirst($row['report_type']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Right: Stats & Arrow -->
                            <div class="flex flex-col sm:flex-row items-end sm:items-center gap-2 sm:gap-4 shrink-0">
                                <div class="bg-primary/10 border border-primary/20 text-primary px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[14px]">forum</span> <?php echo $row['msg_count']; ?>
                                </div>
                                <span class="material-symbols-outlined text-white/30 group-hover:text-primary group-hover:translate-x-1 transition-all hidden sm:block">chevron_right</span>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-20 h-20 bg-surface-container-highest border border-white/10 rounded-full flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-on-surface-variant text-4xl">forum</span>
                    </div>
                    <h3 class="text-white font-headline-md text-xl mb-2">No Active Conversations</h3>
                    <p class="text-on-surface-variant text-sm max-w-sm mb-6">When you start messaging about a lost or found item, your private chats will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-8 border-t border-white/10 pt-6">
            <button onclick="goBack('dashboard.php')" class="btn-outline-glass px-6 py-2.5 rounded-xl font-bold text-sm inline-flex items-center gap-2 w-full sm:w-auto justify-center">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back
            </button>
        </div>
    </div>
</main>

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

    // Fade in animations
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('visible'); } });
        }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in-up').forEach((el) => { observer.observe(el); });
    });
</script>
</body>
</html>