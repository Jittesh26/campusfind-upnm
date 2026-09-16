<?php
// ai_matching.php – MODERN GLASSMORPHISM AI MATCHING (POLISHED DROPZONE)
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
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - AI Matching</title>
    
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
        
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 0 20px rgba(77, 142, 255, 0.2); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 0 30px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; }
        
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        
        .input-glass { background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(140, 144, 159, 0.3); transition: all 0.3s ease; }
        .input-glass:focus-within { border-color: #adc6ff !important; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2) !important; }
        
        /* Dropzone Styling */
        .dropzone { border: 2px dashed rgba(140, 144, 159, 0.4); background: rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; }
        .dropzone:hover, .dropzone.dragover { border-color: #adc6ff; background: rgba(173, 198, 255, 0.05); }

        /* SweetAlert Dark Theme Overrides */
        .swal2-popup.dark-glass-modal { background: rgba(30, 41, 59, 0.95) !important; backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: #e1e2ec; }
        .swal2-title { color: #ffffff !important; font-family: 'Inter', sans-serif !important; }
        .swal2-html-container { font-family: 'Inter', sans-serif !important; }
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

<!-- Main Form Content -->
<main class="w-full max-w-3xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow flex flex-col justify-center fade-in-up visible">
    
    <div class="glass-panel p-6 sm:p-10 rounded-2xl relative overflow-hidden">
        <!-- Accent Glows -->
        <div class="absolute -top-20 -right-20 w-40 h-40 bg-primary/20 rounded-full blur-[50px] pointer-events-none"></div>
        <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-secondary/10 rounded-full blur-[50px] pointer-events-none"></div>

        <div class="mb-8 border-b border-white/10 pb-6 relative z-10">
            <h1 class="font-headline-lg text-2xl sm:text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-3xl">memory</span> AI Item Matching
            </h1>
            <p class="text-on-surface-variant font-body-sm mt-2">Upload a photo <strong>OR</strong> describe the item to find similar lost/found items across the campus.</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="mb-6 p-4 rounded-xl bg-error-container/20 border border-error/50 text-error text-sm font-medium flex items-center gap-2 relative z-10">
                <span class="material-symbols-outlined text-lg">error</span> 
                Please upload an image or enter a description.
            </div>
        <?php endif; ?>

        <form method="POST" action="ai_matching_process.php" enctype="multipart/form-data" class="space-y-6 relative z-10" id="aiMatchForm">
            
            <!-- Image Upload Area -->
            <div>
                <label class="block font-label-md text-on-surface-variant mb-2">📸 Upload Image <span class="text-white/40 font-normal">(optional)</span></label>
                
                <div class="w-full rounded-xl dropzone p-8 text-center group min-h-[220px] flex flex-col items-center justify-center relative" id="uploadArea">
                    
                    <!-- Default Dropzone View -->
                    <div id="defaultState" class="flex flex-col items-center justify-center w-full transition-opacity duration-200">
                        <div class="w-16 h-16 rounded-full bg-surface-container border border-white/10 flex items-center justify-center mx-auto mb-4 group-hover:bg-primary/20 group-hover:border-primary/50 transition-colors">
                            <span class="material-symbols-outlined text-3xl text-primary">cloud_upload</span>
                        </div>
                        <p class="text-white font-medium mb-1">Click to upload or drag & drop</p>
                        <p class="text-xs text-on-surface-variant mt-1">PNG, JPG, WEBP up to 5MB</p>
                    </div>

                    <!-- Active Image Selected View -->
                    <div id="selectedState" class="hidden flex-col items-center justify-center w-full animation-fadeIn">
                        <div class="relative inline-block mb-3 group/preview">
                            <img id="preview" class="max-w-[180px] max-h-[180px] object-cover rounded-xl border-2 border-white/20 shadow-glass" alt="Uploaded Preview">
                            <button type="button" class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-error text-white border-2 border-surface flex items-center justify-center text-sm shadow-lg hover:scale-110 hover:bg-red-600 transition-all z-10" onclick="removeSelectedFile(event)" title="Remove photo">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 bg-white/5 border border-white/10 px-4 py-1.5 rounded-full text-sm text-on-surface-variant mb-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">image</span>
                            <span id="fileName" class="font-medium truncate max-w-[150px]">photo.jpg</span>
                            <span id="fileSize" class="text-white/40 text-xs"></span>
                        </div>
                        <span class="text-xs text-primary hover:text-primary-fixed underline cursor-pointer transition-colors">Click or drop to change photo</span>
                    </div>

                    <input type="file" name="image" id="imageInput" class="hidden" accept="image/*" onchange="handleFileSelect(event)">
                </div>
            </div>

            <!-- OR Divider -->
            <div class="flex items-center gap-4 my-2 opacity-50">
                <div class="h-px bg-white/20 flex-1"></div>
                <span class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">OR</span>
                <div class="h-px bg-white/20 flex-1"></div>
            </div>

            <!-- Text Description -->
            <div>
                <label class="block font-label-md text-on-surface-variant mb-2">📝 Describe the Item <span class="text-white/40 font-normal">(optional)</span></label>
                <div class="relative input-glass rounded-xl px-4 py-3">
                    <textarea name="text_query" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 p-0 min-h-[100px] resize-none" placeholder="e.g., Black water bottle with a blue cap and a scratch on the side"></textarea>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 pt-4">
                <button type="submit" class="btn-primary text-white font-label-md text-label-md font-bold py-4 px-8 rounded-xl flex items-center justify-center gap-2 flex-1 shadow-glow" id="submitBtn">
                    Find Matches
                    <span class="material-symbols-outlined text-[20px]">search</span>
                </button>
                <a href="dashboard.php" class="btn-outline-glass text-center py-4 px-8 rounded-xl font-bold sm:w-1/3">Cancel</a>
            </div>
        </form>

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

    // --- UPLOAD DROPZONE LOGIC ---
    const uploadArea = document.getElementById('uploadArea');
    const imageInput = document.getElementById('imageInput');
    const defaultState = document.getElementById('defaultState');
    const selectedState = document.getElementById('selectedState');
    const preview = document.getElementById('preview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');

    uploadArea.addEventListener('click', function(e) {
        if (e.target.closest('button')) return; // Ignore if delete button clicked
        imageInput.click();
    });

    function handleFileSelect(event) {
        const file = event.target.files[0] || (event.dataTransfer && event.dataTransfer.files[0]);
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({icon: 'error', title: 'File too large', text: 'Image exceeds 5MB.', background: '#191b23', color: '#e1e2ec', customClass: { popup: 'dark-glass-modal' }});
                imageInput.value = '';
                return;
            }
            preview.src = URL.createObjectURL(file);
            fileName.textContent = file.name;
            fileSize.textContent = '(' + formatBytes(file.size) + ')';
            
            defaultState.style.display = 'none';
            selectedState.style.display = 'flex';
        }
    }

    function removeSelectedFile(event) {
        event.stopPropagation();
        imageInput.value = '';
        preview.src = '';
        selectedState.style.display = 'none';
        defaultState.style.display = 'flex';
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover', 'border-primary', 'bg-primary/5'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover', 'border-primary', 'bg-primary/5'), false);
    });

    uploadArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            imageInput.files = files;
            handleFileSelect({ target: { files: files } });
        }
    });

    // --- FORM VALIDATION & SWEETALERT AI LOADING SEQUENCE ---
    document.getElementById('aiMatchForm').addEventListener('submit', function(e) {
        const textQuery = document.querySelector('textarea[name="text_query"]');

        if ((!imageInput.files || imageInput.files.length === 0) && (!textQuery.value || textQuery.value.trim() === '')) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Input Required',
                text: 'Please upload an image or enter a description to find matches.',
                background: '#1E293B', color: '#FFFFFF',
                customClass: { popup: 'dark-glass-modal' }
            });
            return;
        }

        // Custom AI Loading Modal
        Swal.fire({
            title: 'Initializing AI Core...',
            html: `
                <div id="ai-status-text" class="text-sm text-on-surface-variant mb-4 font-body-md">Connecting to neural engine</div>
                <div class="w-full bg-surface-container-highest h-2 rounded-full overflow-hidden border border-white/5">
                    <div id="ai-progress-bar" class="h-full bg-gradient-to-r from-primary to-secondary" style="width: 0%; transition: width 0.5s ease;"></div>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            background: '#1E293B',
            color: '#FFFFFF',
            customClass: { popup: 'dark-glass-modal' },
            didOpen: () => {
                Swal.showLoading();
                const statusText = document.getElementById('ai-status-text');
                const progressBar = document.getElementById('ai-progress-bar');
                
                let progress = 0;
                setInterval(() => {
                    progress += Math.random() * 15 + 5;
                    if(progress > 95) progress = 95;
                    if(progressBar) progressBar.style.width = progress + '%';
                }, 500);

                setTimeout(() => { 
                    Swal.update({title: '🔍 Scanning database...'}); 
                    if(statusText) statusText.textContent = 'Searching for candidate matches'; 
                }, 1500);
                setTimeout(() => { 
                    Swal.update({title: '🤖 AI analyzing visual features...'}); 
                    if(statusText) statusText.textContent = 'Comparing material, shape, and details'; 
                }, 3000);
                setTimeout(() => { 
                    Swal.update({title: '✨ Almost done...'}); 
                    if(statusText) statusText.textContent = 'Ranking similarity scores'; 
                }, 4500);
            }
        });
    });

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