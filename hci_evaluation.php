<?php
// hci_evaluation.php – FYP Usability Testing Dashboard (Aeon Campus Design System)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

$conn->query("CREATE TABLE IF NOT EXISTS hci_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    task_completion_time FLOAT,
    errors INT,
    satisfaction INT,
    sus_score INT,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $time = floatval($_POST['time']);
    $errors = intval($_POST['errors']);
    $satisfaction = intval($_POST['satisfaction']);
    $sus = intval($_POST['sus']);
    $comments = trim($_POST['comments']);
    
    $stmt = $conn->prepare("INSERT INTO hci_results (user_id, task_completion_time, errors, satisfaction, sus_score, comments) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idiiis", $user_id, $time, $errors, $satisfaction, $sus, $comments);
    if ($stmt->execute()) {
        $message = 'success';
    }
}

$results = $conn->query("SELECT * FROM hci_results ORDER BY created_at DESC");
$avg_sus = $conn->query("SELECT AVG(sus_score) as avg_sus FROM hci_results")->fetch_assoc()['avg_sus'] ?? 0;
$avg_sus = round($avg_sus, 1);
$total_tests = $results->num_rows;
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - HCI Evaluation</title>
    
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
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 0 20px rgba(77, 142, 255, 0.2); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 0 30px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        
        .input-glass { background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(140, 144, 159, 0.3); transition: all 0.3s ease; }
        .input-glass:focus { border-color: #adc6ff; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2); outline: none; }

        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

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

<!-- Main Content -->
<main class="w-full max-w-7xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">

    <div class="glass-panel p-6 sm:p-10 rounded-2xl relative overflow-hidden">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 border-b border-white/10 pb-6">
            <div>
                <h1 class="font-headline-lg text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-4xl">science</span> Usability Testing (HCI)
                </h1>
                <p class="text-on-surface-variant font-body-sm mt-1">FYP Evaluation Data: Log and track System Usability Scale (SUS) metrics.</p>
            </div>
            <a href="admin_dashboard.php" class="btn-outline-glass px-6 py-2.5 rounded-xl font-bold text-sm inline-flex items-center gap-2 justify-center">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span> Admin Dashboard
            </a>
        </div>

        <!-- Academic KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            
            <!-- Global SUS Score -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6 flex items-center gap-6 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-9xl">speed</span>
                </div>
                <div class="w-16 h-16 rounded-full flex items-center justify-center border-4 z-10 shrink-0 <?php echo $avg_sus >= 68 ? 'bg-success/20 border-success text-success' : 'bg-warning/20 border-warning text-warning'; ?>">
                    <span class="font-display-lg text-2xl font-bold"><?php echo $avg_sus; ?></span>
                </div>
                <div class="z-10">
                    <h3 class="font-headline-md text-base text-white/50 uppercase tracking-widest font-bold mb-1">Average SUS Score</h3>
                    <div class="flex items-center gap-2">
                        <span class="text-white text-lg font-bold">
                            <?php echo $avg_sus >= 80 ? 'Excellent' : ($avg_sus >= 68 ? 'Good / Acceptable' : 'Needs Improvement'); ?>
                        </span>
                        <span class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wider <?php echo $avg_sus >= 68 ? 'bg-success/20 border-success/30 text-success' : 'bg-warning/20 border-warning/30 text-warning'; ?>">
                            <?php echo $avg_sus >= 68 ? 'PASS' : 'MARGINAL'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Total Testers -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6 flex items-center gap-6 relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-9xl">group</span>
                </div>
                <div class="w-16 h-16 rounded-full bg-primary/20 border-4 border-primary text-primary flex items-center justify-center z-10 shrink-0">
                    <span class="material-symbols-outlined text-[32px]">groups</span>
                </div>
                <div class="z-10">
                    <h3 class="font-headline-md text-base text-white/50 uppercase tracking-widest font-bold mb-1">Evaluation Sample Size</h3>
                    <div class="flex items-center gap-2">
                        <span class="text-white text-2xl font-bold"><?php echo $total_tests; ?></span>
                        <span class="text-white/70 text-sm">Users Tested</span>
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Left: Log New Test Data -->
            <div class="lg:col-span-4 bg-white/5 border border-white/10 rounded-xl p-6 h-fit">
                <h3 class="font-headline-md text-lg text-white mb-6 flex items-center gap-2 border-b border-white/10 pb-4">
                    <span class="material-symbols-outlined text-primary">add_circle</span> Log Test Result
                </h3>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block font-label-md text-on-surface-variant mb-1.5 text-sm">Task Time (seconds)</label>
                        <input type="number" step="0.1" name="time" class="w-full input-glass rounded-lg text-white text-sm px-3 py-2.5" placeholder="e.g. 45.5" required>
                    </div>
                    <div>
                        <label class="block font-label-md text-on-surface-variant mb-1.5 text-sm">Task Errors Made</label>
                        <input type="number" name="errors" class="w-full input-glass rounded-lg text-white text-sm px-3 py-2.5" placeholder="e.g. 1" required>
                    </div>
                    <div>
                        <label class="block font-label-md text-on-surface-variant mb-1.5 text-sm">User Satisfaction (1-5)</label>
                        <input type="number" min="1" max="5" name="satisfaction" class="w-full input-glass rounded-lg text-white text-sm px-3 py-2.5" placeholder="1 = Poor, 5 = Excellent" required>
                    </div>
                    <div>
                        <label class="block font-label-md text-on-surface-variant mb-1.5 text-sm">Calculated SUS Score (0-100)</label>
                        <input type="number" min="0" max="100" name="sus" class="w-full input-glass rounded-lg text-white text-sm px-3 py-2.5" placeholder="e.g. 85" required>
                    </div>
                    <div>
                        <label class="block font-label-md text-on-surface-variant mb-1.5 text-sm">Qualitative Comments</label>
                        <textarea name="comments" class="w-full input-glass rounded-lg text-white text-sm px-3 py-2.5 resize-none" rows="3" placeholder="Feedback or observations..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 rounded-lg font-bold text-white flex justify-center items-center gap-2 shadow-glow text-sm mt-2">
                        <span class="material-symbols-outlined text-[18px]">save</span> Save Record
                    </button>
                </form>
            </div>

            <!-- Right: Results Table -->
            <div class="lg:col-span-8 bg-white/5 border border-white/10 rounded-xl p-6">
                <h3 class="font-headline-md text-lg text-white mb-6 flex items-center gap-2 border-b border-white/10 pb-4">
                    <span class="material-symbols-outlined text-secondary">table_chart</span> Evaluation Database
                </h3>
                
                <div class="overflow-x-auto custom-scrollbar pb-2 max-h-[500px]">
                    <?php if ($results->num_rows === 0): ?>
                        <div class="text-center text-white/40 py-12">
                            <span class="material-symbols-outlined text-4xl mb-2 opacity-50">data_array</span>
                            <p>No usability tests logged yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="w-full text-left border-collapse min-w-[600px]">
                            <thead class="sticky top-0 bg-surface/90 backdrop-blur z-10">
                                <tr>
                                    <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Time</th>
                                    <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10 text-center">Errors</th>
                                    <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10 text-center">Satisfaction</th>
                                    <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-primary border-b border-white/10 text-center">SUS Score</th>
                                    <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10 w-1/3">Comments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $results->fetch_assoc()): 
                                    $sus_val = $row['sus_score'];
                                    $sus_badge = $sus_val >= 80 ? 'bg-primary/20 text-primary border-primary/30' : ($sus_val >= 68 ? 'bg-success/20 text-success border-success/30' : 'bg-warning/20 text-warning border-warning/30');
                                ?>
                                    <tr class="hover:bg-white/5 transition-colors border-b border-white/5">
                                        <td class="px-4 py-3 text-sm text-white/90 whitespace-nowrap"><span class="material-symbols-outlined text-[14px] align-middle text-white/50 mr-1">timer</span> <?php echo $row['task_completion_time']; ?>s</td>
                                        <td class="px-4 py-3 text-sm text-white/90 whitespace-nowrap text-center"><?php echo $row['errors']; ?></td>
                                        <td class="px-4 py-3 text-sm text-white/90 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <?php echo $row['satisfaction']; ?>
                                                <span class="material-symbols-outlined text-[14px] text-yellow-500">star</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="px-2.5 py-1 rounded border text-[11px] font-bold tracking-wider <?php echo $sus_badge; ?>">
                                                <?php echo $sus_val; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-white/70">
                                            <?php echo empty($row['comments']) ? '<span class="italic opacity-50">None</span>' : htmlspecialchars($row['comments']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
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

    // --- SweetAlert Notifications ---
    <?php if ($message === 'success'): ?>
        document.addEventListener('DOMContentLoaded', () => {
            Swal.fire({ 
                icon: 'success', 
                title: 'Data Saved', 
                text: 'Evaluation log added successfully.', 
                toast: true, 
                position: 'top-end', 
                timer: 3000, 
                showConfirmButton: false, 
                background: '#1E293B', 
                color: '#FFFFFF',
                customClass: { popup: 'dark-glass-modal' }
            });
        });
    <?php endif; ?>

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