<?php
// admin_weekly_report.php – PREDICTIVE & PRESCRIPTIVE ANALYTICS WITH AI VALUATION
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

// ============================================
// GEMINI API CONFIGURATION
// ============================================
$GEMINI_API_KEY = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . $GEMINI_API_KEY;

$report_data = null;
$error = '';

// ============================================
// AJAX EMAIL DISPATCH LOGIC
// ============================================
if (isset($_POST['ajax_dispatch'])) {
    header('Content-Type: application/json');
    require_once 'send_notification.php';
    
    $cached = $_SESSION['cached_report'] ?? [];
    $forecast = $cached['ai_forecast'] ?? 'Heightened vigilance required in general campus areas.';
    $actions = $cached['ai_actions'] ?? '<li>Conduct standard patrols.</li>';
    
    $hotspots_html = "";
    if (!empty($cached['current_week']['hotspots'])) {
        foreach($cached['current_week']['hotspots'] as $h) {
            $hotspots_html .= "<li style='margin-bottom: 5px;'><strong>{$h['location']}</strong> ({$h['count']} items reported)</li>";
        }
    } else {
        $hotspots_html = "<li>No specific hotspots identified this week.</li>";
    }

    $email_html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;'>
        <div style='background-color: #DC2626; color: white; padding: 20px; text-align: center;'>
            <h2 style='margin: 0; font-size: 24px;'>🚨 Campus Security Dispatch</h2>
        </div>
        <div style='padding: 30px; background-color: #f8fafc; color: #334155;'>
            <h3 style='color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;'>🔮 Predictive Forecast</h3>
            <p style='font-size: 16px; line-height: 1.6; color: #DC2626; font-weight: bold;'>$forecast</p>
            
            <h3 style='color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 30px;'>🔥 Active Hotspots</h3>
            <ul style='font-size: 15px; line-height: 1.6;'>$hotspots_html</ul>
            
            <h3 style='color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 30px;'>📋 Required Actions</h3>
            <ul style='font-size: 15px; line-height: 1.6;'>$actions</ul>
            
            <div style='margin-top: 40px; text-align: center;'>
                <a href='" . BASE_URL . "admin_dashboard.php' style='background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Open Command Center</a>
            </div>
        </div>
    </div>";

    // HARDCODED TO SEND TO YOUR EMAIL
    $result = sendEmail('SECURITY_OFFICER_EMAIL', 'Chief Security Officer', '🚨 HIGH PRIORITY: Campus Security Dispatch', $email_html, 'Security Dispatch Alert. Please check the dashboard.');
    
    if ($result === true) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'API Error or Server Blocked. Please check Resend API logs.']);
    }
    exit;
}

// ============================================
// DATA AGGREGATION ENGINE
// ============================================
function mapToBroadCategory($rawCat) {
    $rawCat = strtolower(trim($rawCat));
    $electronics = ['laptop', 'phone', 'calculator', 'charger', 'headphone', 'smartwatch', 'electronics & gadgets'];
    $documents = ['student card', 'book', 'ic', 'license', 'notebook', 'cards, ids & documents'];
    $apparel = ['pencil box', 'backpack', 'pouch', 'jacket', 'shirt', 'lanyard', 'bags & apparel'];
    $personal = ['water bottle', 'wallet', 'keys', 'umbrella', 'glasses', 'accessory', 'personal belongings'];
    $sports = ['racket', 'ball', 'water jug', 'gym gear', 'sports & equipment'];

    if (in_array($rawCat, $electronics)) return 'Electronics & Gadgets';
    if (in_array($rawCat, $documents)) return 'Cards, IDs & Documents';
    if (in_array($rawCat, $apparel)) return 'Bags & Apparel';
    if (in_array($rawCat, $personal)) return 'Personal Belongings';
    if (in_array($rawCat, $sports)) return 'Sports & Equipment';
    return 'Other';
}

function getPeriodStats($conn, $start_date, $end_date) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM item_reports WHERE category != 'Campus Tag' AND created_at >= ? AND created_at <= ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $total_lost = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    $stmt = $conn->prepare("SELECT id, item_name, description, category, status FROM item_reports WHERE category != 'Campus Tag' AND created_at >= ? AND created_at <= ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $all_items = $stmt->get_result();
    
    $broad_counts = ['Electronics & Gadgets' => 0, 'Cards, IDs & Documents' => 0, 'Bags & Apparel' => 0, 'Personal Belongings' => 0, 'Sports & Equipment' => 0, 'Other' => 0];
    $items_for_ai = [];

    while ($row = $all_items->fetch_assoc()) {
        $broad = mapToBroadCategory($row['category']);
        $broad_counts[$broad]++;
        $items_for_ai[] = [
            'name' => $row['item_name'],
            'desc' => $row['description'],
            'status' => $row['status']
        ];
    }
    
    arsort($broad_counts);
    $top_categories = [];
    $cat_chart_labels = [];
    $cat_chart_data = [];
    foreach ($broad_counts as $name => $val) {
        if ($val > 0) {
            $top_categories[] = ['category' => $name, 'count' => $val];
            $cat_chart_labels[] = $name;
            $cat_chart_data[] = $val;
        }
    }

    $stmt = $conn->prepare("SELECT location, COUNT(*) as count FROM item_reports WHERE category != 'Campus Tag' AND created_at >= ? AND created_at <= ? AND location IS NOT NULL AND location != '' GROUP BY location ORDER BY count DESC LIMIT 5");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $hotspots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT DAYNAME(created_at) as day_name, COUNT(*) as count FROM item_reports WHERE category != 'Campus Tag' AND created_at >= ? AND created_at <= ? GROUP BY day_name");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $days_res = $stmt->get_result();
    
    $days_map = ['Monday'=>0, 'Tuesday'=>0, 'Wednesday'=>0, 'Thursday'=>0, 'Friday'=>0, 'Saturday'=>0, 'Sunday'=>0];
    $peak_count = 0;
    $peak_day = "N/A";
    
    while ($row = $days_res->fetch_assoc()) {
        $days_map[$row['day_name']] = $row['count'];
        if ($row['count'] > $peak_count) {
            $peak_count = $row['count'];
            $peak_day = $row['day_name'] . " (" . $row['count'] . " items)";
        }
    }

    return [
        'total' => $total_lost,
        'categories' => array_slice($top_categories, 0, 3),
        'chart_cat_labels' => $cat_chart_labels,
        'chart_cat_data' => $cat_chart_data,
        'chart_days_data' => array_values($days_map),
        'hotspots' => $hotspots,
        'peak_day' => $peak_day,
        'raw_items' => $items_for_ai
    ];
}

function callGeminiWithRetry($url, $payload, $maxAttempts = 4) {
    $attempt = 0;
    $wait = 2; 
    do {
        $attempt++;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) return ['success' => true, 'response' => $response, 'code' => $http_code];
        if (($http_code === 503 || $http_code === 429) && $attempt < $maxAttempts) {
            sleep($wait); $wait *= 2; continue;
        }
        return ['success' => false, 'response' => $response, 'code' => $http_code];
    } while ($attempt < $maxAttempts);
    return ['success' => false, 'code' => 500];
}

if (isset($_POST['generate']) || !isset($_SESSION['cached_report'])) {
    $now = date('Y-m-d 23:59:59');
    $seven_days_ago = date('Y-m-d 00:00:00', strtotime('-7 days'));
    $fourteen_days_ago = date('Y-m-d 00:00:00', strtotime('-14 days'));

    $current_week = getPeriodStats($conn, $seven_days_ago, $now);
    $prev_week = getPeriodStats($conn, $fourteen_days_ago, $seven_days_ago);

    $diff = $current_week['total'] - $prev_week['total'];
    if ($prev_week['total'] > 0) {
        $pct_change = round(($diff / $prev_week['total']) * 100, 1);
        $trend_text = ($diff >= 0 ? "+" : "") . $pct_change . "% vs Prev Week";
    } else {
        $trend_text = "Baseline established. Tracking active.";
    }

    $items_text = "";
    foreach ($current_week['raw_items'] as $item) {
        $items_text .= "- Name: {$item['name']}, Desc: {$item['desc']}, Status: {$item['status']}\n";
    }

    $prompt = "You are a Chief Security AI and Valuation Expert for a university campus.\n";
    $prompt .= "Analyze these lost item statistics and estimate their market value in Malaysian Ringgit (RM).\n\n";
    $prompt .= "1. Evaluate the items below. Estimate value based on brands/descriptions (e.g., 'Gucci Wallet' = 2000, 'HP Laptop' = 3000, 'Water Bottle' = 20).\n";
    $prompt .= "2. Calculate total RM value of items that are NOT 'returned' (value_at_risk).\n";
    $prompt .= "3. Calculate total RM value of items that ARE 'returned' (value_recovered).\n\n";
    $prompt .= "STATISTICS:\n";
    $prompt .= "- Peak Day: {$current_week['peak_day']}\n";
    $prompt .= "- Items Logged:\n" . ($items_text ?: "No items logged.\n") . "\n";
    $prompt .= "Return ONLY a raw, valid JSON object with EXACTLY these keys:\n";
    $prompt .= '{"summary": "<p>2 sentence risk summary</p>", "forecast": "1 sentence prediction of where losses will spike next week", "actions": "<li>Action 1</li><li>Action 2</li>", "value_at_risk": 3500, "value_recovered": 1200}';

    $payload = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['response_mime_type' => 'application/json']
    ];

    $result = callGeminiWithRetry($GEMINI_URL, $payload);

    if ($result['success']) {
        $res_data = json_decode($result['response'], true);
        $ai_text = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $ai_json = json_decode($ai_text, true);
        
        $ai_summary = $ai_json['summary'] ?? '<p>Summary unavailable.</p>';
        $ai_forecast = $ai_json['forecast'] ?? 'Vigilance required.';
        $ai_actions = "<ul class='space-y-2 print-actions-list list-disc list-inside text-sm mt-2'>" . ($ai_json['actions'] ?? '<li>Standard patrols</li>') . "</ul>";
        $value_at_risk = intval($ai_json['value_at_risk'] ?? 0);
        $value_recovered = intval($ai_json['value_recovered'] ?? 0);
    } else {
        $http_code = $result['code'];
        $ai_summary = "<p class='text-error'>⚠️ Predictive AI offline (Google Server HTTP $http_code). Rate limit exceeded.</p>";
        $ai_forecast = "API Overloaded. Retrying later.";
        $ai_actions = "<ul class='list-disc pl-4 text-sm text-error'><li>Manual monitoring required.</li></ul>";
        $value_at_risk = 0;
        $value_recovered = 0;
    }

    $ai_summary = str_replace(['```html', '```'], '', $ai_summary);

    $report_data = [
        'generated_at' => date('d M Y, h:i A'),
        'current_week' => $current_week,
        'prev_week' => $prev_week,
        'trend_text' => $trend_text,
        'ai_summary' => $ai_summary,
        'ai_forecast' => $ai_forecast,
        'ai_actions' => $ai_actions,
        'value_at_risk' => $value_at_risk,
        'value_recovered' => $value_recovered
    ];
    $_SESSION['cached_report'] = $report_data;
} else {
    $report_data = $_SESSION['cached_report'];
}

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
    <title>CampusFind - AI Analytics Report</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
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
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; font-family: 'Inter', sans-serif; }
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: 0 0 20px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }

        /* ==========================================================================
           STRICT PRINT CSS (ACTIVATES ONLY DURING HTML2PDF EXPORT TO FIT 1 PAGE)
           ========================================================================== */
        .pdf-mode {
            background-color: #ffffff !important;
            color: #000000 !important;
            padding: 20px !important; /* Smaller padding to fit 1 page */
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .pdf-mode * {
            color: #000000 !important;
            border-color: #e5e7eb !important; 
        }
        .pdf-mode .glass-panel,
        .pdf-mode .bg-white\/5,
        .pdf-mode .bg-surface-container-low,
        .pdf-mode .bg-primary\/5 {
            background: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
        
        /* Remove specific dark backgrounds on badges */
        .pdf-mode .bg-error\/20 { background-color: #fee2e2 !important; color: #b91c1c !important; border-color: #fca5a5 !important; }
        .pdf-mode .bg-warning\/20 { background-color: #fef3c7 !important; color: #b45309 !important; border-color: #fcd34d !important; }
        .pdf-mode .bg-primary\/20 { background-color: #dbeafe !important; color: #1d4ed8 !important; border-color: #93c5fd !important; }
        .pdf-mode .bg-tertiary\/10 { background-color: #ffedd5 !important; border-color: #fdba74 !important; }

        /* Hide specific elements during PDF generation */
        .pdf-mode .no-print { display: none !important; }

        /* Force chart backgrounds to white and SHRINK HEIGHT for 1-page fit */
        .pdf-mode canvas { background-color: #ffffff !important; }
        .pdf-mode .h-\[250px\] { height: 160px !important; min-height: 160px !important; max-height: 160px !important;}
        
        /* AGGRESSIVE COMPRESSION TO FIT A4 */
        .pdf-mode .mb-8 { margin-bottom: 12px !important; }
        .pdf-mode .p-6, .pdf-mode .p-4 { padding: 12px !important; }
        .pdf-mode .gap-8 { gap: 12px !important; }
        .pdf-mode .gap-4 { gap: 10px !important; }
        .pdf-mode .grid-cols-1.md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
        .pdf-mode .grid-cols-1.lg\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        .pdf-mode .grid-cols-1.md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        
        /* Typography Shrinking */
        .pdf-mode h1 { font-size: 20pt !important; border-bottom: 2px solid #000 !important; padding-bottom: 8px !important; margin-bottom: 12px !important; }
        .pdf-mode h2 { font-size: 14pt !important; margin-bottom: 8px !important; }
        .pdf-mode h3 { font-size: 12pt !important; margin-bottom: 8px !important; }
        .pdf-mode .text-2xl, .pdf-mode .text-4xl { font-size: 16pt !important; }
        .pdf-mode p, .pdf-mode li { font-size: 10pt !important; line-height: 1.3 !important; margin-bottom: 4px !important; }
        .pdf-mode .text-xs, .pdf-mode .text-\[10px\] { font-size: 8pt !important; }
        .pdf-mode .print-actions-list { color: #000 !important; }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-white relative min-h-screen pb-20 flex flex-col">

<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60 no-print" id="bg-shader-wrapper">
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
</div>

<div class="no-print" id="nav-wrapper">
    <?php include 'navbar.php'; ?>
</div>

<main class="w-full max-w-7xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">
    
    <div id="pdf-report-area" class="glass-panel p-6 sm:p-10 rounded-2xl relative overflow-hidden bg-[#10131a]">
        
        <!-- Header -->
        <div class="mb-8 border-b border-white/10 pb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="font-headline-lg text-2xl sm:text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-4xl no-print">monitoring</span> Security & Analytics Report
                </h1>
                <p class="text-on-surface-variant font-body-sm mt-2">Generated at: <strong class="text-white"><?php echo $report_data['generated_at']; ?></strong></p>
            </div>
            <div class="flex gap-2 w-full md:w-auto no-print">
                <button onclick="exportPDF()" class="btn-outline-glass flex-1 md:flex-none px-4 rounded-xl flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">print</span> Export PDF
                </button>
                <form method="POST" id="refreshForm" class="flex-1 md:flex-none">
                    <button type="submit" name="generate" onclick="triggerRefreshLoader()" class="btn-primary text-white font-bold py-3 px-6 rounded-xl flex items-center justify-center gap-2 shadow-glow w-full text-sm transition-all">
                        <span class="material-symbols-outlined text-[18px]">refresh</span> Re-run AI
                    </button>
                </form>
            </div>
        </div>

        <!-- Financial KPI Strip -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-error/20 flex items-center justify-center border border-error/30 text-error shrink-0">
                    <span class="material-symbols-outlined">warning</span>
                </div>
                <div>
                    <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-0.5">Asset Value at Risk</div>
                    <div class="text-2xl font-bold text-white">RM <?php echo number_format($report_data['value_at_risk']); ?></div>
                </div>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-success/20 flex items-center justify-center border border-success/30 text-success shrink-0">
                    <span class="material-symbols-outlined">verified_user</span>
                </div>
                <div>
                    <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-0.5">Value Recovered</div>
                    <div class="text-2xl font-bold text-white">RM <?php echo number_format($report_data['value_recovered']); ?></div>
                </div>
            </div>
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-primary/20 flex items-center justify-center border border-primary/30 text-primary shrink-0">
                    <span class="material-symbols-outlined">insights</span>
                </div>
                <div>
                    <div class="text-[10px] text-white/50 uppercase tracking-widest font-bold mb-0.5">Recovery Efficiency</div>
                    <div class="text-2xl font-bold text-white">
                        <?php 
                            $eff = $report_data['value_at_risk'] > 0 
                                ? round(($report_data['value_recovered'] / $report_data['value_at_risk']) * 100, 1) 
                                : 0;
                            echo $eff . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Predictive Recommendations -->
        <div class="mb-8 bg-primary/5 border border-primary/20 rounded-2xl p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/10 blur-3xl rounded-full pointer-events-none no-print"></div>
            
            <div class="flex justify-between items-start mb-4 relative z-10">
                <h2 class="font-headline-md text-lg text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">robot_2</span> Predictive AI Summary
                </h2>
                <button onclick="dispatchSecurity()" class="bg-error/20 hover:bg-error/30 border border-error/30 text-error px-4 py-2 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 no-print shadow-glass">
                    <span class="material-symbols-outlined text-[16px]">admin_panel_settings</span> Dispatch Alerts
                </button>
            </div>
            
            <div class="text-sm leading-relaxed text-white/90 relative z-10">
                <?php echo $report_data['ai_summary']; ?>
                
                <div class="bg-tertiary/10 border border-tertiary/30 rounded-lg p-3 my-3 text-tertiary">
                    <strong class="flex items-center gap-2 text-xs uppercase tracking-widest mb-1"><span class="material-symbols-outlined text-[16px]">online_prediction</span> Predictive Forecast (Next 7 Days):</strong>
                    <span class="text-white/90 font-medium"><?php echo htmlspecialchars(strip_tags($report_data['ai_forecast'])); ?></span>
                </div>
                
                <h5 class="font-bold text-sm text-white mb-2">Prescriptive Actions Needed:</h5>
                <?php echo $report_data['ai_actions']; ?>
            </div>
        </div>

        <!-- 2-Column Grid: Visual Charts & Metrics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            
            <!-- Chart: Category Breakdown -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6 flex flex-col">
                <h3 class="font-headline-md text-base text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary">donut_large</span> Loss Distribution
                </h3>
                <div class="flex-1 w-full h-[250px] relative flex items-center justify-center">
                    <?php if (empty($report_data['current_week']['chart_cat_data'])): ?>
                        <span class="text-white/40 text-sm">No data available</span>
                    <?php else: ?>
                        <canvas id="categoryChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chart: Incidents by Day -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6 flex flex-col">
                <h3 class="font-headline-md text-base text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-tertiary">bar_chart</span> Incident Timeline
                </h3>
                <div class="flex-1 w-full h-[250px] relative flex items-center justify-center">
                    <?php if (array_sum($report_data['current_week']['chart_days_data']) == 0): ?>
                        <span class="text-white/40 text-sm">No data available</span>
                    <?php else: ?>
                        <canvas id="dayChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Hotspots & Trend -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <!-- Top 5 Hotspots -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <h3 class="font-headline-md text-base text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-error">local_fire_department</span> Active Hotspots
                </h3>
                
                <?php if (empty($report_data['current_week']['hotspots'])): ?>
                    <div class="text-white/40 text-sm text-center py-4">No location data recorded for the last 7 days.</div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($report_data['current_week']['hotspots'] as $h): 
                            $count = $h['count'];
                            if ($count >= 5) { $risk_label = 'Severe'; $risk_class = 'bg-error/20 text-error border-error/30'; } 
                            elseif ($count >= 3) { $risk_label = 'Elevated'; $risk_class = 'bg-warning/20 text-warning border-warning/30'; }
                            else { $risk_label = 'Active'; $risk_class = 'bg-primary/20 text-primary border-primary/30'; }
                        ?>
                            <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-high/50 border border-white/5">
                                <span class="text-white text-sm font-medium flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[16px] text-tertiary">location_on</span> <?php echo htmlspecialchars($h['location']); ?>
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="text-white/70 text-xs font-bold"><?php echo $count; ?> item<?php echo $count === 1 ? '' : 's'; ?></span>
                                    <span class="px-2 py-0.5 rounded border text-[9px] font-bold uppercase tracking-wider <?php echo $risk_class; ?>"><?php echo $risk_label; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Trend Analysis (Week-over-Week) -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6 flex flex-col justify-center">
                <h3 class="font-headline-md text-base text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-info text-[#0dcaf0]">compare_arrows</span> Volume Comparison
                </h3>
                
                <div class="flex items-center justify-between px-4 mb-4">
                    <div class="text-center">
                        <div class="text-4xl font-display-lg font-bold text-white mb-1"><?php echo $report_data['current_week']['total']; ?></div>
                        <div class="text-[10px] text-on-surface-variant uppercase tracking-widest font-bold">Past 7 Days</div>
                    </div>
                    <div class="h-10 w-px bg-white/10"></div>
                    <div class="text-center">
                        <div class="text-4xl font-display-lg font-bold text-white/50 mb-1"><?php echo $report_data['prev_week']['total']; ?></div>
                        <div class="text-[10px] text-on-surface-variant uppercase tracking-widest font-bold">Prior 7 Days</div>
                    </div>
                </div>

                <div class="bg-surface-container-low border border-white/5 p-3 rounded-xl text-center">
                    <?php
                        $trend = $report_data['trend_text'];
                        $trendClass = 'text-white/60';
                        $trendIcon = 'horizontal_rule';
                        if (strpos($trend, '+') !== false) {
                            $trendClass = 'text-warning'; // Increase in lost items = bad
                            $trendIcon = 'trending_up';
                        } elseif (strpos($trend, '-') !== false) {
                            $trendClass = 'text-success'; // Decrease in lost items = good
                            $trendIcon = 'trending_down';
                        }
                    ?>
                    <div class="flex items-center justify-center gap-2 font-bold text-sm <?php echo $trendClass; ?>">
                        <span class="material-symbols-outlined text-[20px]"><?php echo $trendIcon; ?></span>
                        <?php echo htmlspecialchars($trend); ?>
                    </div>
                </div>
            </div>

        </div>

    </div>
</main>

<script>
    // --- Chart.js Variables ---
    let catChartInstance = null;
    let dayChartInstance = null;

    document.addEventListener('DOMContentLoaded', function() {
        Chart.defaults.color = 'rgba(255, 255, 255, 0.6)';
        Chart.defaults.font.family = "'Inter', sans-serif";

        <?php if (!empty($report_data['current_week']['chart_cat_data'])): ?>
        const ctxCat = document.getElementById('categoryChart').getContext('2d');
        catChartInstance = new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($report_data['current_week']['chart_cat_labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($report_data['current_week']['chart_cat_data']); ?>,
                    backgroundColor: ['#6366F1', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#64748B'],
                    borderWidth: 0, hoverOffset: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }, cutout: '70%' }
        });
        <?php endif; ?>

        <?php if (array_sum($report_data['current_week']['chart_days_data']) > 0): ?>
        const ctxDay = document.getElementById('dayChart').getContext('2d');
        dayChartInstance = new Chart(ctxDay, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Lost Items',
                    data: <?php echo json_encode($report_data['current_week']['chart_days_data']); ?>,
                    backgroundColor: '#6366F1', borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }
            }
        });
        <?php endif; ?>
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

    // --- Prescriptive Action Button (Real Email Dispatch via AJAX) ---
    function dispatchSecurity() {
        Swal.fire({
            title: 'Deploy Security Alerts?',
            text: "This will transmit the AI forecast and active hotspot data to the configured security officer email.",
            icon: 'warning',
            iconColor: '#EF4444',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Yes, Transmit Orders',
            background: '#1E293B', color: '#FFFFFF',
            customClass: { popup: 'dark-glass-modal' }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Transmitting...',
                    html: 'Contacting mail server...',
                    allowOutsideClick: false, showConfirmButton: false,
                    background: '#1E293B', color: '#FFFFFF',
                    customClass: { popup: 'dark-glass-modal' },
                    didOpen: () => { Swal.showLoading(); }
                });
                
                const formData = new FormData();
                formData.append('ajax_dispatch', '1');

                fetch('admin_weekly_report.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Alerts Dispatched!', text: 'Security officers have been notified.', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Dispatch Failed', text: data.error, background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } });
                    }
                })
                .catch(error => {
                    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to the server.', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } });
                });
            }
        });
    }

    // --- PDF EXPORT (html2pdf with Custom Print Class Injection) ---
    function exportPDF() {
        Swal.fire({
            title: 'Generating PDF...',
            html: 'Formatting report to fit 1 page...',
            allowOutsideClick: false, showConfirmButton: false,
            background: '#1E293B', color: '#FFFFFF',
            customClass: { popup: 'dark-glass-modal' },
            didOpen: () => { Swal.showLoading(); }
        });
        
        const element = document.getElementById('pdf-report-area');
        
        // 1. Hide UI elements
        document.getElementById('bg-shader-wrapper').style.display = 'none';
        document.getElementById('nav-wrapper').style.display = 'none';
        
        // 2. Add the specific 'pdf-mode' CSS class
        element.classList.add('pdf-mode');

        // 3. Temporarily force Chart.js to render with black text for the white paper
        Chart.defaults.color = '#000000';
        if(catChartInstance) catChartInstance.update('none');
        if(dayChartInstance) {
            dayChartInstance.options.scales.y.grid.color = 'rgba(0,0,0,0.1)';
            dayChartInstance.update('none');
        }

        const opt = {
            margin:       0.2, // Tiny margin to save space
            filename:     'CampusFind_Analytics_Report.pdf',
            image:        { type: 'jpeg', quality: 1 },
            html2canvas:  { scale: 2, useCORS: true }, 
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            // 4. Restore everything back to dark mode normal state
            element.classList.remove('pdf-mode');
            document.getElementById('bg-shader-wrapper').style.display = 'block';
            document.getElementById('nav-wrapper').style.display = 'block';
            
            Chart.defaults.color = 'rgba(255, 255, 255, 0.6)';
            if(catChartInstance) catChartInstance.update('none');
            if(dayChartInstance) {
                dayChartInstance.options.scales.y.grid.color = 'rgba(255,255,255,0.05)';
                dayChartInstance.update('none');
            }

            Swal.close();
        });
    }

    function triggerRefreshLoader() {
        Swal.fire({
            title: 'Generating Report',
            html: 'Connecting to Google Gemini AI...<br>Compiling metrics and forecasts.',
            allowOutsideClick: false, showConfirmButton: false,
            background: '#1E293B', color: '#FFFFFF',
            customClass: { popup: 'dark-glass-modal' },
            didOpen: () => { Swal.showLoading(); }
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