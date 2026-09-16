<?php
// ai_matching_process.php – ALL ITEMS + RETRY LOGIC (NO BLACK SCREEN)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', '');
}
$GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . GEMINI_API_KEY;

$image_path = '';
$text_query = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_path = 'uploads/ai_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
    }

    if (!empty($_POST['text_query'])) {
        $text_query = trim($_POST['text_query']);
    }

    if (empty($image_path) && empty($text_query)) {
        header('Location: ai_matching.php?error=1');
        exit;
    }
} else {
    header('Location: ai_matching.php');
    exit;
}

// Fetch items (limit 10)
$reports = $conn->query("SELECT id, item_name, category, description, location, report_type, status, image_path 
                         FROM item_reports 
                         WHERE status != 'returned' 
                         ORDER BY created_at DESC LIMIT 10");

if ($reports->num_rows === 0) {
    $error = 'No items in the database to compare. Please add some reports first.';
    $results = [];
} else {
    // Build prompt
    $parts = [];
    $prompt = "You are a strict, highly accurate visual matching AI for a lost and found system.\n\n";
    $prompt .= "Your task: Compare the USER'S ITEM (image + text) against each DATABASE ITEM (image + text).\n";
    $prompt .= "Determine if they are the EXACT SAME object or just similar.\n\n";
    $prompt .= "📊 SCORING STRICTNESS RULES:\n";
    $prompt .= "- 90-100%: Visually IDENTICAL (same brand, material, color, shape, size, features).\n";
    $prompt .= "- 70-89%: VERY HIGH similarity. Same item type, same material, same color.\n";
    $prompt .= "- 40-69%: SAME CATEGORY but CLEAR PHYSICAL DIFFERENCES.\n";
    $prompt .= "- 0-39%: Completely DIFFERENT items.\n\n";
    $prompt .= "⚠️ CRITICAL: Pay close attention to MATERIAL and SHAPE.\n\n";
    $prompt .= "=== USER'S UPLOADED ITEM ===\n";
    if (!empty($text_query)) {
        $prompt .= "Text Description: " . $text_query . "\n";
    }
    $parts[] = ['text' => $prompt];

    if (!empty($image_path) && file_exists($image_path)) {
        $parts[] = ['text' => "(User uploaded image attached below)"];
        $parts[] = [
            'inline_data' => [
                'mime_type' => mime_content_type($image_path),
                'data' => base64_encode(file_get_contents($image_path))
            ]
        ];
    }

    $parts[] = ['text' => "\n=== DATABASE ITEMS (WITH IMAGES) ===\n"];
    $items_list = [];
    while ($row = $reports->fetch_assoc()) {
        $items_list[] = $row;
        $item_text = "---\n";
        $item_text .= "Database Item ID: " . $row['id'] . "\n";
        $item_text .= "Name: " . $row['item_name'] . "\n";
        $item_text .= "Category: " . $row['category'] . "\n";
        $item_text .= "Description: " . $row['description'] . "\n";
        $item_text .= "Location: " . $row['location'] . "\n";
        $parts[] = ['text' => $item_text];
        if (!empty($row['image_path']) && file_exists($row['image_path'])) {
            $parts[] = ['text' => "(Database item image attached below)"];
            $parts[] = [
                'inline_data' => [
                    'mime_type' => mime_content_type($row['image_path']),
                    'data' => base64_encode(file_get_contents($row['image_path']))
                ]
            ];
        } else {
            $parts[] = ['text' => "(No image available for this database item)\n"];
        }
    }

    $parts[] = ['text' => "\nAnalyze the visual differences carefully. Output ONLY a valid JSON object where keys are the Item IDs and values are objects containing 'score' (integer 0-100) and 'reason' (string explaining the material/shape/visual differences).\n\n"];
    $parts[] = ['text' => 'Example output:
{
  "12": {"score": 95, "reason": "Visually identical - both are matte black metal water bottles"},
  "15": {"score": 25, "reason": "Different material - user\'s item is plastic, database item is metal"}
}'];

    $payload = [
        'contents' => [
            ['parts' => $parts]
        ],
        'generationConfig' => [
            'response_mime_type' => 'application/json'
        ]
    ];

    // ---------- RETRY LOGIC ----------
    function callGeminiWithRetry($url, $payload, $maxAttempts = 5) {
        $attempt = 0;
        $wait = 2; // initial seconds
        do {
            $attempt++;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code === 200) {
                return ['success' => true, 'response' => $response, 'code' => $http_code];
            }

            if ($http_code === 429 && $attempt < $maxAttempts) {
                sleep($wait);
                $wait *= 2;
                continue;
            }

            return ['success' => false, 'response' => $response, 'code' => $http_code, 'error' => $curl_error];
        } while ($attempt < $maxAttempts);
        return ['success' => false, 'code' => 429, 'response' => 'Max retries exceeded'];
    }

    $result = callGeminiWithRetry($GEMINI_URL, $payload);

    if (!$result['success']) {
        $http_code = $result['code'] ?? 0;
        if ($http_code === 429) {
            $error = "The AI service is currently rate‑limited. Please wait about 60 seconds and try again.<br><small>We've automatically retried 5 times with increasing delays.</small>";
        } else {
            $error = "AI service error (HTTP $http_code). ";
            if ($http_code === 404) $error .= "The API endpoint was not found. Check your API key.";
            elseif ($http_code === 403) $error .= "API key is invalid or expired.";
            elseif (!empty($result['error'])) $error .= "cURL error: " . $result['error'];
        }
        $results = [];
    } else {
        $response = $result['response'];
        $data = json_decode($response, true);
        $raw_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $clean_text = trim($raw_text);
        $clean_text = preg_replace('/```json\s*|```\s*/', '', $clean_text);
        $scores = json_decode($clean_text, true);
        $results = [];

        if (is_array($scores)) {
            foreach ($items_list as $item) {
                $id = $item['id'];
                $score = isset($scores[$id]['score']) ? intval($scores[$id]['score']) : 0;
                $reason = isset($scores[$id]['reason']) ? $scores[$id]['reason'] : 'No specific reason provided';
                $score = min(100, max(0, $score));
                $item['similarity'] = $score;
                $item['reason'] = $reason;
                $results[] = $item;
            }
            usort($results, function($a, $b) {
                return $b['similarity'] - $a['similarity'];
            });
            $_SESSION['last_ai_results'] = $results;
            $_SESSION['last_ai_query'] = ['image' => $image_path, 'text' => $text_query];
        } else {
            $error = "Could not parse AI response. Please try again with a clearer image or description.";
            $results = [];
        }
    }
}

// Get unread count for navbar badge
$user_id = $_SESSION['user_id'];
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
    <title>CampusFind - AI Results</title>
    
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
                        "surface-container-lowest": "#0b0e15",
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
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        
        .result-card-high { border-left: 4px solid #10B981; }
        .result-card-med { border-left: 4px solid #F59E0B; }
        .result-card-low { border-left: 4px solid #8c909f; }
        
        .score-circle {
            background: conic-gradient(var(--tw-gradient-stops));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
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
    
    <div class="glass-panel p-6 sm:p-8 rounded-2xl relative overflow-hidden mb-8">
        <div class="border-b border-white/10 pb-4 mb-2">
            <h1 class="font-headline-lg text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-3xl">smart_toy</span> AI Matching Results
            </h1>
            <p class="text-on-surface-variant font-body-sm mt-1">
                Database items ranked by visual and semantic similarity to your query.
            </p>
        </div>

        <!-- ERROR HANDLING -->
        <?php if ($error): ?>
            <div class="p-4 rounded-xl bg-error-container/20 border border-error/50 text-error text-sm font-medium mb-6 relative z-10">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-xl mt-0.5">error</span>
                    <div>
                        <strong class="block mb-1">Processing Error</strong>
                        <?php echo $error; ?>
                        
                        <?php if (strpos($error, 'rate‑limited') !== false || strpos($error, '429') !== false): ?>
                            <p class="text-xs text-error/70 mt-2">Please wait 30–60 seconds before generating a new request.</p>
                        <?php endif; ?>
                        
                        <?php if (strpos($error, '404') !== false || strpos($error, '403') !== false): ?>
                            <div class="mt-3 bg-black/20 p-3 rounded-lg border border-error/20">
                                <span class="block text-xs font-bold uppercase tracking-wider mb-1">Troubleshooting Tips</span>
                                <ul class="list-disc pl-4 text-xs space-y-1">
                                    <li>Check your Gemini API key in <code>db_connect.php</code></li>
                                    <li>Ensure server has internet connectivity</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex gap-4">
                <button onclick="goBack('ai_matching.php')" class="btn-primary px-6 py-3 rounded-xl font-bold text-white text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">refresh</span> Try Again
                </button>
                <a href="dashboard.php" class="btn-outline-glass px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">home</span> Dashboard
                </a>
            </div>
            <?php exit; ?>
        <?php endif; ?>

        <!-- EMPTY STATE -->
        <?php if (empty($results)): ?>
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="w-20 h-20 bg-warning/10 border border-warning/30 rounded-full flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-warning text-4xl">search_off</span>
                </div>
                <h3 class="text-white font-headline-md text-xl mb-2">No items found</h3>
                <p class="text-on-surface-variant text-sm max-w-md mb-6">The AI couldn't find any items to compare against. Try uploading a clearer image or adding a more specific description.</p>
                <div class="flex gap-4">
                    <button onclick="goBack('ai_matching.php')" class="btn-primary px-6 py-3 rounded-xl font-bold text-white text-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> New Search
                    </button>
                    <a href="dashboard.php" class="btn-outline-glass px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">home</span> Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            
            <p class="text-sm text-on-surface-variant mb-6">
                Showing <strong class="text-white"><?php echo count($results); ?></strong> item(s) sorted by AI confidence.
            </p>

            <!-- RESULTS LIST -->
            <div class="space-y-4">
                <?php foreach ($results as $row): 
                    $score = $row['similarity'];
                    
                    // Determine Theme based on score
                    if ($score >= 70) {
                        $cardBorder = 'result-card-high';
                        $scoreColor = 'text-success';
                        $matchLabel = 'High Match';
                        $labelBg = 'bg-success/20 text-success border-success/30';
                        $icon = 'verified';
                    } elseif ($score >= 40) {
                        $cardBorder = 'result-card-med';
                        $scoreColor = 'text-warning';
                        $matchLabel = 'Medium Match';
                        $labelBg = 'bg-warning/20 text-warning border-warning/30';
                        $icon = 'warning';
                    } else {
                        $cardBorder = 'result-card-low';
                        $scoreColor = 'text-outline';
                        $matchLabel = 'Low Match';
                        $labelBg = 'bg-surface-container-highest text-on-surface-variant border-white/10';
                        $icon = 'trending_down';
                    }
                    
                    $reason = $row['reason'] ?? 'No specific reason provided by AI.';
                    $typeClass = $row['report_type'] == 'lost' ? 'bg-error/20 text-error border-error/30' : 'bg-secondary/20 text-secondary border-secondary/30';
                ?>
                    
                    <div class="glass-panel p-5 rounded-2xl hover:border-primary/50 transition-all duration-300 group flex flex-col md:flex-row gap-6 items-start md:items-center <?php echo $cardBorder; ?>">
                        
                        <!-- Left: Image -->
                        <div class="w-24 h-24 shrink-0 rounded-xl overflow-hidden border border-white/10 relative bg-surface-container-high flex items-center justify-center">
                            <?php if (!empty($row['image_path']) && file_exists($row['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($row['image_path']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Item image">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-4xl text-white/20">image</span>
                            <?php endif; ?>
                        </div>

                        <!-- Center: Details & AI Reason -->
                        <div class="flex-1 w-full min-w-0">
                            <h3 class="font-headline-md text-lg text-white mb-2 truncate"><?php echo htmlspecialchars($row['item_name']); ?></h3>
                            
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider <?php echo $typeClass; ?>">
                                    <?php echo ucfirst($row['report_type']); ?>
                                </span>
                                <span class="px-2 py-0.5 rounded-md border bg-primary/10 text-primary border-primary/20 text-[10px] font-bold uppercase tracking-wider">
                                    <?php echo htmlspecialchars($row['category']); ?>
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider flex items-center gap-1 <?php echo $labelBg; ?>">
                                    <span class="material-symbols-outlined text-[12px]"><?php echo $icon; ?></span> <?php echo $matchLabel; ?>
                                </span>
                            </div>

                            <p class="text-sm text-on-surface-variant mb-3 flex items-center gap-1 truncate">
                                <span class="material-symbols-outlined text-[14px] text-tertiary">location_on</span>
                                <?php echo htmlspecialchars($row['location']); ?>
                            </p>

                            <!-- AI Reason Box -->
                            <div class="bg-primary/5 border border-primary/20 rounded-lg p-3 text-sm text-primary/90 flex items-start gap-2">
                                <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5">tips_and_updates</span>
                                <span class="leading-relaxed"><strong>AI Analysis:</strong> <?php echo htmlspecialchars($reason); ?></span>
                            </div>
                        </div>

                        <!-- Right: Score & Action -->
                        <div class="flex flex-row md:flex-col items-center justify-between md:justify-center gap-4 shrink-0 w-full md:w-32 mt-4 md:mt-0 pt-4 md:pt-0 border-t md:border-t-0 md:border-l border-white/10 md:pl-6">
                            <div class="text-center flex md:flex-col items-center gap-2 md:gap-0">
                                <div class="text-3xl md:text-4xl font-display-lg font-bold <?php echo $scoreColor; ?>"><?php echo $score; ?>%</div>
                                <div class="text-[10px] text-on-surface-variant uppercase tracking-widest font-bold">Similarity</div>
                            </div>
                            <a href="item_detail.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold text-white text-center w-auto md:w-full flex items-center justify-center gap-1 shadow-glow">
                                <span class="material-symbols-outlined text-[16px]">visibility</span> View
                            </a>
                        </div>

                    </div>

                <?php endforeach; ?>
            </div>

            <!-- Bottom Actions -->
            <div class="mt-8 flex gap-4 flex-wrap border-t border-white/10 pt-6">
                <button onclick="goBack('ai_matching.php')" class="btn-outline-glass px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span> New Search
                </button>
                <a href="dashboard.php" class="btn-outline-glass px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">home</span> Dashboard
                </a>
            </div>

        <?php endif; ?>
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

    function goBack(defaultUrl) {
        defaultUrl = defaultUrl || 'dashboard.php';
        if (document.referrer && document.referrer.includes(window.location.hostname)) {
            window.history.back();
        } else {
            window.location.href = defaultUrl;
        }
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