<?php
// map.php – STUDENT DISCOVERY MAP (Boxed Glass Layout)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

// 🔥 FAIL-SAFE DATABASE FIX
$conn->query("ALTER TABLE item_reports ADD COLUMN IF NOT EXISTS location_method ENUM('preset', 'map_pin') DEFAULT 'map_pin'");
$conn->query("ALTER TABLE item_reports ADD COLUMN IF NOT EXISTS campus_location_id INT NULL");

$user_id = $_SESSION['user_id'];

// Fetch all active public reports
$sql = "SELECT 
            ir.id, 
            ir.item_name, 
            ir.report_type, 
            ir.category,
            ir.description,
            ir.status, 
            ir.location as location_name,
            ir.latitude,
            ir.longitude,
            ir.search_radius,
            ir.location_method,
            ir.created_at,
            cl.lat as safe_lat,
            cl.lng as safe_lng,
            u.unique_id
        FROM item_reports ir
        JOIN users u ON ir.user_id = u.id
        LEFT JOIN campus_locations cl ON ir.campus_location_id = cl.id
        WHERE ir.latitude IS NOT NULL 
          AND ir.longitude IS NOT NULL
          AND ir.status != 'returned'
          AND ir.category != 'Campus Tag'
        ORDER BY ir.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    die("<div style='color:white; background:red; padding:20px; font-family:sans-serif;'><strong>Database Error:</strong> " . $conn->error . "</div>");
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

// Get unread count for navbar badge
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
    <title>CampusFind - Discover Map</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Pure Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface": "#10131a", "primary": "#adc6ff", "secondary": "#44e2cd", "tertiary": "#ffb786", 
                        "error": "#ffb4ab", "success": "#10B981", "warning": "#F59E0B"
                    },
                    "fontFamily": { "headline-md": ["Inter"], "body-md": ["Inter"] }
                }
            }
        }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow: hidden; font-family: 'Inter', sans-serif; }
        
        /* Premium Glass Panel */
        .glass-panel { background: rgba(18, 24, 36, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        
        /* Clean Inputs */
        .input-glass { background: rgba(0, 0, 0, 0.4) !important; border: 1px solid rgba(255,255,255,0.1) !important; color: white !important; transition: all 0.3s ease; }
        .input-glass:focus { border-color: #adc6ff !important; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2) !important; outline: none !important; }
        .input-glass::placeholder { color: rgba(255,255,255,0.4) !important; }

        /* Filter Buttons */
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; cursor: pointer; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        .active-type-all { background: rgba(99, 102, 241, 0.2); border-color: #6366F1; color: #A5B4FC; }
        .active-type-lost { background: rgba(239, 68, 68, 0.2); border-color: #EF4444; color: #FCA5A5; }
        .active-type-found { background: rgba(16, 185, 129, 0.2); border-color: #10B981; color: #6EE7B7; }

        /* Leaflet Map Styling */
        .leaflet-container { background: #e5e7eb; outline: none; font-family: 'Inter', sans-serif; border-radius: 12px; }
        .leaflet-control-zoom { border: 1px solid rgba(255,255,255,0.15) !important; border-radius: 8px !important; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important; margin-right: 16px !important; margin-bottom: 24px !important;}
        .leaflet-control-zoom a { background: rgba(15, 23, 42, 0.9) !important; color: white !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; backdrop-filter: blur(8px); width: 40px !important; height: 40px !important; line-height: 40px !important; }
        .leaflet-control-zoom a:hover { background: rgba(99, 102, 241, 0.9) !important; }
        
        .leaflet-popup-content-wrapper { background: rgba(15, 23, 42, 0.95) !important; color: #e1e2ec !important; border: 1px solid rgba(255,255,255,0.15) !important; backdrop-filter: blur(16px) !important; border-radius: 16px !important; box-shadow: 0 10px 40px rgba(0,0,0,0.6) !important; padding: 0 !important; overflow: hidden; }
        .leaflet-popup-content { margin: 0 !important; width: 100% !important; }
        .leaflet-popup-tip { border-top-color: rgba(15, 23, 42, 0.95) !important; }

        .marker-cluster { background-color: rgba(99, 102, 241, 0.4) !important; }
        .marker-cluster div { background-color: rgba(79, 70, 229, 0.9) !important; color: white !important; font-weight: 700; border: 2px solid rgba(255,255,255,0.5); }

        /* Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

        /* Z-Index Management */
        #navbar-wrapper { position: relative; z-index: 1000; }
        .map-overlay-bottom { position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); z-index: 1000; pointer-events: none; }
        .map-overlay-bottom > * { pointer-events: auto; }
        
        /* Pulse Animation for User Location */
        .pulse-ring { border-radius: 50%; animation: pulsate 2s ease-out infinite; }
        @keyframes pulsate { 0% { transform: scale(0.1); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-white relative min-h-screen flex flex-col overflow-hidden">

<!-- Background Shader Canvas -->
<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60 bg-[#10131a]">
    <canvas id="shader-canvas-ANIMATION_8" style="display:block;width:100%;height:100%"></canvas>
    <script>
        (function() {
          const canvas = document.getElementById('shader-canvas-ANIMATION_8');
          function syncSize() { const w = canvas.clientWidth || 1280; const h = canvas.clientHeight || 720; if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; } }
          if (typeof ResizeObserver !== 'undefined') { new ResizeObserver(syncSize).observe(canvas); } syncSize();
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
</div>

<div id="navbar-wrapper">
    <?php include 'navbar.php'; ?>
</div>

<main class="w-full max-w-[1400px] mx-auto px-4 sm:px-6 pt-24 sm:pt-28 pb-6 flex-grow flex flex-col h-screen">
    
    <!-- BOXED GLASS PANEL -->
    <div class="glass-panel p-4 sm:p-6 rounded-2xl relative flex flex-col h-full shadow-[0_0_40px_rgba(0,0,0,0.5)] fade-in-up visible">
        
        <!-- Header & Filters (Z-Index Fixed) -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-4 pb-4 border-b border-white/10 shrink-0 z-50 relative">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-3xl">map</span> Campus Map
                </h1>
            </div>
            
            <div class="flex flex-col sm:flex-row w-full lg:w-auto gap-3">
                <!-- Search Input -->
                <div class="relative w-full sm:w-64">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-white/50 text-[18px]">search</span>
                    <input type="text" id="searchInput" class="w-full input-glass rounded-xl pl-9 pr-4 py-2.5 text-sm" placeholder="Search items...">
                </div>

                <!-- Type Toggles -->
                <div class="flex items-center gap-1 bg-surface-container-high/50 p-1.5 rounded-xl border border-white/5 shrink-0">
                    <button onclick="setFilter('type', 'all')" id="btn-type-all" class="btn-outline-glass active-type-all px-4 py-1.5 rounded-lg text-xs font-bold flex-1 sm:flex-none">All</button>
                    <button onclick="setFilter('type', 'lost')" id="btn-type-lost" class="btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold flex-1 sm:flex-none flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full bg-error"></span> Lost</button>
                    <button onclick="setFilter('type', 'found')" id="btn-type-found" class="btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold flex-1 sm:flex-none flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full bg-success"></span> Found</button>
                </div>

                <!-- Category Dropdown -->
                <div class="relative w-full sm:w-48 group shrink-0">
                    <div class="input-glass rounded-xl flex items-center justify-between px-3 py-2 cursor-pointer text-sm font-bold border border-white/20 h-full">
                        <span id="catSelectedText" class="truncate">All Categories</span>
                        <span class="material-symbols-outlined text-[18px]">expand_more</span>
                    </div>
                    <div class="absolute right-0 top-full mt-2 w-full sm:w-56 glass-panel rounded-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-[1000] overflow-hidden shadow-2xl border border-white/20">
                        <div class="max-h-60 overflow-y-auto custom-scrollbar py-2">
                            <div class="px-4 py-2 hover:bg-white/10 cursor-pointer text-sm font-bold text-primary border-b border-white/5" onclick="setFilter('category', 'all', 'All Categories')">All Categories</div>
                            <?php 
                            $broadCats = ['Electronics & Gadgets', 'Cards, IDs & Documents', 'Bags & Apparel', 'Personal Belongings', 'Sports & Equipment', 'Other'];
                            foreach ($broadCats as $cat): ?>
                                <div class="px-4 py-2.5 hover:bg-white/10 cursor-pointer text-sm font-bold text-white border-b border-white/5" onclick="setFilter('category', '<?php echo $cat; ?>', '<?php echo $cat; ?>')">
                                    <?php echo $cat; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- The Map Container -->
        <div id="map" class="flex-1 w-full rounded-xl border border-white/10 shadow-inner z-0 relative overflow-hidden">
            
            <!-- Floating Action Buttons (Inside Map Box) -->
            <div class="absolute right-4 top-4 z-[1000] flex flex-col gap-3">
                <button onclick="centerMap()" class="w-12 h-12 rounded-full bg-surface-container-high/90 hover:bg-primary text-white shadow-lg flex items-center justify-center transition-all hover:scale-110 border border-white/20 backdrop-blur-sm" title="Center Campus">
                    <span class="material-symbols-outlined text-[24px]">home</span>
                </button>
                <button onclick="locateUser()" class="w-12 h-12 rounded-full bg-surface-container-high/90 hover:bg-secondary text-white shadow-lg flex items-center justify-center transition-all hover:scale-110 border border-white/20 backdrop-blur-sm" title="Near Me">
                    <span class="material-symbols-outlined text-[24px]">my_location</span>
                </button>
            </div>

            <!-- Search This Area Button -->
            <div class="map-overlay-bottom">
                <button id="searchAreaBtn" onclick="searchThisArea()" class="bg-primary/90 backdrop-blur border border-white/20 text-white px-5 py-2.5 rounded-full text-sm font-bold shadow-[0_0_20px_rgba(77,142,255,0.4)] transition-all hover:scale-105 flex items-center gap-2 hidden transform translate-y-4 opacity-0">
                    <span class="material-symbols-outlined text-[18px]">travel_explore</span> Search This Area
                </button>
            </div>

        </div>

        <!-- Footer Legend -->
        <div class="mt-4 pt-4 border-t border-white/10 flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0">
            <div class="flex items-center gap-4 text-xs font-bold text-white/80 w-full sm:w-auto justify-center sm:justify-start">
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-error border border-error/50"></span> Lost Item</div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-success border border-success/50"></span> Found Item</div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full border border-primary bg-primary/20"></span> Search Area</div>
            </div>
            
            <div class="flex items-center gap-2 text-sm text-on-surface-variant font-medium">
                <span class="material-symbols-outlined text-[18px]">pin_drop</span> Displaying <span id="visibleCount" class="text-white font-bold mx-1">0</span> items
            </div>
        </div>
        
    </div>
</main>

<!-- Leaflet.js & Turf.js -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

<script>
    // --- DROPDOWN LOGIC ---
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('opacity-0'); profileMenu.classList.toggle('invisible'); profileMenu.style.transform = profileMenu.classList.contains('opacity-0') ? 'translateY(-10px)' : 'translateY(0)'; });
        document.addEventListener('click', (e) => { if (!profileMenu.contains(e.target) && !profileBtn.contains(e.target)) { profileMenu.classList.add('opacity-0', 'invisible'); profileMenu.style.transform = 'translateY(-10px)'; } });
    }

    // --- MAP INITIALIZATION ---
    let rawItems = <?php echo json_encode($items); ?>;
    let mapDataCache = []; 
    let filters = { type: 'all', category: 'all', keyword: '' };
    
    // Standard Bright Map (OSM)
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' });
    const map = L.map('map', { zoomControl: false, layers: [osmLayer] }).setView([3.048000, 101.725000], 17);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Responsive Sizing Guard
    new ResizeObserver(() => { map.invalidateSize(); }).observe(document.getElementById('map'));

    // Layer Groups
    const clusterGroup = L.markerClusterGroup({ maxClusterRadius: 40, spiderfyOnMaxZoom: true });
    const shapesGroup = L.layerGroup().addTo(map);

    let userMarker = null;

    // --- RENDER ENGINE ---
    function renderMap(dataArray = rawItems) {
        clusterGroup.clearLayers();
        shapesGroup.clearLayers();
        let visibleCount = 0;

        dataArray.forEach(item => {
            if (!item.latitude || !item.longitude) return;

            // Apply Filters
            if (filters.type !== 'all' && item.report_type !== filters.type) return;
            if (filters.category !== 'all' && item.category !== filters.category) return;
            if (filters.keyword !== '') {
                const searchStr = `${item.item_name} ${item.description} ${item.location_name} ${item.category}`.toLowerCase();
                if (!searchStr.includes(filters.keyword)) return;
            }

            visibleCount++;

            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);
            const isLost = item.report_type === 'lost';
            
            const colorHex = isLost ? '#EF4444' : '#10B981';
            const badgeClass = isLost ? 'bg-error/20 text-error border-error/30' : 'bg-success/20 text-success border-success/30';
            
            // Draw subtle radius
            const rad = parseInt(item.search_radius) || 100;
            L.circle([lat, lng], { radius: rad, color: colorHex, fillColor: colorHex, fillOpacity: 0.08, weight: 1.5, dashArray: '4,4' }).addTo(shapesGroup);

            // Create Marker
            const icon = L.divIcon({
                className: 'custom-marker',
                html: `<div class="w-8 h-8 rounded-full flex items-center justify-center text-[14px] font-bold border-2 border-white shadow-lg transition-transform hover:scale-110" style="background-color: ${colorHex}; color: white; box-shadow: 0 0 15px ${colorHex}80;">${isLost ? 'L' : 'F'}</div>`,
                iconSize: [32, 32], iconAnchor: [16, 32]
            });

            const timeAgo = timeSince(new Date(item.created_at.replace(/-/g, '/')));

            let navLat = lat; let navLng = lng;
            if (item.location_method === 'map_pin') {
                if (item.safe_lat && item.safe_lat != 0) { navLat = item.safe_lat; navLng = item.safe_lng; } 
                else { navLat = parseFloat(navLat).toFixed(3); navLng = parseFloat(navLng).toFixed(3); }
            }
            const navUrl = `https://www.google.com/maps/dir/?api=1&destination=${navLat},${navLng}`;

            const marker = L.marker([lat, lng], { icon: icon });
            marker.bindPopup(`
                <div class="p-4 w-60 font-body-md text-white flex flex-col gap-2">
                    <h6 class="font-bold text-base text-white truncate mb-1">${item.item_name}</h6>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2 py-0.5 rounded border text-[9px] font-bold uppercase tracking-wider ${badgeClass}">${item.report_type}</span>
                        <span class="text-[10px] font-bold text-white/50 uppercase tracking-widest">${item.category}</span>
                    </div>
                    <div class="text-xs text-on-surface-variant space-y-1.5 border-t border-white/10 pt-3">
                        <div class="flex items-start gap-1.5"><span class="material-symbols-outlined text-[14px] text-tertiary">location_on</span> <span class="leading-tight">${item.location_name || 'Mapped Area'}</span></div>
                        <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px] text-primary">radar</span> Search Radius: ${rad}m</div>
                        <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px] text-secondary">schedule</span> ${timeAgo}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-4">
                        <a href="item_detail.php?id=${item.id}" class="bg-white/10 hover:bg-white/20 border border-white/10 text-white py-2.5 rounded-lg text-[11px] font-bold text-center transition-all">Details</a>
                        <a href="${navUrl}" target="_blank" class="bg-gradient-to-r from-primary to-inverse-primary text-white py-2.5 rounded-lg text-[11px] font-bold text-center hover:shadow-glow transition-all">Navigate</a>
                    </div>
                </div>
            `);

            clusterGroup.addLayer(marker);
        });

        map.addLayer(clusterGroup);
        document.getElementById('visibleCount').innerText = visibleCount;
    }

    // --- FILTERING ---
    function setFilter(type, value, displayTxt = '') {
        filters[type] = value;
        
        if (type === 'type') {
            document.querySelectorAll('.btn-outline-glass').forEach(b => b.className = 'btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold flex-1 sm:flex-none flex items-center justify-center gap-1');
            const btn = document.getElementById(`btn-type-${value}`);
            if (value === 'all') btn.classList.add('active-type-all');
            if (value === 'lost') btn.classList.add('active-type-lost');
            if (value === 'found') btn.classList.add('active-type-found');
        }
        
        if (type === 'category') {
            document.getElementById('catSelectedText').innerText = displayTxt;
        }
        
        applyFilters();
    }

    document.getElementById('searchInput').addEventListener('input', (e) => {
        filters.keyword = e.target.value.toLowerCase();
        applyFilters();
    });

    function applyFilters() {
        let filtered = rawItems.filter(item => {
            if (filters.type !== 'all' && item.report_type !== filters.type) return false;
            if (filters.category !== 'all' && item.category !== filters.category) return false;
            if (filters.keyword !== '') {
                const searchStr = `${item.item_name} ${item.description} ${item.location_name}`.toLowerCase();
                if (!searchStr.includes(filters.keyword)) return false;
            }
            return true;
        });
        mapDataCache = filtered;
        renderMap(filtered); 
    }

    // --- SEARCH THIS AREA LOGIC ---
    const searchAreaBtn = document.getElementById('searchAreaBtn');
    
    map.on('dragend', () => {
        searchAreaBtn.classList.remove('hidden');
        setTimeout(() => { searchAreaBtn.classList.remove('translate-y-4', 'opacity-0'); }, 10);
    });

    function searchThisArea() {
        searchAreaBtn.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => { searchAreaBtn.classList.add('hidden'); }, 300);

        const bounds = map.getBounds();
        const filteredByBounds = mapDataCache.filter(item => {
            const lat = parseFloat(item.latitude); const lng = parseFloat(item.longitude);
            return lat >= bounds.getSouth() && lat <= bounds.getNorth() && lng >= bounds.getWest() && lng <= bounds.getEast();
        });

        renderMap(filteredByBounds);
    }

    // --- MAP CONTROLS ---
    function centerMap() { 
        map.flyTo([3.048000, 101.725000], 17); 
    }

    function locateUser() {
        if (!navigator.geolocation) { Swal.fire({ icon: 'error', title: 'Error', text: 'Geolocation not supported.', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } }); return; }
        
        Swal.fire({ title: 'Locating...', allowOutsideClick: false, showConfirmButton: false, background: '#1E293B', color: '#FFFFFF', didOpen: () => { Swal.showLoading(); } });

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                Swal.close();
                const lat = pos.coords.latitude; const lng = pos.coords.longitude;
                map.flyTo([lat, lng], 17);

                if (userMarker) shapesGroup.removeLayer(userMarker);

                const el = document.createElement('div');
                el.innerHTML = '<div class="w-4 h-4 bg-[#44e2cd] rounded-full border-2 border-white shadow-[0_0_15px_#44e2cd]"><div class="absolute inset-0 bg-[#44e2cd] rounded-full animate-ping opacity-75"></div></div>';
                const mk = L.marker([lat, lng], { icon: L.divIcon({className: '', html: el.innerHTML}) });
                const r = L.circle([lat, lng], { radius: 500, color: '#44e2cd', fillOpacity: 0.1, weight: 2, dashArray: '5,5' });
                
                userMarker = L.layerGroup([mk, r]).addTo(shapesGroup);

                // Auto filter by 500m
                const userPt = turf.point([lng, lat]);
                const nearbyItems = mapDataCache.filter(item => {
                    const dist = turf.distance(userPt, turf.point([parseFloat(item.longitude), parseFloat(item.latitude)]), { units: 'kilometers' });
                    return dist <= 0.5;
                });
                renderMap(nearbyItems);
                Swal.fire({ icon: 'success', title: 'Location Found', text: `Showing ${nearbyItems.length} items near you.`, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, background: '#1E293B', color: '#FFFFFF' });
            },
            (err) => { Swal.fire({ icon: 'error', title: 'Denied', text: 'Please enable location access.', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } }); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    function timeSince(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 86400; if (interval >= 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600; if (interval >= 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60; if (interval >= 1) return Math.floor(interval) + " mins ago";
        return "Just now";
    }

    // Init App
    mapDataCache = rawItems;
    setTimeout(() => { applyFilters(); }, 150);

</script>
</body>
</html>