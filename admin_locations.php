<?php
// admin_locations.php – Geospatial Landmark Management (Aeon Campus Design System)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

// --- DB UPGRADE: Ensure boundary_geojson column exists ---
$check_col = $conn->query("SHOW COLUMNS FROM campus_locations LIKE 'boundary_geojson'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE campus_locations ADD COLUMN boundary_geojson LONGTEXT NULL AFTER default_radius");
}

$message = '';
$error = '';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $name = trim($_POST['location_name']);
            $desc = trim($_POST['description']);
            $lat = floatval($_POST['lat']);
            $lng = floatval($_POST['lng']);
            $geojson = trim($_POST['boundary_geojson']); // Capture the drawn shape!

            if (empty($name) || $lat == 0 || $lng == 0 || empty($geojson)) {
                $error = "Name, Latitude, Longitude, and a Drawn Map Boundary are required.";
            } else {
                if ($_POST['action'] === 'add') {
                    $stmt = $conn->prepare("INSERT INTO campus_locations (location_name, description, lat, lng, default_radius, boundary_geojson) VALUES (?, ?, ?, ?, 100, ?)");
                    $stmt->bind_param("ssdds", $name, $desc, $lat, $lng, $geojson);
                    $msg_text = "Location and boundary added successfully!";
                } else {
                    $id = intval($_POST['loc_id']);
                    $stmt = $conn->prepare("UPDATE campus_locations SET location_name=?, description=?, lat=?, lng=?, boundary_geojson=? WHERE id=?");
                    $stmt->bind_param("ssddsi", $name, $desc, $lat, $lng, $geojson, $id);
                    $msg_text = "Location updated successfully!";
                }
                if ($stmt->execute()) {
                    $message = $msg_text;
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['loc_id']);
            $conn->query("DELETE FROM campus_locations WHERE id = $id");
            $message = "Location deleted successfully.";
        }
    }
}

// Fetch existing locations
$locations = $conn->query("SELECT * FROM campus_locations ORDER BY location_name ASC");

// Get unread count for navbar badge
$user_id = $_SESSION['user_id'];
$unread_query = "SELECT COUNT(*) as count FROM chat_messages cm JOIN item_reports ir ON cm.item_report_id = ir.id WHERE cm.sender_id != ? AND cm.is_read = 0 AND (ir.user_id = ? OR ir.id IN (SELECT item_report_id FROM chat_messages WHERE sender_id = ?))";
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
    <title>CampusFind - Geo-Admin</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- MapLibre GL CSS -->
    <link href="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css" rel="stylesheet" />
    <!-- Mapbox Draw CSS (Compatible with MapLibre) -->
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.css" type="text/css" />

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface": "#10131a", "on-background": "#e1e2ec", "primary": "#adc6ff", 
                        "secondary": "#44e2cd", "tertiary": "#ffb786", "error": "#ffb4ab", "warning": "#F59E0B", "success": "#10B981",
                        "surface-container-high": "#272a31", "on-surface-variant": "#c2c6d6"
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; font-family: 'Inter', sans-serif; }
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        
        /* 🔥 FIX: Input Glass Styling & Autofill */
        .input-glass { background-color: rgba(0, 0, 0, 0.3) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; color: #ffffff !important; transition: all 0.3s ease; }
        .input-glass:focus { border-color: #adc6ff !important; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2) !important; outline: none !important; }
        .input-glass::placeholder { color: rgba(255, 255, 255, 0.4) !important; }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 50px rgba(0, 0, 0, 0.8) inset !important;
            -webkit-text-fill-color: #e1e2ec !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); transition: all 0.3s ease; }
        .btn-primary:hover { box-shadow: 0 0 20px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; }
        
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }
        
        #adminMap { border-radius: 12px; height: 450px; width: 100%; border: 1px solid rgba(255,255,255,0.1); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
    </style>
</head>
<body class="antialiased selection:bg-primary selection:text-surface relative min-h-screen pb-20 flex flex-col">

<!-- Global Shader Background -->
<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60">
    <div class="absolute inset-0 w-full h-full"><canvas id="shader-canvas-ANIMATION_8" style="display:block;width:100%;height:100%"></canvas></div>
</div>

<?php include 'navbar.php'; ?>

<main class="w-full max-w-7xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-4xl">draw</span> Draw Campus Zones
            </h1>
            <p class="text-on-surface-variant text-sm mt-1">Use the map tool to draw exact building boundaries (Polygons) for perfect spatial accuracy.</p>
        </div>
        <a href="admin_dashboard.php" class="btn-outline-glass px-4 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl bg-success/20 border border-success/50 text-success text-sm font-medium flex items-center gap-2">
            <span class="material-symbols-outlined">check_circle</span> <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-error/20 border border-error/50 text-error text-sm font-medium flex items-center gap-2">
            <span class="material-symbols-outlined">error</span> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left: Map & Form -->
        <div class="lg:col-span-5 space-y-6">
            <div class="glass-panel p-6 rounded-2xl">
                <h3 class="text-lg font-bold text-white mb-6 flex items-center gap-2" id="formTitle">
                    <span class="material-symbols-outlined text-secondary">add_circle</span> Add New Zone
                </h3>
                
                <form method="POST" id="locationForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="loc_id" id="locId" value="">
                    <!-- HIDDEN FIELD FOR DRAWN SHAPE DATA -->
                    <input type="hidden" name="boundary_geojson" id="boundaryGeojson" value="">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-on-surface-variant font-bold mb-1.5">Building/Zone Name *</label>
                            <input type="text" name="location_name" id="locName" class="w-full input-glass bg-transparent rounded-lg px-4 py-3 text-sm focus:ring-0" placeholder="e.g. Fakulti Kejuruteraan" required>
                        </div>
                        
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-on-surface-variant font-bold mb-1.5">Description</label>
                            <textarea name="description" id="locDesc" class="w-full input-glass bg-transparent rounded-lg px-4 py-3 text-sm resize-none focus:ring-0" rows="2" placeholder="e.g. Main library building near the lake"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4 opacity-70">
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-on-surface-variant font-bold mb-1.5">Center Lat</label>
                                <input type="number" step="any" name="lat" id="locLat" class="w-full input-glass bg-black/40 rounded-lg px-3 py-2 text-xs font-mono text-primary focus:ring-0 cursor-not-allowed" required readonly>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-widest text-on-surface-variant font-bold mb-1.5">Center Lng</label>
                                <input type="number" step="any" name="lng" id="locLng" class="w-full input-glass bg-black/40 rounded-lg px-3 py-2 text-xs font-mono text-primary focus:ring-0 cursor-not-allowed" required readonly>
                            </div>
                        </div>

                        <div class="text-sm text-white bg-primary/10 border border-primary/30 p-4 rounded-xl mt-4 flex flex-col gap-2">
                            <span class="font-bold flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px]">polyline</span> How to draw a zone:</span>
                            <ol class="list-decimal list-inside text-xs text-white/70 space-y-1.5 ml-1">
                                <li>Click the <b>Polygon Icon (⬟)</b> in the top right of the map.</li>
                                <li>Click around the corners of the building to outline it.</li>
                                <li>Click your starting point again to finish the shape.</li>
                            </ol>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="btn-primary text-white font-bold py-3 px-4 rounded-xl flex-1 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span> Save Zone
                        </button>
                        <button type="button" onclick="resetForm()" class="btn-outline-glass py-3 px-6 rounded-xl font-bold">Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: MapLibre Map (BRIGHT OPENSTREETMAP) & List -->
        <div class="lg:col-span-7 space-y-6">
            <!-- The Map -->
            <div class="glass-panel p-2 rounded-2xl relative shadow-lg border-2 border-white/10">
                <div id="adminMap"></div>
            </div>

            <!-- Existing Zones List -->
            <div class="glass-panel p-6 rounded-2xl flex flex-col max-h-[350px]">
                <h3 class="text-sm font-bold text-white/70 uppercase tracking-widest mb-4 border-b border-white/10 pb-2">Registered Zones</h3>
                <div class="overflow-y-auto flex-1 custom-scrollbar pr-2 space-y-2">
                    <?php while ($row = $locations->fetch_assoc()): ?>
                        <div class="bg-white/5 border border-white/10 p-3 rounded-xl flex justify-between items-center group hover:border-primary/50 transition-colors">
                            <div class="flex-1 cursor-pointer" onclick="flyToLoc(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                <div class="text-white font-bold text-sm flex items-center gap-2">
                                    <span class="material-symbols-outlined text-secondary text-[16px]"><?php echo empty($row['boundary_geojson']) ? 'location_on' : 'polyline'; ?></span> 
                                    <?php echo htmlspecialchars($row['location_name']); ?>
                                    <?php if(!empty($row['boundary_geojson'])): ?>
                                        <span class="text-[9px] bg-primary/20 text-primary px-2 rounded border border-primary/30">Drawn</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                <button onclick="editLoc(event, <?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-primary/20 text-primary border border-primary/30 px-2.5 py-1 rounded text-xs font-bold hover:bg-primary/40">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this landmark?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="loc_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="bg-error/20 text-error border border-error/30 px-2.5 py-1 rounded text-xs font-bold hover:bg-error/40">Del</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- MapLibre GL JS -->
<script src="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js"></script>
<!-- Mapbox Draw JS (For Drawing Polygons) -->
<script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.js"></script>
<!-- Turf.js (For calculating the center of a drawn polygon) -->
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

<script>
    // Initialize MapLibre with Bright OpenStreetMap
    const map = new maplibregl.Map({
        container: 'adminMap',
        style: {
            'version': 8,
            'sources': {
                'osm': { 'type': 'raster', 'tiles': ['https://a.tile.openstreetmap.org/{z}/{x}/{y}.png'], 'tileSize': 256, 'attribution': '© OpenStreetMap' }
            },
            'layers': [{ 'id': 'osm-layer', 'type': 'raster', 'source': 'osm' }]
        },
        center: [101.725000, 3.048000], // Center of UPNM
        zoom: 16
    });

    map.addControl(new maplibregl.NavigationControl(), 'bottom-right');

    // Initialize the Mapbox Draw Tool
    const draw = new MapboxDraw({
        displayControlsDefault: false,
        controls: {
            polygon: true, // Only allow drawing polygons
            trash: true    // Allow deleting shapes
        },
        defaultMode: 'draw_polygon'
    });

    map.addControl(draw, 'top-right');

    map.on('draw.create', updateDrawnShape);
    map.on('draw.update', updateDrawnShape);
    map.on('draw.delete', updateDrawnShape);

    function updateDrawnShape(e) {
        const data = draw.getAll();
        const geojsonInput = document.getElementById('boundaryGeojson');
        
        if (data.features.length > 0) {
            if (data.features.length > 1) {
                draw.delete(data.features[0].id);
                data.features.shift();
            }
            const currentFeature = data.features[0];
            geojsonInput.value = JSON.stringify(currentFeature); // Save the shape

            // Calculate center
            const center = turf.centroid(currentFeature);
            const lng = center.geometry.coordinates[0].toFixed(6);
            const lat = center.geometry.coordinates[1].toFixed(6);

            document.getElementById('locLat').value = lat;
            document.getElementById('locLng').value = lng;
        } else {
            geojsonInput.value = '';
            document.getElementById('locLat').value = '';
            document.getElementById('locLng').value = '';
        }
    }

    function editLoc(e, data) {
        e.stopPropagation();
        document.getElementById('formTitle').innerHTML = '<span class="material-symbols-outlined text-warning">edit</span> Edit Zone';
        document.getElementById('formAction').value = "edit";
        document.getElementById('locId').value = data.id;
        document.getElementById('locName').value = data.location_name;
        document.getElementById('locDesc').value = data.description || '';
        document.getElementById('locLat').value = data.lat;
        document.getElementById('locLng').value = data.lng;
        
        // Load existing shape into the map
        draw.deleteAll();
        if (data.boundary_geojson && data.boundary_geojson.trim() !== '') {
            try {
                const geojsonObj = JSON.parse(data.boundary_geojson);
                draw.add(geojsonObj);
                document.getElementById('boundaryGeojson').value = data.boundary_geojson;
                
                const bbox = turf.bbox(geojsonObj);
                map.fitBounds([[bbox[0], bbox[1]], [bbox[2], bbox[3]]], { padding: 50, maxZoom: 18 });
            } catch (err) {
                fallbackFlyTo(data.lat, data.lng);
            }
        } else {
            document.getElementById('boundaryGeojson').value = '';
            fallbackFlyTo(data.lat, data.lng);
        }
    }

    function flyToLoc(data) {
        draw.deleteAll();
        if (data.boundary_geojson && data.boundary_geojson.trim() !== '') {
            try {
                const geojsonObj = JSON.parse(data.boundary_geojson);
                draw.add(geojsonObj);
                const bbox = turf.bbox(geojsonObj);
                map.fitBounds([[bbox[0], bbox[1]], [bbox[2], bbox[3]]], { padding: 50, maxZoom: 18 });
            } catch (err) {
                fallbackFlyTo(data.lat, data.lng);
            }
        } else {
            fallbackFlyTo(data.lat, data.lng);
        }
    }

    function fallbackFlyTo(lat, lng) {
        map.flyTo({ center: [lng, lat], zoom: 18, essential: true });
    }

    function resetForm() {
        document.getElementById('locationForm').reset();
        document.getElementById('formTitle').innerHTML = '<span class="material-symbols-outlined text-secondary">add_circle</span> Add New Zone';
        document.getElementById('formAction').value = "add";
        document.getElementById('locId').value = "";
        document.getElementById('boundaryGeojson').value = "";
        draw.deleteAll();
        map.flyTo({ center: [101.725000, 3.048000], zoom: 16 });
    }

    // Profile Dropdown
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('opacity-0'); profileMenu.classList.toggle('invisible'); profileMenu.style.transform = profileMenu.classList.contains('opacity-0') ? 'translateY(-10px)' : 'translateY(0)'; });
        document.addEventListener('click', (e) => { if (!profileMenu.contains(e.target) && !profileBtn.contains(e.target)) { profileMenu.classList.add('opacity-0', 'invisible'); profileMenu.style.transform = 'translateY(-10px)'; } });
    }

    // Shader logic
    (function() {
      const canvas = document.getElementById('shader-canvas-ANIMATION_8');
      function syncSize() {
        const w = canvas.clientWidth || 1280; const h = canvas.clientHeight || 720;
        if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
      }
      if (typeof ResizeObserver !== 'undefined') new ResizeObserver(syncSize).observe(canvas);
      syncSize();
      const gl = canvas.getContext('webgl'); if (!gl) return;
      const vs = `attribute vec2 a_position; varying vec2 v_texCoord; void main() { v_texCoord = a_position * 0.5 + 0.5; gl_Position = vec4(a_position, 0.0, 1.0); }`;
      const fs = `precision highp float; uniform float u_time; varying vec2 v_texCoord;
      vec3 permute(vec3 x) { return mod(((x*34.0)+1.0)*x, 289.0); }
      float snoise(vec2 v){ const vec4 C = vec4(0.211324865405187, 0.366025403784439, -0.577350269189626, 0.024390243902439); vec2 i=floor(v+dot(v, C.yy)); vec2 x0=v-i+dot(i, C.xx); vec2 i1=(x0.x>x0.y)?vec2(1.,0.):vec2(0.,1.); vec4 x12=x0.xyxy+C.xxzz; x12.xy-=i1; i=mod(i, 289.0); vec3 p=permute(permute(i.y+vec3(0.,i1.y,1.))+i.x+vec3(0.,i1.x,1.)); vec3 m=max(0.5-vec3(dot(x0,x0),dot(x12.xy,x12.xy),dot(x12.zw,x12.zw)),0.0); m=m*m; m=m*m; vec3 x=2.0*fract(p * C.www)-1.0; vec3 h=abs(x)-0.5; vec3 ox=floor(x+0.5); vec3 a0=x-ox; m*=1.79284291400159-0.85373472095314*(a0*a0+h*h); vec3 g; g.x=a0.x*x0.x+h.x*x0.y; g.yz=a0.yz*x12.xz+h.yz*x12.yw; return 130.0*dot(m, g); }
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
</body>
</html>