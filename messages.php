<?php
// messages.php – PRIVATE 1-TO-1 CHAT (SMART POLLING & SMART NAVIGATION)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';
require 'encryption.php';

$user_id = intval($_SESSION['user_id']);
$item_id = isset($_GET['item']) ? intval($_GET['item']) : 0;
$other_id = isset($_GET['other']) ? intval($_GET['other']) : 0;

if ($item_id <= 0) {
    header('Location: messages_list.php');
    exit;
}

if (!isset($_SESSION['unique_id'])) {
    $u_stmt = $conn->prepare("SELECT unique_id FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result()->fetch_assoc();
    $_SESSION['unique_id'] = $u_res['unique_id'] ?? 'A0000';
}

// Fetch item details
$check = $conn->prepare("SELECT id, user_id, category, item_name, verification_question, verification_answer FROM item_reports WHERE id = ?");
$check->bind_param("i", $item_id);
$check->execute();
$item = $check->get_result()->fetch_assoc();
if (!$item) {
    header('Location: messages_list.php');
    exit;
}
$is_owner = ($item['user_id'] == $user_id);
$owner_id = $item['user_id'];

// Auto-set other_id for non-owner
if (!$is_owner) {
    $other_id = $owner_id;
}

// Redirect owner back to item detail if no finder is selected
if ($other_id <= 0) {
    header('Location: item_detail.php?id=' . $item_id);
    exit;
}

// Fetch other user's unique_id
$other_unique_id = '';
$other_stmt = $conn->prepare("SELECT unique_id FROM users WHERE id = ?");
$other_stmt->bind_param("i", $other_id);
$other_stmt->execute();
$other_res = $other_stmt->get_result()->fetch_assoc();
$other_unique_id = $other_res['unique_id'] ?? 'Unknown';

// --- Verification Gatekeeper ---
if (!empty($item['verification_question']) && !$is_owner) {
    if (!isset($_SESSION['verified_chat'][$item_id]) || $_SESSION['verified_chat'][$item_id] !== true) {
        header('Location: item_detail.php?id=' . $item_id . '&error=verify');
        exit;
    }
} else {
    $_SESSION['verified_chat'][$item_id] = true;
}

// --- Privacy Check ---
$check_pair = $conn->prepare("SELECT id FROM chat_messages 
                              WHERE item_report_id = ? 
                                AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
                              LIMIT 1");
$check_pair->bind_param("iiiii", $item_id, $user_id, $other_id, $other_id, $user_id);
$check_pair->execute();
$has_pair_messages = ($check_pair->get_result()->num_rows > 0);

if ($has_pair_messages) {
    $check_participant = $conn->prepare("SELECT id FROM chat_messages 
                                         WHERE item_report_id = ? AND (sender_id = ? OR receiver_id = ?) 
                                         LIMIT 1");
    $check_participant->bind_param("iii", $item_id, $user_id, $user_id);
    $check_participant->execute();
    if ($check_participant->get_result()->num_rows == 0) {
        $_SESSION['chat_error'] = "This chat is private. Only the original participants can view it.";
        header('Location: item_detail.php?id=' . $item_id);
        exit;
    }
}

// Fallback POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $other_id > 0) {
    $raw_message = trim($_POST['message']);
    if (!empty($raw_message)) {
        $encrypted = encryptMessage($raw_message);
        $stmt = $conn->prepare("INSERT INTO chat_messages (item_report_id, sender_id, receiver_id, message, iv) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $item_id, $user_id, $other_id, $encrypted['data'], $encrypted['iv']);
        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE item_report_id = ? AND sender_id = ? AND receiver_id = ?");
            $stmt2->bind_param("iii", $item_id, $other_id, $user_id);
            $stmt2->execute();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

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
    <title>CampusFind - Secure Chat</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "surface": "#10131a", "primary": "#adc6ff", "secondary": "#44e2cd", "tertiary": "#ffb786", "error": "#ffb4ab", "success": "#10B981" }, fontFamily: { "headline-md": ["Inter"], "body-md": ["Inter"] } } } }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; }
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .chat-scrollbar::-webkit-scrollbar { width: 6px; }
        .chat-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 8px; }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-white relative min-h-screen pb-6 flex flex-col">

<!-- Background Shader (RESTORED ANIMATION) -->
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

<?php include 'navbar.php'; ?>

<main class="w-full max-w-4xl mx-auto px-4 sm:px-6 pt-24 sm:pt-28 pb-4 flex-grow flex flex-col h-screen">
    
    <div class="glass-panel rounded-2xl relative overflow-hidden flex flex-col h-[calc(100vh-120px)] sm:h-[calc(100vh-140px)]">
        
        <!-- Chat Header -->
        <div class="px-5 py-4 border-b border-white/10 bg-surface-container-low/50 shrink-0 flex items-center justify-between z-10">
            <div class="flex items-center gap-3">
                
                <!-- 🔥 SMART BACK BUTTON HERE 🔥 -->
                <button onclick="goBack('messages_list.php')" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/70 hover:text-white transition-colors" title="Back">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                </button>

                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white shadow-glow shrink-0 border border-white/20">
                        <span class="material-symbols-outlined text-[20px]">person</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-white font-bold text-sm sm:text-base leading-tight">User <?php echo htmlspecialchars($other_unique_id); ?></span>
                        <a href="item_detail.php?id=<?php echo $item_id; ?>" class="text-[11px] text-primary hover:text-primary-fixed hover:underline truncate max-w-[200px] sm:max-w-[300px]" title="View Item Details">
                            Re: <?php echo htmlspecialchars($item['item_name']); ?> (#<?php echo $item_id; ?>)
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="flex-1 overflow-y-auto chat-scrollbar p-5 relative" id="chatBox">
            <div id="chatMessages" class="w-full flex flex-col"></div>
        </div>

        <!-- Input Area -->
        <div class="p-4 sm:p-5 border-t border-white/10 bg-surface-container-low/50 shrink-0">
            <form id="chatForm" class="flex gap-2">
                <div class="relative flex-1">
                    <input type="text" id="messageInput" class="w-full bg-white/5 border border-white/10 focus:border-primary focus:ring-1 focus:ring-primary/50 text-white text-sm rounded-full pl-5 pr-12 py-3.5 transition-all outline-none" placeholder="Type a secure message..." autocomplete="off">
                </div>
                <button type="submit" class="w-12 h-12 shrink-0 rounded-full bg-gradient-to-br from-primary to-inverse-primary text-white flex items-center justify-center hover:scale-105 hover:shadow-glow transition-all disabled:opacity-50" id="sendBtn">
                    <span class="material-symbols-outlined text-[20px] ml-1">send</span>
                </button>
            </form>
            <div class="text-center mt-2">
                <span class="text-[10px] text-white/40 uppercase tracking-widest"><i class="material-symbols-outlined text-[10px] align-middle">lock</i> E2E Encrypted & Anonymous</span>
            </div>
        </div>
    </div>
</main>

<script>
    // --- SMART BACK BUTTON SCRIPT ---
    function goBack(defaultUrl) {
        if (document.referrer && document.referrer.includes(window.location.hostname)) {
            // Prevent loop if referrer is exactly the same page
            if (document.referrer === window.location.href) {
                window.location.href = defaultUrl;
            } else {
                window.history.back();
            }
        } else {
            window.location.href = defaultUrl;
        }
    }

    // --- CHAT LOGIC ---
    const itemId = <?php echo $item_id; ?>;
    const currentUserId = <?php echo $user_id; ?>;
    const otherUserId = <?php echo $other_id; ?>;
    const userUniqueId = "<?php echo htmlspecialchars($_SESSION['unique_id'] ?? 'You'); ?>";
    const chatBox = document.getElementById('chatBox');
    const chatMessages = document.getElementById('chatMessages');
    const messageInput = document.getElementById('messageInput');
    const chatForm = document.getElementById('chatForm');
    const sendBtn = document.getElementById('sendBtn');

    function loadMessages() {
        fetch(`messages_ajax.php?action=get_messages&item_id=${itemId}&other_id=${otherUserId}&_=${Date.now()}`)
            .then(response => response.json())
            .then(data => { if (data.success) renderMessages(data.messages); })
            .catch(error => console.error('Error:', error));
    }

    function renderMessages(messages) {
        if (!messages || messages.length === 0) {
            chatMessages.innerHTML = `<div class="flex flex-col items-center justify-center py-12 text-center opacity-50 mt-10"><span class="material-symbols-outlined text-4xl mb-2">forum</span><h6 class="text-white font-bold text-sm">No messages yet</h6></div>`;
            return;
        }
        const isNearBottom = chatBox.scrollHeight - chatBox.clientHeight - chatBox.scrollTop < 100;
        let html = '';
        messages.forEach(msg => {
            const isSelf = (parseInt(msg.sender_id) === currentUserId);
            const senderDisplay = isSelf ? `You (${msg.unique_id})` : `User ${msg.unique_id}`;
            const cleanMessage = msg.message ? msg.message.trim() : '';
            const readIcon = isSelf ? (parseInt(msg.is_read) === 1 ? `<span class="text-success ml-2"><span class="material-symbols-outlined text-[14px] align-middle">done_all</span></span>` : `<span class="text-white/40 ml-2"><span class="material-symbols-outlined text-[14px] align-middle">check</span></span>`) : '';
            const wrapperClass = isSelf ? 'flex flex-col items-end w-full mb-4' : 'flex flex-col items-start w-full mb-4';
            const bubbleClass = isSelf ? 'bg-gradient-to-br from-primary to-inverse-primary text-white px-4 py-2.5 rounded-2xl rounded-tr-sm shadow-glow max-w-[85%] sm:max-w-[75%] break-words text-sm' : 'bg-white/10 border border-white/10 text-white px-4 py-2.5 rounded-2xl rounded-tl-sm max-w-[85%] sm:max-w-[75%] break-words text-sm';
            html += `<div class="${wrapperClass}"><div class="${bubbleClass}">${escapeHtml(cleanMessage)}</div><div class="text-[10px] text-white/40 mt-1.5 uppercase tracking-widest font-bold flex items-center">${senderDisplay} • ${msg.sent_at} ${readIcon}</div></div>`;
        });
        chatMessages.innerHTML = html;
        if (isNearBottom) chatBox.scrollTop = chatBox.scrollHeight;
        fetch(`messages_ajax.php?action=mark_read&item_id=${itemId}&other_id=${otherUserId}`);
    }

    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = messageInput.value.trim();
        if (!message) return;

        if (chatMessages.innerHTML.includes('No messages yet')) chatMessages.innerHTML = '';
        chatMessages.insertAdjacentHTML('beforeend', `<div class="flex flex-col items-end w-full mb-4"><div class="bg-gradient-to-br from-primary to-inverse-primary text-white px-4 py-2.5 rounded-2xl rounded-tr-sm shadow-glow max-w-[85%] sm:max-w-[75%] break-words text-sm opacity-70">${escapeHtml(message)}</div><div class="text-[10px] text-white/40 mt-1.5 uppercase tracking-widest font-bold flex items-center">You • Sending...</div></div>`);
        chatBox.scrollTop = chatBox.scrollHeight;
        messageInput.value = ''; sendBtn.disabled = true;

        fetch('messages_ajax.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `action=send&item_id=${itemId}&other_id=${otherUserId}&message=${encodeURIComponent(message)}` })
        .then(response => response.json())
        .then(data => { sendBtn.disabled = false; loadMessages(); })
        .catch(() => { sendBtn.disabled = false; loadMessages(); });
    });

    loadMessages();
    let chatInterval = setInterval(() => { if(!document.hidden) loadMessages(); }, 10000);
    document.addEventListener("visibilitychange", function() {
        clearInterval(chatInterval);
        chatInterval = setInterval(loadMessages, document.hidden ? 30000 : 10000);
    });

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
</script>
</body>
</html>