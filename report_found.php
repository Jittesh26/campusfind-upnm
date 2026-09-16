<?php
// report_found.php – SPATIAL INTELLIGENCE FORM (Aeon Campus Design System)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once 'db_connect.php';
require_once 'csrf.php';

$user_id = $_SESSION['user_id'];

// --- DB UPGRADE: Ensure incident_time column exists ---
$check_time = $conn->query("SHOW COLUMNS FROM item_reports LIKE 'incident_time'");
if ($check_time && $check_time->num_rows == 0) {
    $conn->query("ALTER TABLE item_reports ADD COLUMN incident_time VARCHAR(50) DEFAULT 'Uncertain' AFTER report_date");
}

$message = '';
$error = '';
$item_name = $category = $description = $location = $report_date = $incident_time = $verification_question = $verification_answer = '';
$latitude = $longitude = '';

// Fetching ALL location data including the GeoJSON boundary!
$locations_result = $conn->query("SELECT id, location_name, lat, lng, default_radius, boundary_geojson FROM campus_locations ORDER BY location_name");
$campus_locations = [];
while ($row = $locations_result->fetch_assoc()) {
    $campus_locations[] = $row;
}

function resizeImage($source_path, $target_path, $max_width = 800, $max_height = 800, $quality = 80) {
    if (!file_exists($source_path)) return false;
    list($width, $height, $type) = getimagesize($source_path);
    if ($width <= $max_width && $height <= $max_height) { copy($source_path, $target_path); return true; }
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);
    $new_image = imagecreatetruecolor($new_width, $new_height);
    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG: $source = imagecreatefrompng($source_path); imagealphablending($new_image, false); imagesavealpha($new_image, true); break;
        case IMAGETYPE_WEBP: $source = imagecreatefromwebp($source_path); break;
        default: copy($source_path, $target_path); return true;
    }
    imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($new_image, $target_path, $quality); break;
        case IMAGETYPE_PNG: imagepng($new_image, $target_path, 9); break;
        case IMAGETYPE_WEBP: imagewebp($new_image, $target_path, $quality); break;
    }
    imagedestroy($source); imagedestroy($new_image);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $item_name = trim($_POST['item_name']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $report_date = trim($_POST['report_date']);
        $incident_time = trim($_POST['incident_time'] ?? 'Uncertain');
        $verification_question = trim($_POST['verification_question']);
        $verification_answer = trim($_POST['verification_answer']);
        
        // Geospatial Data
        $location = trim($_POST['location']); 
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $search_radius = !empty($_POST['search_radius']) ? intval($_POST['search_radius']) : 100;
        $location_method = !empty($_POST['location_method']) ? trim($_POST['location_method']) : 'map_pin';
        $campus_location_id = !empty($_POST['campus_location_id']) ? intval($_POST['campus_location_id']) : null;

        // 🔥 BACKEND MAP SYNC: If they picked a location but JS failed to pass coordinates, fetch them from DB
        if ((empty($latitude) || empty($longitude)) && !empty($location)) {
            $loc_stmt = $conn->prepare("SELECT lat, lng FROM campus_locations WHERE location_name = ?");
            $loc_stmt->bind_param("s", $location);
            $loc_stmt->execute();
            $loc_res = $loc_stmt->get_result()->fetch_assoc();
            if ($loc_res && $loc_res['lat'] != 0) {
                $latitude = floatval($loc_res['lat']);
                $longitude = floatval($loc_res['lng']);
            }
        }

        if (empty($item_name) || empty($category) || empty($description) || empty($report_date) || empty($incident_time) || empty($verification_question) || empty($verification_answer)) {
            $error = 'All fields except photo are required.';
        } elseif (empty($location) && ($latitude === null || $longitude === null)) {
            $error = 'Please select a location from the dropdown OR pin it on the map.';
        } else {
            $primary_image = '';
            $uploaded_images = [];
            $upload_failed = false;

            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $total_files = count($_FILES['images']['name']);
                $max_files = 5;
                $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $max_file_size = 5 * 1024 * 1024;

                for ($i = 0; $i < min($total_files, $max_files); $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['images']['tmp_name'][$i];
                        if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) { $error = "Images exceed 5MB limit."; $upload_failed = true; break; }
                        $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $tmp_name); finfo_close($finfo);
                        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (!array_key_exists($mime, $allowed)) { $error = "Invalid format."; $upload_failed = true; break; }
                        $filename = 'uploads/found_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $i . '.' . $allowed[$mime];
                        if (move_uploaded_file($tmp_name, $filename)) {
                            resizeImage($filename, $filename, 800, 800, 80);
                            if ($i === 0) $primary_image = $filename;
                            $uploaded_images[] = $filename;
                        }
                    }
                }
            }

            if (!$upload_failed) {
                // Insert with new Spatial Columns + Verification Rules
                $stmt = $conn->prepare("INSERT INTO item_reports (user_id, report_type, item_name, category, description, location, latitude, longitude, search_radius, location_method, campus_location_id, report_date, incident_time, image_path, status, verification_question, verification_answer) VALUES (?, 'found', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reported', ?, ?)");
                
                $stmt->bind_param("issssddisisssss", $user_id, $item_name, $category, $description, $location, $latitude, $longitude, $search_radius, $location_method, $campus_location_id, $report_date, $incident_time, $primary_image, $verification_question, $verification_answer);

                if ($stmt->execute()) {
                    $report_id = $stmt->insert_id;
                    if (!empty($uploaded_images)) {
                        foreach ($uploaded_images as $index => $img_path) {
                            $img_stmt = $conn->prepare("INSERT INTO item_images (item_report_id, image_path, sort_order) VALUES (?, ?, ?)");
                            $img_stmt->bind_param("isi", $report_id, $img_path, $index); $img_stmt->execute();
                        }
                    }

                    $message = '✅ Found item reported securely with spatial data!';
                    
                    // AI Match integration
                    require_once 'ai_auto_match.php';
                    $ai_description = $description . " [Time Logged: " . $incident_time . "]";
                    $matches = aiAutoMatch($report_id, 'found', $category, $location, $ai_description, $primary_image);
                    if ($matches > 0) $message .= " 🤖 AI found $matches match(es)! Notifications dispatched.";
                    
                    $item_name = $category = $description = $location = $report_date = $incident_time = $verification_question = $verification_answer = '';
                    $latitude = $longitude = '';
                } else {
                    $error = 'Database Error. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - Report Found Item</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- MapLibre GL CSS -->
    <link href="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css" rel="stylesheet" />

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
                        "surface-container-lowest": "#0b0e15", "success": "#10B981"
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; font-family: 'Inter', sans-serif;}
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .btn-success-glass { background: linear-gradient(135deg, #10B981, #059669); border: none; box-shadow: 0 4px 16px rgba(16, 185, 129, 0.35); transition: all 0.3s ease;}
        .btn-success-glass:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; cursor: pointer; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        .btn-outline-glass.active-rad { background: rgba(68, 226, 205, 0.2); border-color: #44e2cd; color: #6EE7B7; }
        .input-glass { background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(140, 144, 159, 0.3); transition: all 0.3s ease; }
        .input-glass:focus-within, .input-glass.active-dropdown { border-color: #44e2cd !important; box-shadow: 0 0 10px rgba(68, 226, 205, 0.2) !important; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); opacity: 0.6; cursor: pointer; }
        
        /* Updated Dropzone CSS for Found Items */
        .dropzone { border: 2px dashed rgba(68, 226, 205, 0.4); background: rgba(0,0,0,0.2); transition: all 0.3s ease; cursor: pointer; min-height: 240px;}
        .dropzone:hover, .dropzone.dragover { border-color: #44e2cd; background: rgba(68, 226, 205, 0.05); }
        
        #locationPicker { height: 350px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.12); width: 100%; z-index: 1; outline: none; }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        .swal2-popup.dark-glass-modal { background: rgba(30, 41, 59, 0.95) !important; backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: #e1e2ec; }

        /* Animation for Image Gallery */
        .animation-fadeIn { animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards; opacity: 0; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-secondary selection:text-white relative min-h-screen pb-20">

<!-- Global Background Shader (RESTORED ANIMATION) -->
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

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-10 fade-in-up visible">
    <div class="mb-8 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-secondary/20 border border-secondary/30 flex items-center justify-center">
            <span class="material-symbols-outlined text-secondary text-2xl">check_circle</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Report Found Item</h1>
            <p class="text-on-surface-variant text-sm mt-1">Our AI engine will scan recent lost reports to find a match instantly.</p>
        </div>
    </div>

    <?php if ($message): ?><div class="mb-6 p-4 rounded-xl bg-success/20 border border-success/50 text-success text-sm flex items-center gap-2"><span class="material-symbols-outlined text-lg">check_circle</span> <?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-6 p-4 rounded-xl bg-error/20 border border-error/50 text-error text-sm flex items-center gap-2"><span class="material-symbols-outlined text-lg">error</span> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Form -->
        <div class="lg:col-span-2 space-y-8">
            <form id="reportForm" method="POST" enctype="multipart/form-data" class="space-y-8">
                <?php csrfInput(); ?>

                <!-- Section 1: Basic Info -->
                <div class="glass-panel p-6 sm:p-8 rounded-2xl">
                    <h3 class="text-xl text-white mb-6 flex items-center gap-2 border-b border-white/10 pb-4"><span class="material-symbols-outlined text-secondary">info</span> Basic Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-on-surface-variant mb-2 text-sm font-semibold">Item Name <span class="text-error">*</span></label>
                            <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                                <span class="material-symbols-outlined text-outline mr-3">label</span>
                                <input name="item_name" class="w-full bg-transparent border-none text-white focus:ring-0 p-0 text-sm" placeholder="e.g. Blue HP Laptop" type="text" value="<?php echo htmlspecialchars($item_name); ?>" required/>
                            </div>
                        </div>

                        <div class="md:col-span-2 relative custom-dropdown" id="categoryDropdownWrapper">
                            <label class="block text-on-surface-variant mb-2 text-sm font-semibold">Category <span class="text-error">*</span></label>
                            <input type="hidden" name="category" id="categoryInput" value="<?php echo htmlspecialchars($category); ?>" required>
                            
                            <div class="input-glass rounded-xl flex items-center px-4 py-3 cursor-pointer h-[50px]" id="categoryBtn">
                                <span class="material-symbols-outlined text-outline mr-3">category</span>
                                <span id="categorySelectedText" class="w-full text-white text-sm truncate"><?php echo empty($category) ? 'Select Category' : htmlspecialchars($category); ?></span>
                                <span class="material-symbols-outlined text-outline transition-transform" id="categoryArrow">expand_more</span>
                            </div>

                            <div id="categoryMenu" class="absolute left-0 right-0 top-full mt-2 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden">
                                <div class="max-h-64 overflow-y-auto custom-scrollbar py-2">
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
                                        $activeClass = ($category == $mainCat) ? 'bg-secondary/20' : '';
                                        $textColor = ($category == $mainCat) ? 'text-secondary' : 'text-white';
                                        echo "<div class='px-4 py-2.5 hover:bg-white/10 cursor-pointer transition-colors custom-option-cat text-sm $textColor $activeClass' data-value='$mainCat'>
                                                <strong class='font-bold'>$mainCat</strong>
                                                <div class='text-[11px] text-white/50 leading-tight mt-0.5'>$subText</div>
                                              </div>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-on-surface-variant mb-2 text-sm font-semibold">Date Found <span class="text-error">*</span></label>
                            <div class="relative input-glass rounded-xl flex items-center px-4 py-3">
                                <span class="material-symbols-outlined text-outline mr-3">calendar_month</span>
                                <input name="report_date" class="w-full bg-transparent border-none text-white focus:ring-0 p-0 text-sm" type="date" value="<?php echo htmlspecialchars($report_date); ?>" required/>
                            </div>
                        </div>

                        <div class="relative custom-dropdown" id="timeDropdownWrapper">
                            <label class="block text-on-surface-variant mb-2 text-sm font-semibold">Estimated Time <span class="text-error">*</span></label>
                            <input type="hidden" name="incident_time" id="timeInput" value="<?php echo htmlspecialchars($incident_time); ?>" required>
                            
                            <div class="input-glass rounded-xl flex items-center px-4 py-3 cursor-pointer h-[50px]" id="timeBtn">
                                <span class="material-symbols-outlined text-outline mr-3">schedule</span>
                                <span id="timeSelectedText" class="w-full text-white text-sm truncate"><?php echo empty($incident_time) ? 'Select Time Range' : htmlspecialchars($incident_time); ?></span>
                                <span class="material-symbols-outlined text-outline transition-transform" id="timeArrow">expand_more</span>
                            </div>

                            <div id="timeMenu" class="absolute left-0 right-0 top-full mt-2 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden">
                                <div class="max-h-64 overflow-y-auto custom-scrollbar py-2">
                                    <?php
                                    $time_options = [
                                        'Morning (06:00 AM - 12:00 PM)' => 'Early classes, breakfast, morning commute',
                                        'Afternoon (12:00 PM - 06:00 PM)' => 'Lunch, afternoon lectures, sports',
                                        'Evening (06:00 PM - 12:00 AM)' => 'Dinner, evening study, campus events',
                                        'Night (12:00 AM - 06:00 AM)' => 'Late night study, dorms',
                                        'Uncertain / Sometime today' => 'Not sure exactly when it was found'
                                    ];
                                    foreach($time_options as $mainTime => $subText) {
                                        $activeClass = ($incident_time == $mainTime) ? 'bg-secondary/20' : '';
                                        $textColor = ($incident_time == $mainTime) ? 'text-secondary' : 'text-white';
                                        echo "<div class='px-4 py-2.5 hover:bg-white/10 cursor-pointer transition-colors custom-option-time text-sm $textColor $activeClass' data-value='$mainTime'>
                                                <strong class='font-bold'>$mainTime</strong>
                                                <div class='text-[11px] text-white/50 leading-tight mt-0.5'>$subText</div>
                                              </div>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-on-surface-variant mb-2 text-sm font-semibold">Detailed Description <span class="text-error">*</span></label>
                            <div class="relative input-glass rounded-xl px-4 py-3">
                                <textarea name="description" class="w-full bg-transparent border-none text-white focus:ring-0 p-0 text-sm min-h-[100px] resize-none" placeholder="Describe the item physically. The AI will use this to find a match." required><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Verification Gatekeeper -->
                <div class="glass-panel p-6 sm:p-8 rounded-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-secondary/10 blur-3xl rounded-full pointer-events-none"></div>
                    <h3 class="text-xl text-white mb-2 flex items-center gap-2 border-b border-white/10 pb-4"><span class="material-symbols-outlined text-secondary">admin_panel_settings</span> Verification Challenge</h3>
                    <p class="text-sm text-white/50 mb-6">Create a security question that only the real owner would know. They must answer this to claim the item.</p>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-on-surface-variant mb-2 text-sm font-semibold">Security Question <span class="text-error">*</span></label>
                            <div class="relative input-glass rounded-xl flex items-center px-4 py-3 border-l-2 border-l-secondary">
                                <span class="material-symbols-outlined text-outline mr-3">help</span>
                                <input name="verification_question" class="w-full bg-transparent border-none text-white focus:ring-0 p-0 text-sm" placeholder="e.g. What picture is on the lock screen?" type="text" value="<?php echo htmlspecialchars($verification_question); ?>" required/>
                            </div>
                        </div>
                        <div>
                            <label class="block text-on-surface-variant mb-2 text-sm font-semibold">Secret Answer <span class="text-error">*</span></label>
                            <div class="relative input-glass rounded-xl flex items-center px-4 py-3 border-l-2 border-l-primary">
                                <span class="material-symbols-outlined text-outline mr-3">key</span>
                                <input name="verification_answer" class="w-full bg-transparent border-none text-white focus:ring-0 p-0 text-sm" placeholder="e.g. My cat" type="text" value="<?php echo htmlspecialchars($verification_answer); ?>" required/>
                            </div>
                            <p class="text-xs text-secondary mt-2"><i class="material-symbols-outlined text-[12px] align-middle">lock</i> Kept strictly hidden. Verified by AI.</p>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Visuals (NEW UPGRADED UI) -->
                <div class="glass-panel p-6 sm:p-8 rounded-2xl">
                    <h3 class="text-xl text-white mb-6 flex items-center gap-2 border-b border-white/10 pb-4"><span class="material-symbols-outlined text-secondary">add_a_photo</span> Visual Fingerprint</h3>
                    <p class="text-sm text-on-surface-variant mb-4">Uploading clear photos greatly improves our AI's ability to find a match.</p>
                    
                    <label for="imageInput" class="block w-full rounded-2xl dropzone p-8 text-center group transition-all relative overflow-hidden cursor-pointer mb-4 min-h-[200px] flex flex-col items-center justify-center">
                        
                        <!-- Default Prompt -->
                        <div id="uploadPrompt" class="flex flex-col items-center justify-center transition-all duration-300 w-full h-full">
                            <div class="w-16 h-16 rounded-full bg-surface-container border border-white/10 flex items-center justify-center mx-auto mb-4 group-hover:bg-secondary/20 group-hover:border-secondary/50 transition-all duration-300">
                                <span class="material-symbols-outlined text-3xl text-secondary">add_photo_alternate</span>
                            </div>
                            <p class="text-white font-bold mb-1 text-lg" id="uploadTitle">Click or drag photos here</p>
                            <p class="text-xs text-on-surface-variant">Upload up to 5 images (PNG, JPG, WEBP)</p>
                        </div>

                        <!-- Inside Box Preview Grid -->
                        <div id="imagePreviewContainer" class="hidden w-full h-full flex-col items-center justify-center">
                            <div id="imagePreviewGrid" class="flex flex-wrap items-center justify-center gap-4 w-full"></div>
                            <div class="mt-6 text-sm font-bold text-secondary bg-secondary/10 px-4 py-2 rounded-full border border-secondary/20 hover:bg-secondary/20 transition-colors">
                                <span class="material-symbols-outlined text-[16px] align-middle mr-1">edit</span> Click to change photos
                            </div>
                        </div>

                        <input type="file" id="imageInput" name="images[]" class="hidden" accept="image/*" multiple onchange="previewInsideBox(event)">
                    </label>
                </div>

                <!-- Section 4: Spatial Intelligence Location Engine -->
                <div class="glass-panel p-6 sm:p-8 rounded-2xl relative overflow-hidden border-t-4 border-secondary">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-secondary/10 blur-3xl rounded-full pointer-events-none"></div>
                    <h3 class="text-xl text-white mb-2 flex items-center gap-2"><span class="material-symbols-outlined text-secondary">satellite_alt</span> Geospatial Location</h3>
                    <p class="text-sm text-white/50 mb-6">Select a building OR tap on the map to drop a pin. The system will automatically calculate the rest.</p>

                    <!-- HIDDEN SPATIAL INPUTS -->
                    <input type="hidden" name="location" id="locationInput" value="<?php echo htmlspecialchars($location); ?>">
                    <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($latitude); ?>">
                    <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($longitude); ?>">
                    <input type="hidden" name="search_radius" id="searchRadiusInput" value="100">
                    <input type="hidden" name="location_method" id="locationMethodInput" value="preset">
                    <input type="hidden" name="campus_location_id" id="campusLocationIdInput" value="">

                    <!-- Option 1: Dropdown -->
                    <div class="relative custom-dropdown mb-6" id="locationDropdownWrapper">
                        <div class="input-glass rounded-xl flex items-center px-4 py-3 cursor-pointer h-[50px] border-l-4 border-l-secondary" id="locationBtn">
                            <span class="material-symbols-outlined text-outline mr-3">domain</span>
                            <span id="locationSelectedText" class="w-full text-white text-sm font-bold truncate">Select Campus Building...</span>
                            <span class="material-symbols-outlined text-outline transition-transform" id="locationArrow">expand_more</span>
                        </div>
                        <div id="locationMenu" class="absolute left-0 right-0 top-[60px] mt-2 glass-panel rounded-xl opacity-0 invisible transition-all duration-200 z-[100] overflow-hidden">
                            <div class="max-h-56 overflow-y-auto custom-scrollbar py-2">
                                <?php foreach ($campus_locations as $loc): ?>
                                    <div class="px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors custom-option-loc text-sm text-white border-b border-white/5" 
                                         data-value="<?php echo htmlspecialchars($loc['location_name']); ?>" 
                                         data-id="<?php echo $loc['id']; ?>"
                                         data-lat="<?php echo $loc['lat']; ?>" 
                                         data-lng="<?php echo $loc['lng']; ?>"
                                         data-rad="<?php echo $loc['default_radius']; ?>"
                                         data-geojson="<?php echo htmlspecialchars($loc['boundary_geojson'] ?? '', ENT_QUOTES); ?>">
                                        <div class="font-bold"><?php echo htmlspecialchars($loc['location_name']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Naming Feedback Banner -->
                    <div id="autoNameBanner" class="hidden bg-success/20 border border-success/30 text-success p-3 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 transition-all">
                        <span class="material-symbols-outlined text-[16px]">radar</span>
                        <span id="autoNameText">Spatial AI locked on location.</span>
                    </div>

                    <!-- Interactive Map -->
                    <div class="relative">
                        <div id="locationPicker"></div>
                        
                        <!-- Floating Radius Selector -->
                        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-[1000] bg-surface/90 backdrop-blur-md border border-white/10 rounded-full p-1.5 flex gap-1 shadow-glass hidden" id="radiusSelector">
                            <button type="button" onclick="changeRadius(50)" id="rad-50" class="btn-outline-glass px-3 py-1 rounded-full text-[10px] font-bold">50m</button>
                            <button type="button" onclick="changeRadius(100)" id="rad-100" class="btn-outline-glass active-rad px-3 py-1 rounded-full text-[10px] font-bold">100m</button>
                            <button type="button" onclick="changeRadius(200)" id="rad-200" class="btn-outline-glass px-3 py-1 rounded-full text-[10px] font-bold">200m</button>
                            <button type="button" onclick="changeRadius(500)" id="rad-500" class="btn-outline-glass px-3 py-1 rounded-full text-[10px] font-bold">500m</button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <button type="submit" class="btn-success-glass text-white font-bold py-4 px-8 rounded-xl flex-1 flex items-center justify-center gap-2">
                        Submit Found Report <span class="material-symbols-outlined text-[20px]">send</span>
                    </button>
                    <!-- SMART CANCEL BUTTON -->
                    <button type="button" onclick="goBack('dashboard.php')" class="btn-outline-glass text-center py-4 px-8 rounded-xl font-bold flex-1">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: AI Assistant Panel -->
        <div class="hidden lg:block space-y-6">
            <div class="glass-panel p-6 rounded-2xl sticky top-24 border-t-4 border-t-secondary">
                <div class="flex items-center gap-3 mb-4"><div class="w-10 h-10 rounded-full bg-secondary/20 flex items-center justify-center"><span class="material-symbols-outlined text-secondary">smart_toy</span></div><h3 class="text-lg font-bold text-white">Finder Guide</h3></div>
                <div class="space-y-4">
                    <div class="flex items-start gap-3"><span class="material-symbols-outlined text-secondary text-[20px]">schedule</span><div><h4 class="text-sm font-bold text-white mb-0.5">Time Correlating</h4><p class="text-xs text-white/60">Adding an estimated time window prevents the AI from falsely matching items.</p></div></div>
                    <div class="flex items-start gap-3"><span class="material-symbols-outlined text-secondary text-[20px]">policy</span><div><h4 class="text-sm font-bold text-white mb-0.5">The Gatekeeper Rule</h4><p class="text-xs text-white/60">Hide ONE defining feature to use as your Verification Question. Do not photograph this detail.</p></div></div>
                    <div class="flex items-start gap-3"><span class="material-symbols-outlined text-secondary text-[20px]">satellite_alt</span><div><h4 class="text-sm font-bold text-white mb-0.5">Spatial Intelligence</h4><p class="text-xs text-white/60">The new map engine calculates exact radii to prevent false matches across campus.</p></div></div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MapLibre & Turf.js -->
<script src="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

<script>
    // --- SMART BACK BUTTON SCRIPT ---
    function goBack(defaultUrl) {
        if (document.referrer && document.referrer.includes(window.location.hostname)) {
            if (document.referrer === window.location.href) {
                window.location.href = defaultUrl;
            } else {
                window.history.back();
            }
        } else {
            window.location.href = defaultUrl;
        }
    }

    // --- IMPORT CAMPUS LOCATIONS FOR TURF.JS ---
    const campusData = <?php echo json_encode($campus_locations); ?>;

    // --- MAPLIBRE INITIALIZATION ---
    const map = new maplibregl.Map({
        container: 'locationPicker',
        style: {
            'version': 8,
            'sources': { 'osm': { 'type': 'raster', 'tiles': ['https://a.tile.openstreetmap.org/{z}/{x}/{y}.png'], 'tileSize': 256 } },
            'layers': [{ 'id': 'osm-layer', 'type': 'raster', 'source': 'osm' }]
        },
        center: [101.725000, 3.048000], zoom: 16
    });

    map.addControl(new maplibregl.NavigationControl(), 'bottom-right');

    let currentMarker = null;
    const radiusSourceId = 'uncertainty-radius-source';
    const polySourceId = 'building-poly-source';

    map.on('load', () => {
        // Circle Radius Source (Green for Found)
        map.addSource(radiusSourceId, { 'type': 'geojson', 'data': { 'type': 'FeatureCollection', 'features': [] } });
        map.addLayer({ 'id': 'radius-fill', 'type': 'fill', 'source': radiusSourceId, 'paint': { 'fill-color': '#44e2cd', 'fill-opacity': 0.15, 'fill-outline-color': '#44e2cd' } });

        // Building Polygon Source (Green for Found)
        map.addSource(polySourceId, { 'type': 'geojson', 'data': { 'type': 'FeatureCollection', 'features': [] } });
        map.addLayer({ 'id': 'poly-fill', 'type': 'fill', 'source': polySourceId, 'paint': { 'fill-color': '#44e2cd', 'fill-opacity': 0.3, 'fill-outline-color': '#03c6b2' } });
    });

    // --- OPTION 1: PRESET LOCATION SELECTION ---
    function selectPresetLocation(option) {
        const name = option.getAttribute('data-value');
        const lat = parseFloat(option.getAttribute('data-lat'));
        const lng = parseFloat(option.getAttribute('data-lng'));
        const rad = parseInt(option.getAttribute('data-rad')) || 100;
        const geojsonStr = option.getAttribute('data-geojson');

        if (isNaN(lat) || isNaN(lng) || lat === 0) {
            Swal.fire({ icon: 'info', title: 'Location Needs Pin', text: 'We don\'t have exact GPS coordinates for this building yet. Please tap on the map to drop a pin manually!', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } });
            return;
        }

        // Update Hidden Inputs
        document.getElementById('locationInput').value = name;
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('searchRadiusInput').value = rad;
        document.getElementById('locationMethodInput').value = 'preset';
        document.getElementById('campusLocationIdInput').value = option.getAttribute('data-id');

        // Update UI
        document.getElementById('locationSelectedText').innerText = name;
        document.getElementById('locationSelectedText').classList.remove('text-outline-variant');
        document.getElementById('radiusSelector').classList.add('hidden');
        
        // Success Banner
        const banner = document.getElementById('autoNameBanner');
        banner.classList.remove('hidden', 'bg-warning/20', 'border-warning/30', 'text-warning');
        banner.classList.add('bg-success/20', 'border-success/30', 'text-success');
        document.getElementById('autoNameText').innerText = `Locked to preset building: ${name}`;

        // Map Actions
        dropGreenPin(lat, lng);

        // Clear Circle, Draw Polygon if exists
        map.getSource(radiusSourceId).setData({ 'type': 'FeatureCollection', 'features': [] });
        
        if (geojsonStr && geojsonStr.trim() !== '') {
            try {
                const poly = JSON.parse(geojsonStr);
                map.getSource(polySourceId).setData(poly);
                const bbox = turf.bbox(poly);
                map.fitBounds([[bbox[0], bbox[1]], [bbox[2], bbox[3]]], { padding: 40, maxZoom: 18 });
            } catch(e) { console.error("Bad GeoJSON"); map.flyTo({ center: [lng, lat], zoom: 18 }); }
        } else {
            map.getSource(polySourceId).setData({ 'type': 'FeatureCollection', 'features': [] });
            map.flyTo({ center: [lng, lat], zoom: 18 });
            drawCircle(lat, lng, rad); // Fallback to circle if no polygon
        }
    }

    // --- OPTION 2: MAP CLICK (AUTO-NAMING & RADIUS) ---
    map.on('click', (e) => {
        const lat = e.lngLat.lat;
        const lng = e.lngLat.lng;
        
        dropGreenPin(lat, lng);
        document.getElementById('radiusSelector').classList.remove('hidden');
        
        if(map.getSource(polySourceId)) map.getSource(polySourceId).setData({ 'type': 'FeatureCollection', 'features': [] });
        drawCircle(lat, lng, 100);

        // 🧠 TURF.JS HAVERSINE AUTO-NAMING
        const point = turf.point([lng, lat]);
        let nearestName = "Unknown Area";
        let minDistance = 99999;
        let matchedId = null;

        campusData.forEach(loc => {
            if(loc.lat && loc.lng && loc.lat != 0) {
                const dist = turf.distance(point, turf.point([parseFloat(loc.lng), parseFloat(loc.lat)]), {units: 'meters'});
                if (dist < minDistance) { minDistance = dist; nearestName = loc.location_name; matchedId = loc.id; }
            }
        });

        let finalName = "";
        let bannerClass = "";
        if (minDistance <= 50) {
            finalName = nearestName; 
            bannerClass = "bg-success/20 border-success/30 text-success";
            document.getElementById('autoNameText').innerHTML = `Auto-detected: <b>${finalName}</b>`;
        } else if (minDistance <= 250) {
            finalName = "Near " + nearestName;
            bannerClass = "bg-warning/20 border-warning/30 text-warning";
            document.getElementById('autoNameText').innerHTML = `Auto-detected: <b>${finalName}</b> (${Math.round(minDistance)}m away)`;
        } else {
            finalName = "Campus Grounds";
            bannerClass = "bg-warning/20 border-warning/30 text-warning";
            document.getElementById('autoNameText').innerHTML = `Custom Pin: Campus Grounds`;
            matchedId = null;
        }

        // Update Inputs
        document.getElementById('locationInput').value = finalName;
        document.getElementById('latitude').value = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);
        document.getElementById('locationMethodInput').value = 'map_pin';
        document.getElementById('searchRadiusInput').value = 100;
        document.getElementById('campusLocationIdInput').value = matchedId || '';

        // Update UI
        document.getElementById('locationSelectedText').innerText = finalName;
        document.getElementById('locationSelectedText').classList.remove('text-outline-variant');
        
        const banner = document.getElementById('autoNameBanner');
        banner.className = `p-3 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 transition-all ${bannerClass}`;
        banner.classList.remove('hidden');

        changeRadiusUI(100);
    });

    // --- RADIUS CHANGER ---
    function changeRadius(meters) {
        document.getElementById('searchRadiusInput').value = meters;
        changeRadiusUI(meters);
        const lat = parseFloat(document.getElementById('latitude').value);
        const lng = parseFloat(document.getElementById('longitude').value);
        if(!isNaN(lat) && !isNaN(lng)) drawCircle(lat, lng, meters);
    }

    function changeRadiusUI(activeRad) {
        document.querySelectorAll('#radiusSelector button').forEach(b => b.classList.remove('active-rad'));
        document.getElementById('rad-' + activeRad).classList.add('active-rad');
    }

    // --- MAP HELPERS ---
    function dropGreenPin(lat, lng) {
        if (currentMarker) currentMarker.remove();
        const el = document.createElement('div');
        el.className = 'w-8 h-8 rounded-full bg-secondary shadow-[0_0_15px_rgba(68,226,205,0.8)] border-2 border-white flex items-center justify-center text-surface';
        el.innerHTML = '<span class="material-symbols-outlined text-[16px]">check_circle</span>';
        currentMarker = new maplibregl.Marker(el).setLngLat([lng, lat]).addTo(map);
    }

    function drawCircle(lat, lng, radiusMeters) {
        if (!map.getSource(radiusSourceId)) return;
        const km = radiusMeters / 1000;
        const options = { steps: 64, units: 'kilometers' };
        const circle = turf.circle([lng, lat], km, options);
        map.getSource(radiusSourceId).setData(circle);
    }

    // --- CUSTOM DROPDOWN BINDINGS ---
    function setupCustomDropdown(btnId, menuId, arrowId, optionClass) {
        const btn = document.getElementById(btnId);
        const menu = document.getElementById(menuId);
        const options = document.querySelectorAll(optionClass);
        if(!btn || !menu) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.glass-panel.absolute').forEach(m => { 
                if(m.id !== menuId && m.id !== 'profileMenu' && m.id !== 'navReportMenu' && m.id !== 'navNotifMenu') { 
                    m.classList.add('opacity-0', 'invisible'); 
                    m.style.transform = 'translateY(-10px)'; 
                } 
            });
            if (menu.classList.contains('opacity-0')) {
                menu.classList.remove('opacity-0', 'invisible');
                menu.style.transform = 'translateY(0)';
                btn.classList.add('active-dropdown');
            } else { closeMenu(); }
        });

        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                if (btnId === 'locationBtn') {
                    selectPresetLocation(option);
                } else {
                    const value = option.getAttribute('data-value');
                    document.getElementById(btnId.replace('Btn', 'Input')).value = value;
                    document.getElementById(btnId.replace('Btn', 'SelectedText')).textContent = option.querySelector('strong') ? option.querySelector('strong').textContent : value;
                    document.getElementById(btnId.replace('Btn', 'SelectedText')).classList.remove('text-outline-variant');
                    
                    options.forEach(opt => { 
                        opt.classList.remove('bg-secondary/20', 'bg-primary/20'); 
                        const t = opt.querySelector('.font-bold'); 
                        if(t) { 
                            t.classList.remove('text-secondary', 'text-primary'); 
                            t.classList.add('text-white'); 
                        } 
                    });
                    
                    const activeColorClass = (btnId === 'locationBtn') ? 'bg-secondary/20' : (document.title.includes('Lost') ? 'bg-error/20' : 'bg-secondary/20');
                    const textActiveColor = (btnId === 'locationBtn') ? 'text-secondary' : (document.title.includes('Lost') ? 'text-error' : 'text-secondary');
                    
                    option.classList.add(activeColorClass);
                    const t = option.querySelector('.font-bold'); 
                    if(t) { 
                        t.classList.remove('text-white'); 
                        t.classList.add(textActiveColor); 
                    }
                }
                closeMenu();
            });
        });

        function closeMenu() { 
            menu.classList.add('opacity-0', 'invisible'); 
            menu.style.transform = 'translateY(-10px)'; 
            btn.classList.remove('active-dropdown'); 
        }
        
        document.addEventListener('click', (e) => { 
            if (!btn.contains(e.target) && !menu.contains(e.target)) closeMenu(); 
        });
    }

    setupCustomDropdown('categoryBtn', 'categoryMenu', 'categoryArrow', '.custom-option-cat');
    setupCustomDropdown('timeBtn', 'timeMenu', 'timeArrow', '.custom-option-time');
    setupCustomDropdown('locationBtn', 'locationMenu', 'locationArrow', '.custom-option-loc');

    // --- SWEETALERT LOADER ---
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        if(this.checkValidity()) { Swal.fire({ title: 'Processing', html: 'Running <b>Spatial AI Match</b>...', allowOutsideClick: false, showConfirmButton: false, background: '#1E293B', color: '#FFFFFF', didOpen: () => { Swal.showLoading(); }}); }
    });

    // --- NEW ELEGANT MULTIPLE IMAGE PREVIEW (INSIDE BOX) ---
    function previewInsideBox(event) {
        const grid = document.getElementById('imagePreviewGrid'); 
        const prompt = document.getElementById('uploadPrompt');
        const container = document.getElementById('imagePreviewContainer');
        
        grid.innerHTML = '';
        
        const files = Array.from(event.target.files).slice(0,5);
        if(files.length > 0) {
            prompt.classList.add('hidden');
            container.classList.remove('hidden');
            container.classList.add('flex');
        } else {
            prompt.classList.remove('hidden');
            container.classList.add('hidden');
            container.classList.remove('flex');
        }

        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = e => {
                grid.innerHTML += `
                <div class="relative w-28 h-28 sm:w-32 sm:h-32 rounded-xl overflow-hidden border-2 border-white/20 shadow-lg group/item animation-fadeIn" style="animation-delay: ${index * 50}ms">
                    <img src="${e.target.result}" class="w-full h-full object-cover group-hover/item:scale-110 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover/item:opacity-100 transition-opacity flex items-end p-2">
                        <span class="text-[10px] text-white/90 truncate w-full font-medium tracking-wide text-center">${file.name}</span>
                    </div>
                </div>`;
            };
            reader.readAsDataURL(file);
        });
    }

    // --- Profile Dropdown Setup (from navbar) ---
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileMenu.classList.toggle('opacity-0');
            profileMenu.classList.toggle('invisible');
            profileMenu.style.transform = profileMenu.classList.contains('opacity-0') ? 'translateY(-10px)' : 'translateY(0)';
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