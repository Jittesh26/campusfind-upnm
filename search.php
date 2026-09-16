<?php
// search.php – MODERN GLASSMORPHISM SEARCH PAGE (Aeon Campus Design System)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

$user_id = $_SESSION['user_id'];

// Get filter values
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$report_type = isset($_GET['report_type']) ? trim($_GET['report_type']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Build the query
// 🔥 FIX: Explicitly hide internal 'Campus Tag' chat entries from public search
$sql = "SELECT * FROM item_reports WHERE category != 'Campus Tag'";
$params = [];
$types = "";

if (!empty($keyword)) {
    $sql .= " AND (item_name LIKE ? OR description LIKE ? OR location LIKE ?)";
    $like = "%$keyword%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($report_type)) {
    $sql .= " AND report_type = ?";
    $params[] = $report_type;
    $types .= "s";
}

if (!empty($date_from)) {
    $sql .= " AND report_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $sql .= " AND report_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$sql .= " ORDER BY report_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

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
    <title>CampusFind - Search</title>
    
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
        
        .input-glass { background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(140, 144, 159, 0.3); transition: all 0.3s ease; }
        .input-glass:focus-within, .input-glass.active-dropdown { border-color: #adc6ff !important; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2) !important; }
        
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 0 20px rgba(77, 142, 255, 0.2); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 0 30px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; }
        
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); opacity: 0.6; cursor: pointer; }

        /* Custom Scrollbar for Dropdowns */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

        /* Result Card Colored Borders */
        .result-card-lost { border-left: 4px solid #ffb4ab; }
        .result-card-found { border-left: 4px solid #44e2cd; }
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
<main class="w-full max-w-6xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">
    
    <!-- Header -->
    <div class="mb-8 border-b border-white/10 pb-6">
        <h1 class="font-headline-lg text-3xl font-bold text-white tracking-tight flex items-center gap-3">
            <span class="material-symbols-outlined text-primary text-4xl">search</span> Search Items
        </h1>
        <p class="text-on-surface-variant font-body-sm mt-2">Filter and browse through all reported lost and found items.</p>
    </div>

    <!-- Search & Filter Form -->
    <div class="glass-panel p-6 sm:p-8 rounded-2xl mb-8 relative z-20">
        <form method="GET" action="search.php" class="space-y-4">
            
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                
                <!-- Keyword Input -->
                <div class="md:col-span-5">
                    <label class="block font-label-sm text-on-surface-variant mb-1 uppercase tracking-wider text-[10px]">Keyword</label>
                    <div class="relative input-glass rounded-xl flex items-center px-4 py-2.5">
                        <span class="material-symbols-outlined text-outline mr-2 text-[18px]">search</span>
                        <input type="text" name="keyword" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 p-0 text-sm" placeholder="Search item name, description, location..." value="<?php echo htmlspecialchars($keyword); ?>">
                    </div>
                </div>

                <!-- Custom Dropdown: Category -->
                <div class="md:col-span-4 relative custom-dropdown" id="categoryDropdownWrapper">
                    <label class="block font-label-sm text-on-surface-variant mb-1 uppercase tracking-wider text-[10px]">Category</label>
                    <input type="hidden" name="category" id="categoryInput" value="<?php echo htmlspecialchars($category); ?>">
                    
                    <div class="input-glass rounded-xl flex items-center px-4 py-2.5 cursor-pointer h-[44px]" id="categoryBtn">
                        <span class="material-symbols-outlined text-outline mr-2 text-[18px] pointer-events-none">category</span>
                        <span id="categorySelectedText" class="w-full text-on-surface font-body-md text-sm select-none truncate pointer-events-none <?php echo empty($category) ? 'text-outline-variant' : ''; ?>">
                            <?php echo empty($category) ? 'All Categories' : htmlspecialchars($category); ?>
                        </span>
                        <span class="material-symbols-outlined text-outline transition-transform duration-200 pointer-events-none text-[18px]" id="categoryArrow">expand_more</span>
                    </div>

                    <div id="categoryMenu" class="absolute left-0 right-0 top-full mt-1 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden shadow-glass border border-white/10" style="transform: translateY(-10px);">
                        <div class="max-h-64 overflow-y-auto custom-scrollbar py-2">
                            <!-- All Categories Option -->
                            <div class="px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors custom-option-cat <?php echo empty($category) ? 'bg-primary/20' : ''; ?>" data-value="">
                                <div class="font-bold text-sm <?php echo empty($category) ? 'text-primary' : 'text-on-surface'; ?>">All Categories</div>
                            </div>
                            <?php
                            $cat_options = [
                                'Electronics & Gadgets' => 'Laptops, Phones, Chargers, Calculators, Headphones, Smartwatches',
                                'Cards, IDs & Documents' => 'Student Cards, IC, Licenses, Books, Notebooks',
                                'Bags & Apparel' => 'Backpacks, Pouches, Jackets, Shirts, Lanyards, Pencil Boxes',
                                'Personal Belongings' => 'Wallets, Water Bottles, Keys, Umbrellas, Glasses, Accessories',
                                'Sports & Equipment' => 'Rackets, Balls, Water Jugs, Gym Gear',
                                'Other' => 'Miscellaneous items'
                            ];
                            foreach($cat_options as $mainCat => $subText) {
                                $activeClass = ($category == $mainCat) ? 'bg-primary/20' : '';
                                $textColor = ($category == $mainCat) ? 'text-primary' : 'text-on-surface';
                                echo "<div class='px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors custom-option-cat $activeClass' data-value='$mainCat'>
                                        <div class='font-bold text-sm $textColor'>$mainCat</div>
                                        <div class='text-[11px] text-on-surface-variant opacity-70 leading-tight mt-0.5'>$subText</div>
                                      </div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Custom Dropdown: Type -->
                <div class="md:col-span-3 relative custom-dropdown" id="typeDropdownWrapper">
                    <label class="block font-label-sm text-on-surface-variant mb-1 uppercase tracking-wider text-[10px]">Report Type</label>
                    <input type="hidden" name="report_type" id="typeInput" value="<?php echo htmlspecialchars($report_type); ?>">
                    
                    <div class="input-glass rounded-xl flex items-center px-4 py-2.5 cursor-pointer h-[44px]" id="typeBtn">
                        <span class="material-symbols-outlined text-outline mr-2 text-[18px] pointer-events-none">filter_alt</span>
                        <span id="typeSelectedText" class="w-full text-on-surface font-body-md text-sm select-none truncate pointer-events-none <?php echo empty($report_type) ? 'text-outline-variant' : ''; ?>">
                            <?php 
                                if($report_type == 'lost') echo 'Lost Items';
                                elseif($report_type == 'found') echo 'Found Items';
                                else echo 'Both Types';
                            ?>
                        </span>
                        <span class="material-symbols-outlined text-outline transition-transform duration-200 pointer-events-none text-[18px]" id="typeArrow">expand_more</span>
                    </div>

                    <div id="typeMenu" class="absolute left-0 right-0 top-full mt-1 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden shadow-glass border border-white/10" style="transform: translateY(-10px);">
                        <div class="py-2">
                            <div class="px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors custom-option-type <?php echo empty($report_type) ? 'bg-primary/20' : ''; ?>" data-value="" data-text="Both Types">
                                <div class="font-bold text-sm <?php echo empty($report_type) ? 'text-primary' : 'text-on-surface'; ?>">Both Types</div>
                            </div>
                            <div class="px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors custom-option-type <?php echo ($report_type == 'lost') ? 'bg-primary/20' : ''; ?>" data-value="lost" data-text="Lost Items">
                                <div class="font-bold text-sm <?php echo ($report_type == 'lost') ? 'text-primary' : 'text-on-surface'; ?>">Lost Items <span class="text-error ml-1">●</span></div>
                            </div>
                            <div class="px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors custom-option-type <?php echo ($report_type == 'found') ? 'bg-primary/20' : ''; ?>" data-value="found" data-text="Found Items">
                                <div class="font-bold text-sm <?php echo ($report_type == 'found') ? 'text-primary' : 'text-on-surface'; ?>">Found Items <span class="text-secondary ml-1">●</span></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <!-- Date From -->
                <div class="md:col-span-3">
                    <label class="block font-label-sm text-on-surface-variant mb-1 uppercase tracking-wider text-[10px]">Date From</label>
                    <div class="relative input-glass rounded-xl flex items-center px-4 py-2.5">
                        <span class="material-symbols-outlined text-outline mr-2 text-[18px]">date_range</span>
                        <input type="date" name="date_from" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 p-0 text-sm [&::-webkit-calendar-picker-indicator]:invert-[1] [&::-webkit-calendar-picker-indicator]:opacity-60" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                </div>

                <!-- Date To -->
                <div class="md:col-span-3">
                    <label class="block font-label-sm text-on-surface-variant mb-1 uppercase tracking-wider text-[10px]">Date To</label>
                    <div class="relative input-glass rounded-xl flex items-center px-4 py-2.5">
                        <span class="material-symbols-outlined text-outline mr-2 text-[18px]">date_range</span>
                        <input type="date" name="date_to" class="w-full bg-transparent border-none text-on-surface placeholder:text-outline-variant focus:ring-0 p-0 text-sm [&::-webkit-calendar-picker-indicator]:invert-[1] [&::-webkit-calendar-picker-indicator]:opacity-60" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>

                <!-- Buttons -->
                <div class="md:col-span-6 flex gap-3 mt-4 md:mt-0">
                    <button type="submit" class="btn-primary text-white font-bold py-2.5 px-6 rounded-xl flex-1 flex items-center justify-center gap-2 shadow-glow text-sm">
                        <span class="material-symbols-outlined text-[18px]">search</span> Apply Filters
                    </button>
                    <a href="search.php" class="btn-outline-glass text-center py-2.5 px-6 rounded-xl font-bold text-sm flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">restart_alt</span> Clear
                    </a>
                </div>
            </div>

        </form>
    </div>

    <!-- Results Section -->
    <div class="flex items-center justify-between mb-4 px-2">
        <h3 class="text-white font-headline-md text-lg">Results <span class="text-on-surface-variant text-sm font-normal ml-2">(<?php echo $result->num_rows; ?> found)</span></h3>
    </div>

    <!-- EMPTY STATE -->
    <?php if ($result->num_rows === 0): ?>
        <div class="glass-panel p-10 rounded-2xl flex flex-col items-center justify-center text-center">
            <div class="w-20 h-20 bg-surface-container-highest border border-white/10 rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-on-surface-variant text-4xl">search_off</span>
            </div>
            <h3 class="text-white font-headline-md text-xl mb-2">No items match your search</h3>
            <p class="text-on-surface-variant text-sm max-w-md">Try adjusting your keyword, changing the category, or expanding the date range.</p>
        </div>
    <?php else: ?>
        
        <!-- RESULTS LIST -->
        <div class="space-y-4">
            <?php while ($row = $result->fetch_assoc()): 
                // Styling Logic
                $isLost = ($row['report_type'] === 'lost');
                $cardBorder = $isLost ? 'result-card-lost' : 'result-card-found';
                $typeBg = $isLost ? 'bg-error/20 text-error border-error/30' : 'bg-secondary/20 text-secondary border-secondary/30';
                
                $statusClass = 'bg-surface-container-highest text-on-surface-variant border-white/10'; // reported
                if ($row['status'] === 'returned') $statusClass = 'bg-primary/20 text-primary border-primary/30'; // Re-using primary for returned as success indicator in search
                elseif ($row['status'] === 'matched') $statusClass = 'bg-tertiary/20 text-tertiary border-tertiary/30';
                elseif ($row['status'] === 'verifying') $statusClass = 'bg-warning/20 text-warning border-warning/30';

                $locationDisplay = !empty($row['location']) ? htmlspecialchars($row['location']) :
                    (!empty($row['latitude']) && !empty($row['longitude']) ? "Pinned on Map" : "Unknown Location");
            ?>
                
                <div class="glass-panel p-4 sm:p-5 rounded-2xl hover:bg-white/5 transition-all duration-300 group flex flex-col md:flex-row gap-5 items-start md:items-center relative z-10 <?php echo $cardBorder; ?>">
                    
                    <!-- Left: Image -->
                    <div class="w-full sm:w-28 h-40 sm:h-28 shrink-0 rounded-xl overflow-hidden border border-white/10 relative bg-surface-container-high flex items-center justify-center">
                        <?php if (!empty($row['image_path']) && file_exists($row['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($row['image_path']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Item image">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-4xl text-white/20">image</span>
                        <?php endif; ?>
                    </div>

                    <!-- Center: Details -->
                    <div class="flex-1 w-full min-w-0">
                        <h3 class="font-headline-md text-lg text-white mb-2 truncate"><?php echo htmlspecialchars($row['item_name']); ?></h3>
                        
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider <?php echo $typeBg; ?>">
                                <?php echo ucfirst($row['report_type']); ?>
                            </span>
                            <span class="px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider <?php echo $statusClass; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                            <span class="px-2 py-0.5 rounded-md border bg-white/5 text-on-surface-variant border-white/10 text-[10px] font-bold uppercase tracking-wider">
                                <?php echo htmlspecialchars($row['category']); ?>
                            </span>
                        </div>

                        <p class="text-sm text-on-surface-variant mb-3 line-clamp-2 leading-relaxed">
                            <?php echo htmlspecialchars($row['description']); ?>
                        </p>

                        <div class="flex flex-wrap gap-4 text-xs text-on-surface-variant font-medium">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] text-tertiary">location_on</span>
                                <?php echo $locationDisplay; ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] text-primary">calendar_month</span>
                                <?php echo formatDateSafe($row['report_date']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Right: Action -->
                    <div class="w-full md:w-auto shrink-0 mt-2 md:mt-0">
                        <a href="item_detail.php?id=<?php echo $row['id']; ?>" class="btn-outline-glass px-5 py-2.5 rounded-xl text-sm font-bold text-white text-center w-full flex items-center justify-center gap-2 hover:bg-white/10 hover:border-white/20">
                            <span class="material-symbols-outlined text-[18px]">visibility</span> View Details
                        </a>
                    </div>

                </div>

            <?php endwhile; ?>
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

    // --- CUSTOM DROPDOWN LOGIC ---
    function setupCustomDropdown(btnId, menuId, arrowId, inputId, textId, optionClass, activeBgClass, activeTextClass) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        const arrow = document.getElementById(arrowId);
        const input = document.getElementById(inputId);
        const text = document.getElementById(textId);
        const options = document.querySelectorAll(optionClass);

        if(!btn) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            
            // Close all other custom dropdowns
            document.querySelectorAll('.glass-panel.absolute').forEach(m => {
                if(m.id !== menuId && m.id !== 'profileMenu' && !m.classList.contains('opacity-0')) {
                    m.classList.add('opacity-0', 'invisible');
                    m.style.transform = 'translateY(-10px)';
                }
            });

            const isClosed = menu.classList.contains('opacity-0');
            if (isClosed) {
                menu.classList.remove('opacity-0', 'invisible');
                menu.style.transform = 'translateY(0)';
                if(arrow) arrow.style.transform = 'rotate(180deg)';
                btn.classList.add('active-dropdown');
            } else {
                closeMenu();
            }
        });

        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const value = option.getAttribute('data-value');
                input.value = value;
                
                // For categories/types, grab only the main title text to display in the button
                // Or fallback to data-text attribute if provided
                let displayTxt = value;
                if(option.getAttribute('data-text')) {
                    displayTxt = option.getAttribute('data-text');
                } else if(option.querySelector('.font-bold')) {
                    displayTxt = option.querySelector('.font-bold').textContent;
                }
                
                // If it's an empty value (All), default text
                if (value === '' && btnId === 'categoryBtn') displayTxt = 'All Categories';
                
                text.textContent = displayTxt;
                text.classList.remove('text-outline-variant');
                
                options.forEach(opt => {
                    opt.classList.remove(activeBgClass);
                    const t = opt.querySelector('.font-bold');
                    if(t) { t.classList.remove(activeTextClass); t.classList.add('text-on-surface'); }
                });
                
                option.classList.add(activeBgClass);
                const t = option.querySelector('.font-bold');
                if(t) { t.classList.remove('text-on-surface'); t.classList.add(activeTextClass); }

                closeMenu();
            });
        });

        function closeMenu() {
            menu.classList.add('opacity-0', 'invisible');
            menu.style.transform = 'translateY(-10px)';
            if(arrow) arrow.style.transform = 'rotate(0deg)';
            btn.classList.remove('active-dropdown');
        }

        document.addEventListener('click', (e) => {
            if (!btn.contains(e.target) && !menu.contains(e.target)) {
                closeMenu();
            }
        });
    }

    // Initialize custom dropdowns
    setupCustomDropdown('categoryBtn', 'categoryMenu', 'categoryArrow', 'categoryInput', 'categorySelectedText', '.custom-option-cat', 'bg-primary/20', 'text-primary');
    setupCustomDropdown('typeBtn', 'typeMenu', 'typeArrow', 'typeInput', 'typeSelectedText', '.custom-option-type', 'bg-primary/20', 'text-primary');

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