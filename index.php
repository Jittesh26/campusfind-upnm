<?php
// index.php – PREMIUM LANDING & LOGIN (Tailwind + WebGL + SweetAlert Fix)
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success_msg = '';

// Check for registration success
if (isset($_GET['registered'])) {
    $success_msg = 'Registration successful! Please sign in.';
}

// --- RATE LIMITING LOGIC ---
$ip = $_SERVER['REMOTE_ADDR'];
$rate_key = 'login_attempts_' . $ip;
if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = ['count' => 0, 'first_attempt' => time()];
}
$attempt_data = &$_SESSION[$rate_key];

if (time() - $attempt_data['first_attempt'] > 900) {
    $attempt_data['count'] = 0;
    $attempt_data['first_attempt'] = time();
}

if ($attempt_data['count'] >= 5) {
    $error = 'Too many failed attempts. Please try again in 15 minutes.';
}

// --- LOGIN PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    require 'db_connect.php';
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, full_name, username, password, role, unique_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                
                session_regenerate_id(true);
                
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['username']  = $row['username'];
                $_SESSION['role']      = $row['role'];
                $_SESSION['unique_id'] = $row['unique_id'];

                if ($remember) {
                    ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30); // 30 days
                }
                
                unset($_SESSION[$rate_key]);

                $redirect_url = 'dashboard.php';
                if ($row['role'] === 'admin') {
                    $redirect_url = 'admin_dashboard.php';
                } elseif (!empty($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                }
                
                header("Location: " . $redirect_url);
                exit;
            } else {
                $error = 'Invalid password.';
                $attempt_data['count']++; 
            }
        } else {
            $error = 'No account found with that username or email.';
            $attempt_data['count']++; 
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>CampusFind - AI-Powered Campus Lost &amp; Found</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    
    <!-- SweetAlert2 MUST be loaded here -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface-container-low": "#191b23",
                        "surface": "#10131a",
                        "on-background": "#e1e2ec",
                        "primary-fixed-dim": "#adc6ff",
                        "outline": "#8c909f",
                        "primary-container": "#4d8eff",
                        "on-surface": "#e1e2ec",
                        "tertiary-fixed": "#ffdcc6",
                        "surface-container-highest": "#32353c",
                        "on-secondary-container": "#004d44",
                        "primary-fixed": "#d8e2ff",
                        "surface-container": "#1d2027",
                        "inverse-primary": "#005ac2",
                        "on-primary-fixed-variant": "#004395",
                        "secondary-fixed": "#62fae3",
                        "on-secondary": "#003731",
                        "on-tertiary-fixed-variant": "#723600",
                        "on-error-container": "#ffdad6",
                        "on-surface-variant": "#c2c6d6",
                        "surface-container-high": "#272a31",
                        "on-primary-container": "#00285d",
                        "error-container": "#93000a",
                        "inverse-on-surface": "#2e3038",
                        "on-primary": "#002e6a",
                        "on-tertiary": "#502400",
                        "surface-dim": "#10131a",
                        "background": "#10131a",
                        "primary": "#adc6ff",
                        "outline-variant": "#424754",
                        "surface-bright": "#363941",
                        "error": "#ffb4ab",
                        "tertiary-container": "#df7412",
                        "inverse-surface": "#e1e2ec",
                        "secondary-container": "#03c6b2",
                        "tertiary": "#ffb786",
                        "on-secondary-fixed": "#00201c",
                        "on-error": "#690005",
                        "surface-variant": "#32353c",
                        "on-primary-fixed": "#001a42",
                        "on-tertiary-fixed": "#311400",
                        "on-tertiary-container": "#461f00",
                        "surface-tint": "#adc6ff",
                        "secondary": "#44e2cd",
                        "on-secondary-fixed-variant": "#005047",
                        "tertiary-fixed-dim": "#ffb786",
                        "secondary-fixed-dim": "#3cddc7",
                        "surface-container-lowest": "#0b0e15"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "margin-safe": "32px",
                        "container-max": "1280px",
                        "gutter-desktop": "24px",
                        "gutter-mobile": "16px",
                        "unit": "4px"
                    },
                    "fontFamily": {
                        "headline-md": ["Inter"], "body-lg": ["Inter"], "label-md": ["Inter"],
                        "label-sm": ["Inter"], "headline-lg": ["Inter"], "body-md": ["Inter"],
                        "display-lg": ["Inter"]
                    },
                    "fontSize": {
                        "headline-md": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.02em", "fontWeight": "600" }],
                        "body-lg": ["18px", { "lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "400" }],
                        "label-md": ["14px", { "lineHeight": "20px", "letterSpacing": "0.01em", "fontWeight": "500" }],
                        "label-sm": ["12px", { "lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "600" }],
                        "headline-lg": ["32px", { "lineHeight": "40px", "letterSpacing": "-0.03em", "fontWeight": "600" }],
                        "body-md": ["16px", { "lineHeight": "24px", "letterSpacing": "0em", "fontWeight": "400" }],
                        "display-lg": ["48px", { "lineHeight": "56px", "letterSpacing": "-0.04em", "fontWeight": "700" }]
                    },
                    "backgroundImage": {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                        'btn-gradient': 'linear-gradient(90deg, #adc6ff 0%, #44e2cd 100%)',
                        'glass-gradient': 'linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%)',
                        'glass-border': 'linear-gradient(180deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.05) 100%)'
                    },
                    "boxShadow": {
                        'glass': '0 8px 32px 0 rgba(0, 0, 0, 0.37)',
                        'glow': '0 0 20px rgba(173, 198, 255, 0.3)'
                    }
                },
            },
        }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; }
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .input-glass { background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(140, 144, 159, 0.3); transition: all 0.3s ease; }
        .input-glass:focus-within { border-color: #adc6ff; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2); }
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 0 20px rgba(77, 142, 255, 0.2); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 0 30px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        .floating-card { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-20px) rotate(2deg); } 100% { transform: translateY(0px) rotate(0deg); } }
        .pulse-dot { animation: pulse-glow 2s infinite; }
        @keyframes pulse-glow { 0% { box-shadow: 0 0 0 0 rgba(77, 142, 255, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(77, 142, 255, 0); } 100% { box-shadow: 0 0 0 0 rgba(77, 142, 255, 0); } }
        .workflow-line { stroke-dasharray: 1000; stroke-dashoffset: 1000; animation: dash 5s linear infinite; }
        @keyframes dash { to { stroke-dashoffset: 0; } }

        /* Custom SVG Animations */
        .icon-camera-lens { transform-origin: center; animation: shutter 3s infinite; }
        @keyframes shutter { 0%, 100% { transform: scale(1); } 10%, 20% { transform: scale(0.6); } 30% { transform: scale(1); } }
        .group:hover .icon-camera-lens { animation-duration: 1.5s; }
        .icon-network-node { transform-origin: center; animation: pulse-node 2s infinite alternate; }
        .icon-network-link { stroke-dasharray: 10; animation: dash-flow 3s linear infinite; }
        @keyframes pulse-node { 0% { transform: scale(0.8); opacity: 0.6; } 100% { transform: scale(1.2); opacity: 1; } }
        @keyframes dash-flow { to { stroke-dashoffset: -20; } }
        .group:hover .icon-network-node { animation-duration: 1s; }
        .group:hover .icon-network-link { animation-duration: 1.5s; }
        .icon-bell-ring { transform-origin: top center; animation: swing 4s infinite; }
        .icon-bell-waves { opacity: 0; transform-origin: center; animation: ripples-bell 4s infinite; }
        @keyframes swing { 0%, 100%, 20% { transform: rotate(0); } 5% { transform: rotate(15deg); } 10% { transform: rotate(-10deg); } 15% { transform: rotate(5deg); } }
        @keyframes ripples-bell { 0%, 20%, 100% { opacity: 0; transform: scale(0.5); } 5%, 15% { opacity: 1; transform: scale(1.2); } }
        .group:hover .icon-bell-ring { animation-duration: 2s; }
        .group:hover .icon-bell-waves { animation-duration: 2s; }
        .icon-qr-laser { animation: scan 2.5s infinite linear; }
        @keyframes scan { 0% { transform: translateY(2px); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { transform: translateY(18px); opacity: 0; } }
        .group:hover .icon-qr-laser { animation-duration: 1.25s; }
        .icon-map-pin { animation: bounce-pin 3s infinite cubic-bezier(0.28, 0.84, 0.42, 1); }
        .icon-map-ripple { transform-origin: center; animation: ripple-pin 3s infinite; }
        @keyframes bounce-pin { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
        @keyframes ripple-pin { 0% { transform: scale(0.2); opacity: 1; } 50% { transform: scale(1.5); opacity: 0; } 100% { transform: scale(0.2); opacity: 0; } }
        .group:hover .icon-map-pin { animation-duration: 1.5s; }
        .group:hover .icon-map-ripple { animation-duration: 1.5s; }
        .hover-glow:hover { box-shadow: 0 0 30px rgba(173, 198, 255, 0.15), 0 8px 32px 0 rgba(0, 0, 0, 0.37); }

        /* 🔥 FIX: CHROME/EDGE YELLOW AUTOFILL HACK 🔥 */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 50px rgba(0, 0, 0, 0.8) inset !important;
            -webkit-text-fill-color: #e1e2ec !important;
            caret-color: #e1e2ec !important;
            transition: background-color 5000s ease-in-out 0s;
            border-radius: 0.75rem;
        }

        /* SweetAlert Dark Theme Overrides */
        .swal2-popup.dark-glass-modal {
            background: rgba(30, 41, 59, 0.95) !important;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e1e2ec;
        }
        .swal2-title { color: #ffffff !important; }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-on-primary-container relative">

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
          const fs = `precision highp float; uniform float u_time; uniform vec2 u_resolution; uniform vec2 u_mouse; varying vec2 v_texCoord;
          vec3 permute(vec3 x) { return mod(((x*34.0)+1.0)*x, 289.0); }
          float snoise(vec2 v){ const vec4 C = vec4(0.211324865405187, 0.366025403784439, -0.577350269189626, 0.024390243902439); vec2 i  = floor(v + dot(v, C.yy) ); vec2 x0 = v -   i + dot(i, C.xx); vec2 i1; i1 = (x0.x > x0.y) ? vec2(1.0, 0.0) : vec2(0.0, 1.0); vec4 x12 = x0.xyxy + C.xxzz; x12.xy -= i1; i = mod(i, 289.0); vec3 p = permute( permute( i.y + vec3(0.0, i1.y, 1.0 )) + i.x + vec3(0.0, i1.x, 1.0 )); vec3 m = max(0.5 - vec3(dot(x0,x0), dot(x12.xy,x12.xy), dot(x12.zw,x12.zw)), 0.0); m = m*m ; m = m*m ; vec3 x = 2.0 * fract(p * C.www) - 1.0; vec3 h = abs(x) - 0.5; vec3 ox = floor(x + 0.5); vec3 a0 = x - ox; m *= 1.79284291400159 - 0.85373472095314 * ( a0*a0 + h*h ); vec3 g; g.x  = a0.x  * x0.x  + h.x  * x0.y; g.yz = a0.yz * x12.xz + h.yz * x12.yw; return 130.0 * dot(m, g); }
          void main() { vec2 uv = v_texCoord; vec2 mouse = u_mouse / u_resolution; float n1 = snoise(uv * 2.0 + u_time * 0.05); float n2 = snoise(uv * 4.0 - u_time * 0.08 + mouse * 0.1); float n3 = snoise(uv * 8.0 + u_time * 0.12); vec3 baseColor = vec3(0.043, 0.055, 0.082); vec3 accentColor1 = vec3(0.231, 0.510, 0.965); vec3 accentColor2 = vec3(0.176, 0.831, 0.749); float mask = smoothstep(-0.2, 0.8, (n1 + n2 * 0.5)); vec3 color = mix(baseColor, accentColor1, mask * 0.4); float lines = sin(uv.y * 50.0 + n3 * 2.0 + u_time) * 0.5 + 0.5; color += accentColor2 * lines * pow(n3, 3.0) * 0.15; float dist = distance(uv, mouse); float glow = smoothstep(0.5, 0.0, dist) * 0.1; color += accentColor1 * glow; gl_FragColor = vec4(color, 1.0); }`;
          
          function cs(type, src) { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
          const prog = gl.createProgram(); gl.attachShader(prog, cs(gl.VERTEX_SHADER, vs)); gl.attachShader(prog, cs(gl.FRAGMENT_SHADER, fs)); gl.linkProgram(prog); gl.useProgram(prog);
          const buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
          const pos = gl.getAttribLocation(prog, 'a_position'); gl.enableVertexAttribArray(pos); gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);
          const uTime = gl.getUniformLocation(prog, 'u_time'); const uRes = gl.getUniformLocation(prog, 'u_resolution'); const uMouse = gl.getUniformLocation(prog, 'u_mouse');

          let mouse = { x: canvas.width / 2, y: canvas.height / 2 };
          window.addEventListener('mousemove', (event) => {
            const rect = canvas.getBoundingClientRect();
            if (rect.width && rect.height) { mouse.x = ((event.clientX - rect.left) / rect.width) * canvas.width; mouse.y = (1.0 - (event.clientY - rect.top) / rect.height) * canvas.height; }
          });

          function render(t) {
            if (typeof ResizeObserver === 'undefined') syncSize();
            gl.viewport(0, 0, canvas.width, canvas.height);
            if (uTime) gl.uniform1f(uTime, t * 0.001);
            if (uRes) gl.uniform2f(uRes, canvas.width, canvas.height);
            if (uMouse) gl.uniform2f(uMouse, mouse.x, mouse.y);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
            requestAnimationFrame(render);
          }
          render(0);
        })();
        </script>
    </div>
    <div class="absolute inset-0 bg-gradient-radial from-transparent to-background/90"></div>
</div>

<!-- TopNavBar Component -->
<nav class="fixed top-0 w-full bg-surface/60 backdrop-blur-xl border-b border-white/10 shadow-sm transition-all duration-300 ease-in-out z-50">
    <div class="flex justify-between items-center h-20 px-4 md:px-8 max-w-7xl mx-auto">
        <div class="flex items-center gap-2 font-headline-md text-headline-md font-bold tracking-tighter text-on-surface">
            <span class="material-symbols-outlined text-primary text-3xl" data-weight="fill" style="font-variation-settings: 'FILL' 1;">explore</span>
            <span>CampusFind</span>
        </div>
        <div class="hidden md:flex space-x-8">
            <a class="text-on-surface-variant hover:text-on-surface transition-colors font-body-md text-body-md hover:bg-white/5 px-3 py-2 rounded-lg" href="#features">How it Works</a>
            <a class="text-on-surface-variant hover:text-on-surface transition-colors font-body-md text-body-md hover:bg-white/5 px-3 py-2 rounded-lg cursor-pointer" href="javascript:void(0);" onclick="showSecurity()">Security</a>
            <a class="text-on-surface-variant hover:text-on-surface transition-colors font-body-md text-body-md hover:bg-white/5 px-3 py-2 rounded-lg cursor-pointer" href="javascript:void(0);" onclick="showSupport()">Support</a>
        </div>
        <div class="flex items-center">
            <a class="text-primary font-label-md text-label-md font-bold px-6 py-2 rounded-full border border-primary/30 hover:bg-primary/10 transition-colors" href="#login-section">
                Sign In
            </a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="relative min-h-screen flex flex-col items-center justify-center pt-20 overflow-hidden px-4 md:px-8">
    <div class="z-10 text-center max-w-4xl mx-auto fade-in-up">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass-panel mb-8 border border-primary/30">
            <div class="w-2 h-2 rounded-full bg-secondary pulse-dot"></div>
            <span class="font-label-sm text-label-sm text-secondary uppercase tracking-widest">AI Matching Engine Live</span>
        </div>
        <h1 class="font-display-lg text-display-lg md:text-[72px] md:leading-[80px] font-bold tracking-tighter mb-6 bg-clip-text text-transparent bg-gradient-to-b from-white to-on-surface-variant">
            Find. Report. Reconnect.<br/>
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary to-secondary">Smartly.</span>
        </h1>
        <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto mb-12">
            The AI-powered lost and found ecosystem for modern campuses. Combining multimodal neural matching, interactive Leaflet geolocation, and privacy-first Campus Tag QR codes to instantly reunite students with their belongings.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a class="btn-primary text-white font-label-md text-label-md px-8 py-4 rounded-xl flex items-center gap-2 group w-full sm:w-auto justify-center" href="#login-section">
                Get Started
                <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
            </a>
            <a class="glass-panel text-on-surface font-label-md text-label-md px-8 py-4 rounded-xl hover:bg-white/10 transition-colors flex items-center gap-2 w-full sm:w-auto justify-center" href="#features">
                <span class="material-symbols-outlined">play_circle</span>
                See How It Works
            </a>
        </div>
    </div>
    <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 animate-bounce opacity-50">
        <span class="material-symbols-outlined text-3xl">keyboard_arrow_down</span>
    </div>
</section>

<!-- Interactive Login & Showcase Section -->
<section class="relative min-h-screen flex items-center py-32 px-4 md:px-8" id="login-section">
    <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
        
        <!-- Left Side Floating Elements -->
        <div class="relative h-[600px] hidden lg:block fade-in-up delay-100">
            <div class="absolute inset-0 bg-gradient-to-tr from-primary/10 to-transparent rounded-full blur-3xl"></div>
            
            <div class="absolute top-10 left-10 glass-panel p-4 rounded-xl flex items-start gap-4 floating-card w-72" style="animation-delay: 0s;">
                <div class="w-12 h-12 rounded-lg bg-surface-container-high flex items-center justify-center shrink-0 border border-white/5">
                    <span class="material-symbols-outlined text-secondary text-2xl">headphones</span>
                </div>
                <div>
                    <h4 class="font-label-md text-label-md text-on-surface mb-1">AirPods Pro</h4>
                    <p class="font-label-sm text-label-sm text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">location_on</span> Main Library, 2nd Floor
                    </p>
                    <div class="mt-2 inline-block px-2 py-1 rounded bg-secondary/20 text-secondary font-label-sm text-[10px] uppercase tracking-wider">Found 10m ago</div>
                </div>
            </div>

            <div class="absolute top-1/2 right-0 glass-panel p-4 rounded-xl flex items-start gap-4 floating-card w-72" style="animation-delay: -2s;">
                <div class="w-12 h-12 rounded-lg bg-surface-container-high flex items-center justify-center shrink-0 border border-white/5">
                    <span class="material-symbols-outlined text-tertiary text-2xl">backpack</span>
                </div>
                <div>
                    <h4 class="font-label-md text-label-md text-on-surface mb-1">Blue Backpack</h4>
                    <p class="font-label-sm text-label-sm text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">location_on</span> Student Center Cafe
                    </p>
                    <div class="mt-2 inline-block px-2 py-1 rounded bg-tertiary/20 text-tertiary font-label-sm text-[10px] uppercase tracking-wider">Reported Missing</div>
                </div>
            </div>

            <div class="absolute bottom-10 left-20 glass-panel p-2 rounded-xl floating-card w-64" style="animation-delay: -4s;">
                <div class="bg-cover bg-center w-full h-32 rounded-lg border border-white/10 mb-2" style="background-image: url('https://images.unsplash.com/photo-1524661135-423995f22d0b?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80')"></div>
                <div class="px-2 pb-2">
                    <div class="flex items-center justify-between">
                        <span class="font-label-sm text-label-sm text-on-surface">Campus Grid</span>
                        <div class="flex items-center gap-1 text-primary">
                            <div class="w-1.5 h-1.5 rounded-full bg-primary pulse-dot"></div>
                            <span class="text-[10px] uppercase tracking-wider">Live</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- The Login Form -->
        <div class="w-full max-w-[480px] mx-auto lg:mx-0 fade-in-up">
            <div class="glass-panel rounded-2xl p-8 md:p-12 relative overflow-hidden">
                <div class="absolute -top-20 -right-20 w-40 h-40 bg-primary/20 rounded-full blur-[50px] pointer-events-none"></div>
                <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-secondary/20 rounded-full blur-[50px] pointer-events-none"></div>
                
                <div class="text-center mb-8 relative z-10">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-br from-primary to-inverse-primary rounded-full flex items-center justify-center shadow-glow mb-4">
                        <span class="material-symbols-outlined text-white text-3xl" data-weight="fill" style="font-variation-settings: 'FILL' 1;">explore</span>
                    </div>
                    <h2 class="font-headline-lg text-headline-lg font-bold text-on-surface mb-2">Welcome Back</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Sign in to report or claim items.</p>
                </div>

                <!-- PHP ALERTS -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-error-container/20 border border-error/50 text-error text-sm font-medium flex items-center gap-2 relative z-10">
                        <span class="material-symbols-outlined text-lg">error</span> 
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success_msg): ?>
                    <div class="mb-6 p-4 rounded-xl bg-secondary-container/20 border border-secondary/50 text-secondary text-sm font-medium flex items-center gap-2 relative z-10">
                        <span class="material-symbols-outlined text-lg">check_circle</span> 
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-5 relative z-10">
                    <div>
                        <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                            <span class="material-symbols-outlined text-outline mr-3">person</span>
                            <input name="username" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="University ID or Email" type="text" required/>
                        </div>
                    </div>
                    <div>
                        <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                            <span class="material-symbols-outlined text-outline mr-3">lock</span>
                            <input name="password" id="password" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="Password" type="password" required/>
                            <button class="text-outline hover:text-on-surface transition-colors ml-2 flex items-center" type="button" onclick="togglePassword()">
                                <span class="material-symbols-outlined" id="password-icon">visibility_off</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <div class="relative flex items-center justify-center w-5 h-5 border border-outline rounded bg-surface/50 group-hover:border-primary transition-colors">
                                <input name="remember" class="opacity-0 absolute inset-0 cursor-pointer peer" type="checkbox"/>
                                <span class="material-symbols-outlined text-[16px] text-primary opacity-0 peer-checked:opacity-100 transition-opacity">check</span>
                            </div>
                            <span class="font-label-sm text-label-sm text-on-surface-variant group-hover:text-on-surface transition-colors">Remember Me</span>
                        </label>
                        <a class="font-label-sm text-label-sm text-primary hover:text-primary-fixed transition-colors" href="forgot_password.php">Forgot Password?</a>
                    </div>
                    <button class="w-full btn-primary text-white font-label-md text-label-md font-bold py-4 rounded-xl mt-4 flex items-center justify-center gap-2" type="submit">
                        Sign In
                        <span class="material-symbols-outlined text-[20px]">login</span>
                    </button>
                </form>

                <div class="mt-8 text-center border-t border-white/10 pt-6 relative z-10">
                    <p class="font-label-sm text-label-sm text-on-surface-variant">
                        New to CampusFind? 
                        <a class="text-primary hover:text-primary-fixed font-bold border-b border-primary/30 hover:border-primary transition-colors pb-0.5 ml-1" href="register.php">Create your account →</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Workflow Section -->
<section class="py-32 px-4 md:px-8 relative bg-surface-container-lowest/50 border-y border-white/5" id="features">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-20 fade-in-up">
            <h2 class="font-headline-lg text-headline-lg font-bold text-on-surface mb-4">How Intelligence Recovers</h2>
            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-2xl mx-auto">Our neural matching engine works in the background to connect lost items with their rightful owners in real-time.</p>
        </div>
        
        <!-- 3-2 Grid Layout -->
        <div class="flex flex-col gap-8">
            <!-- Row 1: 3 Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 relative z-10">
                <svg class="absolute top-1/2 left-0 w-full h-24 -translate-y-1/2 hidden lg:block z-0 pointer-events-none" preserveAspectRatio="none" viewBox="0 0 1000 100">
                    <path class="workflow-line" d="M 150 50 Q 300 0, 500 50 T 850 50" fill="none" stroke="rgba(173, 198, 255, 0.2)" stroke-dasharray="10, 10" stroke-width="2"></path>
                </svg>
                
                <!-- Step 1 -->
                <div class="bg-surface/40 backdrop-blur-xl border border-white/10 hover-glow p-8 rounded-2xl relative z-10 fade-in-up group hover:scale-105 transition-all duration-300 w-full" style="transition-delay: 50ms;">
                    <div class="w-16 h-16 rounded-xl bg-surface-container/80 border border-white/10 flex items-center justify-center mb-6 group-hover:border-primary/50 transition-colors">
                        <svg class="w-8 h-8 text-on-surface" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle class="icon-camera-lens" cx="12" cy="13" r="4"></circle>
                        </svg>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-3">1. Report Instantly</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant">Snap a photo or describe the item. Our AI automatically extracts keywords, colors, and brands to create a digital fingerprint.</p>
                </div>
                
                <!-- Step 2 -->
                <div class="bg-surface/40 backdrop-blur-xl border border-white/10 hover-glow p-8 rounded-2xl relative z-10 fade-in-up delay-100 group hover:scale-105 transition-all duration-300 w-full" style="transition-delay: 150ms;">
                    <div class="w-16 h-16 rounded-xl bg-surface-container/80 border border-white/10 flex items-center justify-center mb-6 group-hover:border-secondary/50 transition-colors relative overflow-hidden">
                        <svg class="w-8 h-8 text-secondary" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path class="icon-network-link" d="M18 6l-6 6-6-6"></path>
                            <path class="icon-network-link" d="M12 12v6"></path>
                            <circle class="icon-network-node" cx="6" cy="6" r="3"></circle>
                            <circle class="icon-network-node" cx="18" cy="6" r="3"></circle>
                            <circle class="icon-network-node" cx="12" cy="18" r="3"></circle>
                        </svg>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-3">2. Multimodal AI Matching</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant">The engine continuously scans incoming 'Found' reports against 'Lost' records, scoring matches based on semantic visual similarity.</p>
                </div>
                
                <!-- Step 3 -->
                <div class="bg-surface/40 backdrop-blur-xl border border-white/10 hover-glow p-8 rounded-2xl relative z-10 fade-in-up delay-200 group hover:scale-105 transition-all duration-300 w-full" style="transition-delay: 250ms;">
                    <div class="w-16 h-16 rounded-xl bg-surface-container/80 border border-white/10 flex items-center justify-center mb-6 group-hover:border-primary/50 transition-colors">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path class="icon-bell-waves" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path class="icon-bell-ring" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path class="icon-bell-ring" d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-3">3. Secure Recovery</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant">Receive real-time in-app alerts and email notifications with precise campus map locations, verified chat messaging, and secure Handover PIN receipts upon return.</p>
                </div>
            </div>
            
            <!-- Row 2: 2 Centered Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:w-2/3 mx-auto relative z-10">
                <!-- Step 4 -->
                <div class="bg-surface/40 backdrop-blur-xl border border-white/10 hover-glow p-8 rounded-2xl relative z-10 fade-in-up group hover:scale-105 transition-all duration-300 w-full" style="transition-delay: 350ms;">
                    <div class="w-16 h-16 rounded-xl bg-surface-container/80 border border-white/10 flex items-center justify-center mb-6 group-hover:border-primary/50 transition-colors relative overflow-hidden">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <rect height="5" width="5" x="3" y="3"></rect>
                            <rect height="5" width="5" x="16" y="3"></rect>
                            <rect height="5" width="5" x="3" y="16"></rect>
                            <path d="M21 16v5h-5"></path>
                            <path d="M8 12h8"></path>
                            <line class="icon-qr-laser stroke-secondary" x1="4" x2="20" y1="12" y2="12"></line>
                        </svg>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-3">4. Smart Campus Tag QR</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant">Generate privacy-first QR code tags for your keys, bag, or wallet. Finders can scan your tag to launch an anonymous encrypted web chat without exposing your real phone number.</p>
                </div>
                
                <!-- Step 5 -->
                <div class="bg-surface/40 backdrop-blur-xl border border-white/10 hover-glow p-8 rounded-2xl relative z-10 fade-in-up group hover:scale-105 transition-all duration-300 w-full" style="transition-delay: 450ms;">
                    <div class="w-16 h-16 rounded-xl bg-surface-container/80 border border-white/10 flex items-center justify-center mb-6 group-hover:border-secondary/50 transition-colors relative">
                        <svg class="w-8 h-8 text-secondary relative z-10" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path class="icon-map-pin" d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle class="icon-map-pin" cx="12" cy="10" r="3"></circle>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-8 h-8 bg-secondary/30 rounded-full icon-map-ripple"></div>
                        </div>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-3">5. Interactive Campus Geolocation</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant">Locate reported lost and found items through an interactive campus map powered by Leaflet.js, helping students understand where items were discovered.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer Component -->
<footer class="bg-surface-container-lowest w-full py-12 border-t border-outline-variant relative z-10">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 px-4 md:px-8 max-w-7xl mx-auto">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2 font-headline-sm text-headline-sm font-bold text-on-surface">
                <span class="material-symbols-outlined text-primary text-2xl" data-weight="fill" style="font-variation-settings: 'FILL' 1;">explore</span>
                <span>CampusFind</span>
            </div>
            <p class="text-on-surface-variant font-label-sm text-label-sm opacity-80 hover:opacity-100 transition-opacity">
                © <?php echo date('Y'); ?> CampusFind. Intelligence for a Connected Campus.
            </p>
        </div>
        <div class="flex flex-wrap md:justify-end gap-x-8 gap-y-4 items-center">
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-secondary transition-colors opacity-80 hover:opacity-100" href="#">Privacy Policy</a>
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-secondary transition-colors opacity-80 hover:opacity-100" href="#">Terms of Service</a>
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-secondary transition-colors opacity-80 hover:opacity-100 cursor-pointer" href="javascript:void(0);" onclick="showSecurity()">Security Info</a>
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-secondary transition-colors opacity-80 hover:opacity-100 cursor-pointer" href="javascript:void(0);" onclick="showSupport()">Contact Support</a>
        </div>
    </div>
</footer>

<script>
    // Password Toggle
    function togglePassword() {
        const field = document.getElementById('password');
        const icon = document.getElementById('password-icon');
        if (field.type === 'password') {
            field.type = 'text';
            icon.innerText = 'visibility';
        } else {
            field.type = 'password';
            icon.innerText = 'visibility_off';
        }
    }

    // Scroll Animations
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    requestAnimationFrame(() => {
                        entry.target.classList.add('visible');
                    });
                }
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -50px 0px" });

        document.querySelectorAll('.fade-in-up').forEach((el) => {
            observer.observe(el);
        });
    });

    // --- SWEETALERT MODALS FOR SECURITY & SUPPORT ---
    function showSecurity() {
        Swal.fire({
            title: '<span class="material-symbols-outlined text-4xl text-secondary mb-2" style="font-size:40px; color:#44e2cd;">shield_lock</span><br>Enterprise-Grade Security',
            html: `
                <div style="text-align:left; font-size:14px; color:#c2c6d6; line-height:1.6; margin-top:16px;">
                    <p style="margin-bottom:12px;"><strong><span style="color:#adc6ff;">E2E-Style Encryption:</span></strong> All peer-to-peer chats are secured using AES-256-CBC encryption. Your messages are completely private.</p>
                    <p style="margin-bottom:12px;"><strong><span style="color:#adc6ff;">Data Privacy (PDPA):</span></strong> Campus Tags use masked IDs to ensure phone numbers and names are never exposed publicly.</p>
                    <p><strong><span style="color:#adc6ff;">AI Gatekeeper:</span></strong> Item claims are protected by a semantic AI verification system to prevent theft and false claims.</p>
                </div>
            `,
            background: '#191b23',
            color: '#e1e2ec',
            confirmButtonColor: '#4d8eff',
            confirmButtonText: 'Understood',
            customClass: { popup: 'dark-glass-modal' }
        });
    }

    function showSupport() {
        Swal.fire({
            title: '<span class="material-symbols-outlined text-4xl text-primary mb-2" style="font-size:40px; color:#adc6ff;">support_agent</span><br>Campus Support',
            html: `
                <div style="text-align:left; font-size:14px; color:#c2c6d6; line-height:1.6; margin-top:16px;">
                    <p>If you encounter any issues or suspicious behavior, please contact the campus administration immediately.</p>
                    <div style="padding:12px; background:rgba(0,0,0,0.3); border-radius:8px; border:1px solid rgba(255,255,255,0.05); text-align:center; margin-top:16px;">
                        <strong style="color:white;">Email:</strong> support@upnm.edu.my<br>
                        <strong style="color:white;">Hotline:</strong> +60 3-9051 3400
                    </div>
                </div>
            `,
            background: '#191b23',
            color: '#e1e2ec',
            confirmButtonColor: '#4d8eff',
            confirmButtonText: 'Close',
            customClass: { popup: 'dark-glass-modal' }
        });
    }
</script>
</body>
</html>