<?php
// register.php – PREMIUM ONBOARDING (Aeon Campus Design System)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'db_connect.php';

$error = '';
$success = '';
$full_name = $faculty = $matric_number = $username = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $faculty = trim($_POST['faculty']);
    $matric_number = trim($_POST['matric_number']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($full_name) || empty($faculty) || empty($matric_number) || empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!preg_match('/^[A-Za-z0-9]{6,20}$/', $matric_number)) {
        $error = 'Matric Number must be 6-20 alphanumeric characters (e.g., A12345).';
    } else {
        // Check if username or email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username or email already taken.';
        } else {
            // Check if matric number is unique
            $check_matric = $conn->prepare("SELECT id FROM users WHERE matric_number = ?");
            $check_matric->bind_param("s", $matric_number);
            $check_matric->execute();
            $check_matric->store_result();

            if ($check_matric->num_rows > 0) {
                $error = 'Matric Number already registered. Please contact admin if this is an error.';
            } else {
                // Generate unique ID
                $result = $conn->query("SELECT unique_id FROM users ORDER BY id DESC LIMIT 1");
                if ($result && $row = $result->fetch_assoc()) {
                    $num = intval(substr($row['unique_id'], 1)) + 1;
                    $unique_id = 'A' . str_pad($num, 4, '0', STR_PAD_LEFT);
                } else {
                    $unique_id = 'A0001';
                }

                // Hash password
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $stmt = $conn->prepare("INSERT INTO users (unique_id, full_name, faculty, matric_number, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'student')");
                $stmt->bind_param("sssssss", $unique_id, $full_name, $faculty, $matric_number, $username, $email, $hashed);

                if ($stmt->execute()) {
                    header("Location: index.php?registered=1");
                    exit;
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
            $check_matric->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>CampusFind - Create Account</title>
    
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
        .input-glass:focus-within, .input-glass.active-dropdown { border-color: #adc6ff; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2); }
        
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 0 20px rgba(77, 142, 255, 0.2); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 0 30px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        
        /* Autofill Fix */
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 50px rgba(0, 0, 0, 0.8) inset !important;
            -webkit-text-fill-color: #e1e2ec !important; caret-color: #e1e2ec !important;
            transition: background-color 5000s ease-in-out 0s; border-radius: 0.75rem;
        }

        /* Custom Scrollbar for Dropdown */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

        /* Password Strength Bar */
        .strength-bar { height: 4px; border-radius: 2px; width: 0%; transition: all 0.3s ease; background-color: transparent; }
        .strength-weak { width: 33%; background-color: #ffb4ab; box-shadow: 0 0 8px rgba(255, 180, 171, 0.5); }
        .strength-medium { width: 66%; background-color: #ffb786; box-shadow: 0 0 8px rgba(255, 183, 134, 0.5); }
        .strength-strong { width: 100%; background-color: #44e2cd; box-shadow: 0 0 8px rgba(68, 226, 205, 0.5); }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-on-primary-container relative min-h-screen flex items-center justify-center">

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

<div class="w-full max-w-7xl mx-auto px-4 md:px-8 py-12 flex items-center min-h-screen">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 w-full items-center">
        
        <!-- Left Side: Value Proposition (Hidden on Mobile) -->
        <div class="hidden lg:block fade-in-up">
            <div class="flex items-center gap-2 font-headline-md text-headline-md font-bold tracking-tighter text-on-surface mb-8">
                <span class="material-symbols-outlined text-primary text-3xl" data-weight="fill" style="font-variation-settings: 'FILL' 1;">explore</span>
                <span>CampusFind</span>
            </div>
            
            <h1 class="font-display-lg text-display-lg font-bold tracking-tighter mb-6 bg-clip-text text-transparent bg-gradient-to-b from-white to-on-surface-variant leading-tight">
                Join the Intelligent<br/>Campus Ecosystem.
            </h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md mb-10">
                Create your account to report lost items, generate privacy-first QR tags, and let our neural matching engine work for you.
            </p>

            <div class="space-y-6">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-surface-container-high border border-white/5 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-secondary">memory</span>
                    </div>
                    <div>
                        <h4 class="font-label-md text-label-md text-on-surface mb-1">AI-Powered Matching</h4>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">Instant visual and semantic pairing.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-surface-container-high border border-white/5 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-primary">qr_code_scanner</span>
                    </div>
                    <div>
                        <h4 class="font-label-md text-label-md text-on-surface mb-1">Privacy-First Campus Tags</h4>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">Anonymous contact without exposing your number.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-surface-container-high border border-white/5 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-tertiary">lock</span>
                    </div>
                    <div>
                        <h4 class="font-label-md text-label-md text-on-surface mb-1">Secure Encrypted Chats</h4>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">End-to-end verified communication.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Registration Form -->
        <div class="w-full max-w-[550px] mx-auto lg:mx-0 fade-in-up delay-100">
            <div class="glass-panel rounded-2xl p-6 md:p-10 relative overflow-visible">
                
                <div class="lg:hidden text-center mb-6">
                    <div class="w-14 h-14 mx-auto bg-gradient-to-br from-primary to-inverse-primary rounded-full flex items-center justify-center shadow-glow mb-3">
                        <span class="material-symbols-outlined text-white text-2xl" data-weight="fill" style="font-variation-settings: 'FILL' 1;">explore</span>
                    </div>
                    <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Create Account</h2>
                </div>
                
                <div class="hidden lg:block mb-8">
                    <h2 class="font-headline-lg text-headline-lg font-bold text-on-surface mb-2">Create Account</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Enter your details to register.</p>
                </div>

                <!-- PHP ALERTS -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-error-container/20 border border-error/50 text-error text-sm font-medium flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">error</span> 
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4 relative z-10" id="registrationForm">
                    
                    <!-- Row 1: Name & Username -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                            <span class="material-symbols-outlined text-outline mr-3 text-[20px]">badge</span>
                            <input name="full_name" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="Full Name" type="text" value="<?php echo htmlspecialchars($full_name); ?>" required/>
                        </div>
                        <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                            <span class="material-symbols-outlined text-outline mr-3 text-[20px]">alternate_email</span>
                            <input name="username" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="Username" type="text" value="<?php echo htmlspecialchars($username); ?>" required/>
                        </div>
                    </div>

                    <!-- Row 2: Email -->
                    <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                        <span class="material-symbols-outlined text-outline mr-3 text-[20px]">mail</span>
                        <input name="email" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="University Email" type="email" value="<?php echo htmlspecialchars($email); ?>" required/>
                    </div>

                    <!-- Row 3: Matric & Custom Faculty Dropdown -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                            <span class="material-symbols-outlined text-outline mr-3 text-[20px]">pin</span>
                            <input name="matric_number" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="Matric ID" type="text" value="<?php echo htmlspecialchars($matric_number); ?>" required/>
                        </div>
                        
                        <!-- 🔥 CUSTOM GLASS DROPDOWN 🔥 -->
                        <div class="relative">
                            <!-- Hidden input actually sent in POST -->
                            <input type="hidden" name="faculty" id="facultyInput" value="<?php echo htmlspecialchars($faculty); ?>" required>
                            
                            <!-- The visible button -->
                            <div class="input-glass rounded-xl flex items-center px-4 py-3 cursor-pointer h-full" id="facultyDropdownBtn">
                                <span class="material-symbols-outlined text-outline mr-3 text-[20px] pointer-events-none">school</span>
                                <span id="facultySelectedText" class="w-full text-on-surface font-body-md text-body-md select-none truncate pointer-events-none <?php echo empty($faculty) ? 'text-outline-variant' : ''; ?>">
                                    <?php echo empty($faculty) ? 'Select Faculty' : htmlspecialchars($faculty); ?>
                                </span>
                                <span class="material-symbols-outlined text-outline transition-transform duration-200 pointer-events-none" id="facultyArrow">expand_more</span>
                            </div>

                            <!-- The floating menu -->
                            <div id="facultyMenu" class="absolute left-0 right-0 top-full mt-2 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden shadow-glass border border-white/10" style="transform: translateY(-10px);">
                                <div class="max-h-56 overflow-y-auto custom-scrollbar py-2">
                                    <div class="px-4 py-2.5 hover:bg-white/10 cursor-pointer text-on-surface transition-colors custom-option text-sm" data-value="Pusat Asasi Pertahanan">Pusat Asasi Pertahanan</div>
                                    <div class="px-4 py-2.5 hover:bg-white/10 cursor-pointer text-on-surface transition-colors custom-option text-sm" data-value="Fakulti Perubatan dan Kesihatan Pertahanan">Fakulti Perubatan dan Kesihatan Pertahanan</div>
                                    <div class="px-4 py-2.5 hover:bg-white/10 cursor-pointer text-on-surface transition-colors custom-option text-sm" data-value="Fakulti Kejuruteraan">Fakulti Kejuruteraan</div>
                                    <div class="px-4 py-2.5 hover:bg-white/10 cursor-pointer text-on-surface transition-colors custom-option text-sm" data-value="Fakulti Sains dan Teknologi Pertahanan">Fakulti Sains dan Teknologi Pertahanan</div>
                                    <div class="px-4 py-2.5 hover:bg-white/10 cursor-pointer text-on-surface transition-colors custom-option text-sm" data-value="Fakulti Pengajian dan Pengurusan Pertahanan">Fakulti Pengajian dan Pengurusan Pertahanan</div>
                                    <div class="px-4 py-2.5 hover:bg-white/10 cursor-pointer text-on-surface transition-colors custom-option text-sm" data-value="Pusat Bahasa">Pusat Bahasa</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Passwords -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                                <span class="material-symbols-outlined text-outline mr-3 text-[20px]">lock</span>
                                <input name="password" id="password" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="Password" type="password" required oninput="checkStrength(this.value)"/>
                                <button class="text-outline hover:text-on-surface transition-colors ml-2 flex items-center" type="button" onclick="togglePassword('password', 'pwd-icon')">
                                    <span class="material-symbols-outlined text-[20px]" id="pwd-icon">visibility_off</span>
                                </button>
                            </div>
                            <div class="w-full bg-surface-container-high h-1 mt-2 rounded-full overflow-hidden">
                                <div id="strength-bar" class="strength-bar"></div>
                            </div>
                        </div>
                        <div class="relative input-glass rounded-xl flex items-center px-4 py-3 h-[50px]">
                            <span class="material-symbols-outlined text-outline mr-3 text-[20px]">lock_reset</span>
                            <input name="confirm_password" id="confirm_password" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 font-body-md text-body-md p-0" placeholder="Confirm Password" type="password" required/>
                            <button class="text-outline hover:text-on-surface transition-colors ml-2 flex items-center" type="button" onclick="togglePassword('confirm_password', 'cpwd-icon')">
                                <span class="material-symbols-outlined text-[20px]" id="cpwd-icon">visibility_off</span>
                            </button>
                        </div>
                    </div>

                    <!-- Terms -->
                    <div class="pt-2">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <div class="relative flex items-center justify-center w-5 h-5 mt-0.5 border border-outline rounded bg-surface/50 group-hover:border-primary transition-colors shrink-0">
                                <input class="opacity-0 absolute inset-0 cursor-pointer peer" type="checkbox" required/>
                                <span class="material-symbols-outlined text-[16px] text-primary opacity-0 peer-checked:opacity-100 transition-opacity">check</span>
                            </div>
                            <span class="font-label-sm text-[13px] text-on-surface-variant leading-relaxed">
                                I agree to the <a href="#" class="text-primary hover:text-primary-fixed transition-colors">Terms of Service</a> and <a href="#" class="text-primary hover:text-primary-fixed transition-colors">Privacy Policy</a>.
                            </span>
                        </label>
                    </div>

                    <button class="w-full btn-primary text-white font-label-md text-label-md font-bold py-4 rounded-xl mt-6 flex items-center justify-center gap-2" type="button" onclick="submitForm()">
                        Create Account
                        <span class="material-symbols-outlined text-[20px]">person_add</span>
                    </button>
                </form>

                <div class="mt-8 text-center border-t border-white/10 pt-6 relative z-10">
                    <p class="font-label-sm text-label-sm text-on-surface-variant">
                        Already have an account? 
                        <a class="text-primary hover:text-primary-fixed font-bold border-b border-primary/30 hover:border-primary transition-colors pb-0.5 ml-1" href="index.php">Sign In →</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- CUSTOM DROPDOWN LOGIC ---
    const facultyBtn = document.getElementById('facultyDropdownBtn');
    const facultyMenu = document.getElementById('facultyMenu');
    const facultyArrow = document.getElementById('facultyArrow');
    const facultyInput = document.getElementById('facultyInput');
    const facultyText = document.getElementById('facultySelectedText');
    const options = document.querySelectorAll('.custom-option');

    facultyBtn.addEventListener('click', (e) => {
        e.stopPropagation(); // Stop click from bubbling to document
        const isClosed = facultyMenu.classList.contains('opacity-0');
        if (isClosed) {
            facultyMenu.classList.remove('opacity-0', 'invisible');
            facultyMenu.style.transform = 'translateY(0)';
            facultyArrow.style.transform = 'rotate(180deg)';
            facultyBtn.classList.add('active-dropdown');
        } else {
            closeDropdown();
        }
    });

    options.forEach(option => {
        option.addEventListener('click', (e) => {
            e.stopPropagation(); // Stop click from bubbling
            const value = option.getAttribute('data-value');
            facultyInput.value = value;
            facultyText.textContent = value;
            facultyText.classList.remove('text-outline-variant');
            
            // Highlight selected option visually
            options.forEach(opt => opt.classList.remove('bg-primary/20', 'text-primary'));
            option.classList.add('bg-primary/20', 'text-primary');

            closeDropdown();
        });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!facultyBtn.contains(e.target) && !facultyMenu.contains(e.target)) {
            closeDropdown();
        }
    });

    function closeDropdown() {
        facultyMenu.classList.add('opacity-0', 'invisible');
        facultyMenu.style.transform = 'translateY(-10px)';
        facultyArrow.style.transform = 'rotate(0deg)';
        facultyBtn.classList.remove('active-dropdown');
    }

    // Custom form submit check to ensure the hidden input is filled
    function submitForm() {
        if (facultyInput.value === '') {
            alert('Please select a Faculty.');
            facultyBtn.classList.add('border-error');
            return;
        }
        document.getElementById('registrationForm').submit();
    }


    // --- PASSWORD & UI LOGIC ---
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

    function checkStrength(val) {
        const bar = document.getElementById('strength-bar');
        if (val.length === 0) {
            bar.className = 'strength-bar';
        } else if (val.length < 6) {
            bar.className = 'strength-bar strength-weak';
        } else if (val.length >= 6 && val.match(/[A-Z]/) && val.match(/[0-9]/)) {
            bar.className = 'strength-bar strength-strong';
        } else {
            bar.className = 'strength-bar strength-medium';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.fade-in-up').forEach((el) => { observer.observe(el); });
    });
</script>
</body>
</html>