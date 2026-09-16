<?php
// reset_password.php – Set New Password with Token (Aeon Campus Design System)
session_start();
require 'db_connect.php';

$message = '';
$error = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Verify token
$valid = false;
$user_id = 0;

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $expiry = strtotime($user['reset_token_expiry']);
        if ($expiry > time()) {
            $valid = true;
            $user_id = $user['id'];
        } else {
            $error = 'This reset link has expired. Please request a new one.';
        }
    } else {
        $error = 'Invalid reset link. Please request a new one.';
    }
} else {
    $error = 'No reset token provided. Please request a new reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please enter a password.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            $message = 'Password reset successfully! You can now log in with your new password.';
            header("Refresh:3; url=index.php");
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>CampusFind - Set New Password</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface": "#10131a", "on-surface": "#e1e2ec", "outline": "#8c909f",
                        "surface-container-high": "#272a31", "on-surface-variant": "#c2c6d6",
                        "error-container": "#93000a", "error": "#ffb4ab", "primary": "#adc6ff",
                        "secondary": "#44e2cd", "tertiary": "#ffb786"
                    },
                    "fontFamily": {
                        "headline-md": ["Inter"], "body-lg": ["Inter"], "label-md": ["Inter"],
                        "label-sm": ["Inter"], "headline-lg": ["Inter"], "body-md": ["Inter"],
                        "display-lg": ["Inter"]
                    }
                }
            }
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
        .shadow-glow { box-shadow: 0 0 20px rgba(173, 198, 255, 0.3); }
        
        /* Autofill Fix */
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 50px rgba(0, 0, 0, 0.8) inset !important;
            -webkit-text-fill-color: #e1e2ec !important; caret-color: #e1e2ec !important;
            transition: background-color 5000s ease-in-out 0s; border-radius: 0.75rem;
        }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-on-primary-container relative min-h-screen flex items-center justify-center">

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
          const fs = `precision highp float; uniform float u_time; uniform vec2 u_resolution; uniform vec2 u_mouse; varying vec2 v_texCoord;
          vec3 permute(vec3 x) { return mod(((x*34.0)+1.0)*x, 289.0); }
          float snoise(vec2 v){ const vec4 C = vec4(0.211324865405187, 0.366025403784439, -0.577350269189626, 0.024390243902439); vec2 i  = floor(v + dot(v, C.yy) ); vec2 x0 = v -   i + dot(i, C.xx); vec2 i1; i1 = (x0.x > x0.y) ? vec2(1.0, 0.0) : vec2(0.0, 1.0); vec4 x12 = x0.xyxy+C.xxzz; x12.xy -= i1; i = mod(i, 289.0); vec3 p = permute( permute( i.y + vec3(0.0, i1.y, 1.0 )) + i.x + vec3(0.0, i1.x, 1.0 )); vec3 m = max(0.5 - vec3(dot(x0,x0), dot(x12.xy,x12.xy), dot(x12.zw,x12.zw)), 0.0); m = m*m ; m = m*m ; vec3 x = 2.0 * fract(p * C.www) - 1.0; vec3 h = abs(x) - 0.5; vec3 ox = floor(x + 0.5); vec3 a0 = x - ox; m *= 1.79284291400159 - 0.85373472095314 * ( a0*a0 + h*h ); vec3 g; g.x  = a0.x  * x0.x  + h.x  * x0.y; g.yz = a0.yz * x12.xz + h.yz * x12.yw; return 130.0 * dot(m, g); }
          void main() { vec2 uv = v_texCoord; vec2 mouse = u_mouse / u_resolution; float n1 = snoise(uv * 2.0 + u_time * 0.05); float n2 = snoise(uv * 4.0 - u_time * 0.08 + mouse * 0.1); float n3 = snoise(uv * 8.0 + u_time * 0.12); vec3 baseColor = vec3(0.043, 0.055, 0.082); vec3 accentColor1 = vec3(0.231, 0.510, 0.965); vec3 accentColor2 = vec3(0.176, 0.831, 0.749); float mask = smoothstep(-0.2, 0.8, (n1 + n2 * 0.5)); vec3 color = mix(baseColor, accentColor1, mask * 0.4); float lines = sin(uv.y * 50.0 + n3 * 2.0 + u_time) * 0.5 + 0.5; color += accentColor2 * lines * pow(n3, 3.0) * 0.15; float dist = distance(uv, mouse); float glow = smoothstep(0.5, 0.0, dist) * 0.1; color += accentColor1 * glow; gl_FragColor = vec4(color, 1.0); }`;
          function cs(type, src) { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
          const prog = gl.createProgram(); gl.attachShader(prog, cs(gl.VERTEX_SHADER, vs)); gl.attachShader(prog, cs(gl.FRAGMENT_SHADER, fs)); gl.linkProgram(prog); gl.useProgram(prog);
          const buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
          const pos = gl.getAttribLocation(prog, 'a_position'); gl.enableVertexAttribArray(pos); gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);
          const uTime = gl.getUniformLocation(prog, 'u_time'); const uRes = gl.getUniformLocation(prog, 'u_resolution'); const uMouse = gl.getUniformLocation(prog, 'u_mouse');
          let mouse = { x: canvas.width / 2, y: canvas.height / 2 };
          window.addEventListener('mousemove', (event) => { const rect = canvas.getBoundingClientRect(); if (rect.width && rect.height) { mouse.x = ((event.clientX - rect.left) / rect.width) * canvas.width; mouse.y = (1.0 - (event.clientY - rect.top) / rect.height) * canvas.height; } });
          function render(t) { if (typeof ResizeObserver === 'undefined') syncSize(); gl.viewport(0, 0, canvas.width, canvas.height); if (uTime) gl.uniform1f(uTime, t * 0.001); if (uRes) gl.uniform2f(uRes, canvas.width, canvas.height); if (uMouse) gl.uniform2f(uMouse, mouse.x, mouse.y); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); requestAnimationFrame(render); }
          render(0);
        })();
        </script>
    </div>
    <div class="absolute inset-0 bg-gradient-radial from-transparent to-background/90"></div>
</div>

<div class="w-full max-w-[480px] mx-auto px-4 z-10 fade-in-up visible">
    <div class="glass-panel rounded-2xl p-8 md:p-12 relative overflow-hidden">
        
        <div class="absolute -top-20 -right-20 w-40 h-40 bg-primary/20 rounded-full blur-[50px] pointer-events-none"></div>
        <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-secondary/20 rounded-full blur-[50px] pointer-events-none"></div>
        
        <div class="text-center mb-8 relative z-10">
            <div class="w-16 h-16 mx-auto bg-gradient-to-br from-primary to-inverse-primary rounded-full flex items-center justify-center shadow-glow mb-4">
                <span class="material-symbols-outlined text-white text-3xl">password</span>
            </div>
            <h2 class="font-headline-lg text-headline-lg font-bold text-on-surface mb-2">Set New Password</h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Create a strong password for your account.</p>
        </div>

        <!-- PHP ALERTS -->
        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-xl bg-error-container/20 border border-error/50 text-error text-sm font-medium flex items-center gap-2 relative z-10">
                <span class="material-symbols-outlined text-lg">error</span> 
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl bg-secondary/20 border border-secondary/50 text-secondary text-sm font-medium flex items-center gap-2 relative z-10">
                <span class="material-symbols-outlined text-lg">check_circle</span> 
                <?php echo htmlspecialchars($message); ?>
                <br>Redirecting...
            </div>
        <?php endif; ?>

        <?php if ($valid && !$message): ?>
            <form action="" method="POST" class="space-y-5 relative z-10">
                <div>
                    <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                        <span class="material-symbols-outlined text-outline mr-3">lock</span>
                        <input name="password" id="password" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="New Password" type="password" required minlength="6"/>
                        <button class="text-outline hover:text-on-surface transition-colors ml-2 flex items-center" type="button" onclick="togglePassword('password', 'pwd-icon')">
                            <span class="material-symbols-outlined text-[20px]" id="pwd-icon">visibility_off</span>
                        </button>
                    </div>
                </div>
                <div>
                    <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                        <span class="material-symbols-outlined text-outline mr-3">lock_reset</span>
                        <input name="confirm_password" id="confirm_password" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="Confirm Password" type="password" required minlength="6"/>
                        <button class="text-outline hover:text-on-surface transition-colors ml-2 flex items-center" type="button" onclick="togglePassword('confirm_password', 'cpwd-icon')">
                            <span class="material-symbols-outlined text-[20px]" id="cpwd-icon">visibility_off</span>
                        </button>
                    </div>
                </div>
                
                <button class="w-full btn-primary text-white font-label-md text-label-md font-bold py-4 rounded-xl mt-4 flex items-center justify-center gap-2 shadow-glow" type="submit">
                    Update Password
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-8 text-center border-t border-white/10 pt-6 relative z-10">
            <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors flex items-center justify-center gap-1" href="index.php">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back to Login
            </a>
        </div>
        
    </div>
</div>

<script>
    // Password Toggle Logic
    function togglePassword(inputId, iconId) {
        const field = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (field.type === 'password') {
            field.type = 'text';
            icon.innerText = 'visibility';
        } else {
            field.type = 'password';
            icon.innerText = 'visibility_off';
        }
    }

    // Fade in animation trigger
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('visible'); } });
        }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in-up').forEach((el) => { observer.observe(el); });
    });
</script>
</body>
</html>