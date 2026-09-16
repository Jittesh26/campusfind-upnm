<?php
// review_matches.php – AI SIDE-BY-SIDE MATCH HUB (Aeon Campus Design System)
ini_set('display_errors', 0);
error_reporting(0);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db_connect.php';
$user_id = $_SESSION['user_id'];

// Auto-create table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS ai_match_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_item_id INT NOT NULL,
    matched_item_id INT NOT NULL,
    score INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle Accept / Reject Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_id'])) {
    $log_id = intval($_POST['log_id']);
    $action = $_POST['action'] ?? '';

    if ($action === 'accept') {
        $conn->query("UPDATE ai_match_logs SET status = 'accepted' WHERE id = $log_id");
        $other_user = intval($_POST['other_user']);
        $my_item = intval($_POST['my_item']);
        header("Location: messages.php?item=$my_item&other=$other_user");
        exit;
    } elseif ($action === 'reject') {
        $conn->query("UPDATE ai_match_logs SET status = 'rejected' WHERE id = $log_id");
        header("Location: review_matches.php?rejected=1");
        exit;
    }
}

// Fetch Pending Matches for this user
$sql = "SELECT aml.*, 
           ir1.item_name as my_item_name, ir1.report_type as my_type, ir1.image_path as my_image, ir1.description as my_desc, ir1.location as my_loc, ir1.id as my_item_id,
           ir2.item_name as other_item_name, ir2.report_type as other_type, ir2.image_path as other_image, ir2.description as other_desc, ir2.location as other_loc, ir2.user_id as other_user_id, ir2.id as other_item_id, u.unique_id as other_unique_id
        FROM ai_match_logs aml
        JOIN item_reports ir1 ON (ir1.id = aml.original_item_id OR ir1.id = aml.matched_item_id)
        JOIN item_reports ir2 ON (ir2.id = aml.original_item_id OR ir2.id = aml.matched_item_id)
        JOIN users u ON ir2.user_id = u.id
        WHERE ir1.user_id = ? AND ir2.user_id != ? AND ir1.id != ir2.id AND aml.status = 'pending'
        GROUP BY aml.id
        ORDER BY aml.score DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$matches = $stmt->get_result();

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
    <title>CampusFind - Review Matches</title>
    
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
                        "surface-container-lowest": "#0b0e15", "success": "#10B981", "warning": "#F59E0B"
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
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        .shadow-glow { box-shadow: 0 0 20px rgba(173, 198, 255, 0.3); }
        
        .btn-accept { background: linear-gradient(135deg, #10B981, #059669); border: none; box-shadow: 0 4px 16px rgba(16, 185, 129, 0.35); transition: all 0.3s ease; }
        .btn-accept:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5); }
        
        .btn-reject { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #FCA5A5; transition: all 0.3s ease; }
        .btn-reject:hover { background: rgba(239, 68, 68, 0.25); color: white; transform: translateY(-2px); }

        /* SweetAlert Dark Theme Overrides */
        .swal2-popup.dark-glass-modal { background: rgba(30, 41, 59, 0.95) !important; backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: #e1e2ec; font-family: 'Inter', sans-serif !important; }
        .swal2-title, .swal2-html-container { font-family: 'Inter', sans-serif !important; color: #ffffff !important; }
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

<main class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">
    
    <!-- Header -->
    <div class="mb-8 border-b border-white/10 pb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-headline-lg text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-4xl">robot_2</span> Review AI Matches
            </h1>
            <p class="text-on-surface-variant font-body-sm mt-2">The system has flagged these items as potential matches.</p>
        </div>
        <a href="dashboard.php" class="btn-outline-glass px-4 py-2 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Dashboard
        </a>
    </div>

    <!-- Alert Message -->
    <?php if (isset($_GET['rejected'])): ?>
        <div class="mb-6 p-4 rounded-xl bg-success/20 border border-success/40 text-success text-sm font-medium flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">check_circle</span> 
            Match dismissed successfully.
        </div>
    <?php endif; ?>

    <!-- Matches Loop -->
    <?php if ($matches->num_rows > 0): ?>
        <div class="space-y-10">
            <?php while ($row = $matches->fetch_assoc()): ?>
                <div class="glass-panel p-6 sm:p-8 rounded-2xl relative overflow-hidden group">
                    
                    <div class="flex flex-col md:flex-row gap-6 md:gap-12 relative">
                        
                        <!-- Left Card: Your Item -->
                        <div class="flex-1 bg-white/5 border border-white/10 rounded-xl p-5 text-center border-t-4 border-t-primary flex flex-col hover:bg-white/10 transition-colors relative">
                            <span class="absolute top-3 right-3 bg-primary/20 text-primary px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Your Item</span>
                            
                            <div class="w-28 h-28 mx-auto rounded-xl bg-surface-container border border-white/10 mb-4 flex items-center justify-center overflow-hidden shadow-lg">
                                <?php if (!empty($row['my_image']) && file_exists($row['my_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['my_image']); ?>" class="w-full h-full object-cover" alt="My Item">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-4xl text-white/20">image</span>
                                <?php endif; ?>
                            </div>
                            
                            <h4 class="font-bold text-white text-lg mb-2"><?php echo htmlspecialchars($row['my_item_name']); ?></h4>
                            <div class="flex items-center justify-center gap-3 text-xs text-on-surface-variant mb-4">
                                <span class="px-2 py-0.5 rounded border border-white/10 uppercase tracking-wider font-bold"><?php echo htmlspecialchars($row['my_type']); ?></span>
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-tertiary">location_on</span> <?php echo htmlspecialchars($row['my_loc'] ?: 'Pinned'); ?></span>
                            </div>
                            <p class="text-sm text-white/70 italic bg-black/20 p-3 rounded-lg flex-1 text-left line-clamp-3">
                                "<?php echo htmlspecialchars($row['my_desc']); ?>"
                            </p>
                        </div>

                        <!-- Center: VS Orb (Absolute on Desktop, Flex on Mobile) -->
                        <div class="md:absolute md:left-1/2 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 flex flex-col items-center justify-center z-10 my-2 md:my-0">
                            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-success flex flex-col items-center justify-center text-white border-4 border-[#191b23] shadow-[0_0_30px_rgba(16,185,129,0.4)]">
                                <span class="font-display-lg text-2xl font-bold leading-none"><?php echo $row['score']; ?>%</span>
                                <span class="text-[9px] uppercase tracking-widest font-bold opacity-90 mt-0.5">Match</span>
                            </div>
                        </div>

                        <!-- Right Card: Potential Match -->
                        <div class="flex-1 bg-white/5 border border-white/10 rounded-xl p-5 text-center border-t-4 border-t-success flex flex-col hover:bg-white/10 transition-colors relative">
                            <span class="absolute top-3 left-3 bg-success/20 text-success px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Candidate</span>
                            
                            <div class="w-28 h-28 mx-auto rounded-xl bg-surface-container border border-white/10 mb-4 flex items-center justify-center overflow-hidden shadow-lg">
                                <?php if (!empty($row['other_image']) && file_exists($row['other_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['other_image']); ?>" class="w-full h-full object-cover" alt="Other Item">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-4xl text-white/20">image</span>
                                <?php endif; ?>
                            </div>
                            
                            <h4 class="font-bold text-white text-lg mb-2"><?php echo htmlspecialchars($row['other_item_name']); ?></h4>
                            <div class="flex items-center justify-center gap-3 text-xs text-on-surface-variant mb-4">
                                <span class="px-2 py-0.5 rounded border border-white/10 uppercase tracking-wider font-bold"><?php echo htmlspecialchars($row['other_type']); ?></span>
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">person</span> User <?php echo htmlspecialchars($row['other_unique_id']); ?></span>
                            </div>
                            <p class="text-sm text-white/70 italic bg-black/20 p-3 rounded-lg flex-1 text-left line-clamp-3">
                                "<?php echo htmlspecialchars($row['other_desc']); ?>"
                            </p>
                        </div>

                    </div>

                    <!-- AI Reason Box -->
                    <div class="mt-6 bg-primary/5 border border-primary/20 rounded-xl p-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary text-[24px]">tips_and_updates</span>
                        <div>
                            <h6 class="text-xs uppercase tracking-widest text-primary font-bold mb-1">AI Reasoning</h6>
                            <p class="text-sm text-white/90 leading-relaxed"><?php echo htmlspecialchars($row['reason']); ?></p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 border-t border-white/10 pt-6">
                        <form method="POST" class="flex flex-col sm:flex-row gap-4 justify-center">
                            <input type="hidden" name="log_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="other_user" value="<?php echo $row['other_user_id']; ?>">
                            <input type="hidden" name="my_item" value="<?php echo $row['my_item_id']; ?>">
                            
                            <button type="submit" name="action" value="reject" class="btn-reject w-full sm:w-[250px] py-3 rounded-xl font-bold flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">cancel</span> Not a Match
                            </button>
                            <button type="submit" name="action" value="accept" class="btn-accept w-full sm:w-[250px] py-3 rounded-xl font-bold text-white flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">chat</span> Contact Finder
                            </button>
                        </form>
                    </div>

                </div>
            <?php endwhile; ?>
        </div>

    <?php else: ?>
        <!-- Empty State -->
        <div class="glass-panel p-10 rounded-2xl flex flex-col items-center justify-center text-center">
            <div class="w-24 h-24 bg-success/10 border border-success/30 rounded-full flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-success text-5xl">task_alt</span>
            </div>
            <h3 class="text-white font-headline-md text-2xl mb-2">You're all caught up!</h3>
            <p class="text-on-surface-variant text-sm max-w-md mb-8">You have no pending AI matches to review. We will notify you if a new match is discovered.</p>
            <a href="dashboard.php" class="btn-outline-glass px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">home</span> Return to Dashboard
            </a>
        </div>
    <?php endif; ?>

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