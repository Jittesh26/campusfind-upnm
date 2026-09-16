<?php
// admin_spatial.php – ADVANCED GIS COMMAND CENTER (Leaflet Fixed - Bright Map / Dark UI)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_sql = '';
if ($filter === 'lost') $filter_sql = "AND ir.report_type = 'lost'";
elseif ($filter === 'found') $filter_sql = "AND ir.report_type = 'found'";

$sql = "SELECT 
            ir.id, ir.item_name, ir.report_type, ir.category, ir.status, 
            ir.location as location_name, ir.latitude, ir.longitude, 
            ir.description, ir.created_at, ir.search_radius, ir.location_method,
            cl.boundary_geojson, u.unique_id, u.full_name
        FROM item_reports ir
        JOIN users u ON ir.user_id = u.id
        LEFT JOIN campus_locations cl ON ir.campus_location_id = cl.id
        WHERE ir.latitude IS NOT NULL 
          AND ir.longitude IS NOT NULL
        ORDER BY ir.created_at DESC";

$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) { $items[] = $row; }
?>
<!DOCTYPE html>
<html class="dark scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <title>CampusFind - Admin Spatial Intelligence</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Pure Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />

    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "surface": "#10131a", "primary": "#adc6ff", "secondary": "#44e2cd", "tertiary": "#ffb786", "error": "#ffb4ab", "success": "#10B981", "warning": "#F59E0B", "surface-container-high": "#272a31", "on-surface-variant": "#c2c6d6" } } } }
    </script>
    <style>
        body { background-color: #10131a; color: #e1e2ec; overflow-x: hidden; font-family: 'Inter', sans-serif; }
        .glass-panel { background: rgba(25, 27, 35, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .btn-primary { background: linear-gradient(90deg, #4d8eff 0%, #03c6b2 100%); transition: all 0.3s ease; cursor: pointer; }
        .btn-primary:hover { box-shadow: 0 0 20px rgba(77, 142, 255, 0.4); transform: translateY(-1px); }
        .btn-outline-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.8); transition: all 0.3s ease; cursor: pointer; }
        .btn-outline-glass:hover { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }
        .btn-outline-glass.active { background: rgba(99, 102, 241, 0.2); border-color: #6366F1; color: #A5B4FC; }
        .btn-outline-glass.active-red { background: rgba(239, 68, 68, 0.2); border-color: #EF4444; color: #FCA5A5; }
        .btn-outline-glass.active-green { background: rgba(16, 185, 129, 0.2); border-color: #10B981; color: #6EE7B7; }
        .btn-outline-glass.active-purple { background: rgba(139, 92, 246, 0.2); border-color: #8B5CF6; color: #C4B5FD; }
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease-out, transform 0.8s ease-out; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }

        /* Leaflet Bright Map & Z-Index Fixes */
        .leaflet-container { background: #e5e7eb; font-family: 'Inter', sans-serif; z-index: 1; outline: none; border-radius: 12px; }
        .leaflet-popup-content-wrapper { background: rgba(15, 23, 42, 0.95) !important; color: #e1e2ec !important; border: 1px solid rgba(255,255,255,0.15) !important; backdrop-filter: blur(16px) !important; border-radius: 16px !important; box-shadow: 0 10px 40px rgba(0,0,0,0.6) !important; padding: 0 !important; overflow: hidden; }
        .leaflet-popup-content { margin: 0 !important; width: 100% !important; }
        .leaflet-popup-tip { border-top-color: rgba(15, 23, 42, 0.95) !important; }
        .leaflet-control-zoom { border: 1px solid rgba(255,255,255,0.1) !important; border-radius: 8px !important; overflow: hidden; }
        .leaflet-control-zoom a { background: rgba(30, 41, 59, 0.85) !important; color: white !important; backdrop-filter: blur(8px); }
        .leaflet-control-zoom a:hover { background: rgba(99, 102, 241, 0.9) !important; }
        .leaflet-control-container .leaflet-bottom { bottom: 20px; }
        .leaflet-control-container .leaflet-right { right: 20px; }
        
        .map-floating-controls { position: absolute; top: 16px; left: 16px; z-index: 1000; pointer-events: none; }
        .map-floating-controls > * { pointer-events: auto; }
        .map-overlay-top { z-index: 9999 !important; } /* Fix dropdown overlap */
        .radius-active-cursor { cursor: crosshair !important; }
        .swal2-popup.dark-glass-modal { background: rgba(30, 41, 59, 0.95) !important; backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: #e1e2ec; }
    </style>
</head>
<body class="antialiased font-body-md selection:bg-primary-container selection:text-white relative min-h-screen pb-6 flex flex-col">

<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60">
    <div class="absolute inset-0 w-full h-full"><canvas id="shader-canvas-ANIMATION_8" style="display:block;width:100%;height:100%"></canvas></div>
    <script>
        (function() {
          const canvas = document.getElementById('shader-canvas-ANIMATION_8');
          function syncSize() { const w = canvas.clientWidth || 1280; const h = canvas.clientHeight || 720; if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; } }
          if (typeof ResizeObserver !== 'undefined') { new ResizeObserver(syncSize).observe(canvas); } syncSize();
          const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl'); if (!gl) return;
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

<?php include 'navbar.php'; ?>

<main class="w-full max-w-[1400px] mx-auto px-4 sm:px-6 pt-24 sm:pt-28 pb-4 flex-grow flex flex-col fade-in-up visible min-h-[80vh]">
    
    <div class="glass-panel p-4 sm:p-6 rounded-2xl relative overflow-hidden flex flex-col shadow-[0_0_40px_rgba(0,0,0,0.5)]" style="min-height: 700px; height: calc(100vh - 140px);">
        
        <!-- Header & Filters (Z-Index Fixed) -->
        <div class="map-overlay-top flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-4 pb-4 border-b border-white/10 shrink-0">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary text-3xl">satellite_alt</span> Spatial Intelligence <span class="bg-primary/20 text-primary text-[10px] uppercase tracking-widest px-2 py-0.5 rounded ml-2 border border-primary/30">Admin Mode</span>
                </h1>
                <p class="text-on-surface-variant text-sm mt-1">Real-time geospatial tracking, radius mapping, and heat analysis.</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <div class="bg-surface-container-high/50 p-1.5 rounded-xl border border-white/5 flex gap-1">
                    <button onclick="setFilter('type', 'all')" id="btn-type-all" class="btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold active">All</button>
                    <button onclick="setFilter('type', 'lost')" id="btn-type-lost" class="btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-error"></span> Lost</button>
                    <button onclick="setFilter('type', 'found')" id="btn-type-found" class="btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-success"></span> Found</button>
                    <button onclick="setFilter('type', 'tags')" id="btn-type-tags" class="btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-tertiary"></span> QR Scans</button>
                </div>

                <div class="bg-surface-container-high/50 p-1.5 rounded-xl border border-white/5 flex gap-1">
                    <button onclick="setFilter('time', 'all')" id="btn-time-all" class="btn-outline-glass px-3 py-1.5 rounded-lg text-xs font-bold active">All Time</button>
                    <button onclick="setFilter('time', '24h')" id="btn-time-24h" class="btn-outline-glass px-3 py-1.5 rounded-lg text-xs font-bold">24H</button>
                    <button onclick="setFilter('time', '7d')" id="btn-time-7d" class="btn-outline-glass px-3 py-1.5 rounded-lg text-xs font-bold">7 Days</button>
                </div>

                <button onclick="toggleHighValue()" id="btn-high-value" class="btn-outline-glass px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-1 bg-surface-container-high/50 border-white/5">
                    <span class="material-symbols-outlined text-[16px]">diamond</span> High Value
                </button>
            </div>
        </div>

        <!-- The Map Container -->
        <div class="flex-1 w-full rounded-xl border border-white/10 shadow-inner z-10 overflow-hidden relative flex flex-col mt-20 md:mt-16">
            <div class="map-floating-controls flex flex-col gap-2">
                <button onclick="toggleHeatmap()" id="btn-heatmap" class="bg-surface-container-high/90 backdrop-blur border border-white/10 text-white px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-white/10 transition-colors shadow-lg">
                    <span class="material-symbols-outlined text-[16px] text-warning">local_fire_department</span> Recency Heatmap
                </button>
                <button onclick="activateRadiusTool()" id="btn-radius" class="bg-surface-container-high/90 backdrop-blur border border-white/10 text-white px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-white/10 transition-colors shadow-lg">
                    <span class="material-symbols-outlined text-[16px] text-primary">radar</span> Buffer Search (100m)
                </button>
                <button onclick="clearRadius()" id="btn-clear-radius" class="bg-error/20 border border-error/30 text-error px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-error/30 transition-colors shadow-lg hidden">
                    <span class="material-symbols-outlined text-[16px]">close</span> Clear Buffer
                </button>
            </div>

            <div class="absolute top-4 right-4 z-[1000] flex flex-col gap-2">
                <button onclick="playTimeline()" id="btn-timeline" class="bg-success/20 backdrop-blur border border-success/30 text-success px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-success/30 transition-colors shadow-lg">
                    <span class="material-symbols-outlined text-[16px]">play_arrow</span> Play Timeline
                </button>
                <button onclick="copyShareLink()" class="bg-surface-container-high/90 backdrop-blur border border-white/10 text-white px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-primary/20 transition-colors shadow-lg">
                    <span class="material-symbols-outlined text-[16px]">link</span> Share Map View
                </button>
            </div>

            <div id="timeline-display" class="absolute bottom-6 left-1/2 -translate-x-1/2 z-[1000] bg-surface-container-highest/90 border border-white/10 backdrop-blur-md px-6 py-2 rounded-full text-white font-bold tracking-widest uppercase text-sm shadow-glass hidden">Day 1</div>

            <div id="map" class="w-full h-full flex-1 rounded-xl"></div>
        </div>

        <!-- Footer Stats -->
        <div class="mt-4 pt-4 border-t border-white/10 flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0">
            <div class="flex items-center gap-3 text-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px] text-secondary">memory</span>
                <span>Displaying <strong class="text-white" id="visibleCount">0</strong> active incident vectors.</span>
            </div>
            <button onclick="centerMap();" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold text-white flex justify-center items-center gap-2 shadow-glow w-full sm:w-auto">
                <span class="material-symbols-outlined text-[18px]">my_location</span> Center Map
            </button>
        </div>
        
    </div>
</main>

<!-- Pure Leaflet & Turf.js -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

<script>
    // --- DROPDOWN LOGIC ---
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('opacity-0'); profileMenu.classList.toggle('invisible'); });
        document.addEventListener('click', (e) => { if (!profileMenu.contains(e.target) && !profileBtn.contains(e.target)) { profileMenu.classList.add('opacity-0', 'invisible'); } });
    }

    // --- MASTER GEOSPATIAL LOGIC (LEAFLET IMPLEMENTATION) ---
    const rawItems = <?php echo json_encode($items); ?>;
    let currentFilters = { type: 'all', time: 'all', highValueOnly: false, timelineDay: null };
    let isHeatmapActive = false, radiusModeActive = false, radiusCircle = null, currentBufferCenter = null, timelineInterval = null;
    let activeMarkers = [];
    
    // FIX: Standard Bright OpenStreetMap Base
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' });

    const map = L.map('map', { zoomControl: false, layers: [osmLayer] }).setView([3.048000, 101.725000], 17);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    new ResizeObserver(() => { map.invalidateSize(); }).observe(document.getElementById('map'));

    // Campus GeoFence
    L.polygon([ [3.044000, 101.721500], [3.052500, 101.721500], [3.052500, 101.727500], [3.044000, 101.727500] ], { color: '#6366F1', weight: 2, opacity: 0.5, fillColor: '#6366F1', fillOpacity: 0.05, dashArray: '5,5' }).addTo(map);

    const clusterGroup = L.markerClusterGroup({ maxClusterRadius: 40, spiderfyOnMaxZoom: true, showCoverageOnHover: false });
    const shapesGroup = L.layerGroup().addTo(map);
    let heatLayer = null;

    // --- Core Rendering Function ---
    function renderMap() {
        clusterGroup.clearLayers();
        shapesGroup.clearLayers();
        if (heatLayer) map.removeLayer(heatLayer);
        
        let heatPoints = [];
        let visibleCount = 0;
        const now = new Date();

        rawItems.forEach(item => {
            if (!item.latitude || !item.longitude) return;
            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);

            // Filters
            if (currentFilters.type === 'tags' && item.category !== 'Campus Tag') return;
            if (currentFilters.type !== 'all' && currentFilters.type !== 'tags' && item.report_type !== currentFilters.type) return;
            if (currentFilters.type !== 'tags' && item.category === 'Campus Tag') return; 

            let diffHours = 0;
            if (item.created_at) {
                const itemDate = new Date(item.created_at.replace(/-/g, '/'));
                if (!isNaN(itemDate.getTime())) {
                    diffHours = (now - itemDate) / (1000 * 60 * 60);
                    if (currentFilters.timelineDay !== null) {
                        const startHour = currentFilters.timelineDay * 24; const endHour = (currentFilters.timelineDay + 1) * 24;
                        if (diffHours < startHour || diffHours > endHour) return;
                    } else if (currentFilters.time !== 'all') {
                        if (currentFilters.time === '24h' && diffHours > 24) return;
                        if (currentFilters.time === '7d' && diffHours > (24 * 7)) return;
                    }
                }
            }

            if (currentFilters.highValueOnly) {
                const cat = item.category || '';
                if (!(cat.includes('Electronics') || cat.includes('Laptop') || cat.includes('Phone') || cat.includes('Wallet') || cat.includes('Personal'))) return;
            }

            // Buffer Search Filter
            if (currentBufferCenter && radiusCircle) {
                const itemLatLng = L.latLng(lat, lng);
                if (map.distance(currentBufferCenter, itemLatLng) > 100) return;
            }

            visibleCount++;

            // Data Rendering
            if (isHeatmapActive) {
                let intensity = 0.3; 
                if (diffHours <= 24) intensity = 1.0; else if (diffHours <= 72) intensity = 0.7;
                heatPoints.push([lat, lng, intensity]);
            } else {
                const isLost = item.report_type === 'lost';
                const isTag = item.category === 'Campus Tag';
                let colorHex = isLost ? '#EF4444' : '#10B981';
                let bgColor = isLost ? '#ffb4ab' : '#44e2cd'; let textColor = isLost ? '#690005' : '#003731'; let label = isLost ? 'L' : 'F';
                if (isTag) { colorHex = '#8B5CF6'; bgColor = '#c4b5fd'; textColor = '#3b0764'; label = 'QR'; }

                // 🔥 FIX: Translucent Shapes
                if (item.location_method === 'preset' && item.boundary_geojson && item.boundary_geojson.trim() !== '') {
                    try {
                        const poly = JSON.parse(item.boundary_geojson);
                        L.geoJSON(poly, { style: { color: colorHex, fillColor: colorHex, fillOpacity: 0.08, weight: 1.5, dashArray: '4,4' } }).addTo(shapesGroup);
                    } catch(e) {}
                } else {
                    const r = parseInt(item.search_radius) || 100;
                    L.circle([lat, lng], { radius: r, color: colorHex, fillColor: colorHex, fillOpacity: 0.08, weight: 1.5, dashArray: '4,4' }).addTo(shapesGroup);
                }

                // 2. Draw Marker
                const icon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div class="w-8 h-8 rounded-full flex items-center justify-center text-[14px] font-bold border-2 border-white shadow-lg transition-transform hover:scale-110" style="background-color: ${bgColor}; color: ${textColor}; box-shadow: 0 0 15px ${bgColor}80;">${label}</div>`,
                    iconSize: [32, 32], iconAnchor: [16, 32]
                });

                const badgeClass = isTag ? 'bg-tertiary/20 text-tertiary border-tertiary/30' : (isLost ? 'bg-error/20 text-error border-error/30' : 'bg-success/20 text-success border-success/30');
                let statusClass = 'bg-surface-container-highest text-on-surface-variant border-white/10';
                if (item.status === 'verifying') statusClass = 'bg-warning/20 text-warning border-warning/30';
                else if (item.status === 'matched') statusClass = 'bg-primary/20 text-primary border-primary/30';

                const radText = item.location_method === 'preset' ? 'Zone Boundary' : `${item.search_radius || 100}m Radius`;
                const aiBoostHtml = !isTag ? `<div class="text-[10px] text-primary bg-primary/10 border border-primary/20 px-2 py-1 rounded mt-3 font-bold flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">auto_awesome</span> AI Spatial Boost Enabled</div>` : '';

                const marker = L.marker([lat, lng], { icon: icon });
                marker.bindPopup(`
                    <div class="p-4 w-60 sm:w-64 font-body-md text-white flex flex-col gap-2">
                        <h6 class="font-headline-md font-bold text-base text-white truncate mb-1">${item.item_name}</h6>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span class="px-2 py-0.5 rounded border text-[9px] font-bold uppercase tracking-wider ${badgeClass}">${isTag ? 'QR Scan' : item.report_type}</span>
                            <span class="px-2 py-0.5 rounded border text-[9px] font-bold uppercase tracking-wider ${statusClass}">${item.status}</span>
                        </div>
                        <div class="text-xs text-on-surface-variant space-y-1.5 border-t border-white/10 pt-2">
                            <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[12px] text-primary">person</span> ${item.unique_id || 'Anonymous'}</div>
                            <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[12px] text-tertiary">radar</span> ${radText}</div>
                        </div>
                        ${aiBoostHtml}
                        <a href="item_detail.php?id=${item.id}" target="_blank" class="mt-4 block text-center bg-gradient-to-r from-primary to-inverse-primary text-white py-2.5 rounded-lg text-xs font-bold hover:shadow-glow transition-all">View Details</a>
                    </div>
                `);

                clusterGroup.addLayer(marker);
            }
        });

        if (isHeatmapActive) {
            if (typeof L.heatLayer === 'function') heatLayer = L.heatLayer(heatPoints, { radius: 35, blur: 25, maxZoom: 17, gradient: {0.4: '#4d8eff', 0.6: '#10B981', 0.8: '#F59E0B', 1.0: '#EF4444'} }).addTo(map);
        } else {
            map.addLayer(clusterGroup);
        }

        document.getElementById('visibleCount').innerText = visibleCount;
        
        if (radiusModeActive === false && radiusCircle && visibleCount === 0) {
            Swal.fire({ icon: 'info', title: 'No Results in Zone', text: 'No items found within 100m of this pin.', toast: true, position: 'top', showConfirmButton: false, timer: 3000, background: '#1E293B', color: '#FFFFFF' });
            clearRadius(); 
        }
        updateURLParams();
    }

    // --- Interactive Toggles ---
    function setFilter(type, val) {
        currentFilters[type] = val;
        if (type === 'type') {
            document.querySelectorAll('#btn-type-all, #btn-type-lost, #btn-type-found, #btn-type-tags').forEach(b => b.className = 'btn-outline-glass px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1');
            const activeBtn = document.getElementById(`btn-type-${val}`);
            if (val === 'all') activeBtn.classList.add('active');
            if (val === 'lost') activeBtn.classList.add('active-red');
            if (val === 'found') activeBtn.classList.add('active-green');
            if (val === 'tags') activeBtn.classList.add('active-purple');
        } else if (type === 'time') {
            document.querySelectorAll('#btn-time-all, #btn-time-24h, #btn-time-7d').forEach(b => b.className = 'btn-outline-glass px-3 py-1.5 rounded-lg text-xs font-bold');
            document.getElementById(`btn-time-${val}`).classList.add('active');
        }
        renderMap();
    }

    function toggleHighValue() { currentFilters.highValueOnly = !currentFilters.highValueOnly; const btn = document.getElementById('btn-high-value'); if (currentFilters.highValueOnly) { btn.classList.replace('bg-surface-container-high/50', 'bg-warning/20'); btn.classList.add('text-warning', 'border-warning/50'); } else { btn.classList.replace('bg-warning/20', 'bg-surface-container-high/50'); btn.classList.remove('text-warning', 'border-warning/50'); } renderMap(); }
    
    function toggleHeatmap() { 
        isHeatmapActive = !isHeatmapActive; 
        const btn = document.getElementById('btn-heatmap'); 
        if (isHeatmapActive) { 
            btn.innerHTML = '<span class="material-symbols-outlined text-[16px] text-white">location_on</span> View Pins'; 
            btn.classList.add('bg-warning/20', 'border-warning/50', 'text-warning'); btn.classList.remove('bg-surface-container-high/90', 'border-white/10', 'text-white'); 
        } else { 
            btn.innerHTML = '<span class="material-symbols-outlined text-[16px] text-warning">local_fire_department</span> Recency Heatmap'; 
            btn.classList.remove('bg-warning/20', 'border-warning/50', 'text-warning'); btn.classList.add('bg-surface-container-high/90', 'border-white/10', 'text-white'); 
        } 
        renderMap(); 
    }

    // --- Radius Buffer Tool ---
    function activateRadiusTool() {
        radiusModeActive = true;
        document.getElementById('map').classList.add('radius-active-cursor');
        Swal.fire({ title: 'Buffer Tool Active', text: 'Click on the map to draw a 100-meter search radius.', icon: 'info', toast: true, position: 'top', showConfirmButton: false, timer: 3000, background: '#1E293B', color: '#FFFFFF' });
    }

    map.on('click', function(e) {
        if (!radiusModeActive) return;
        currentBufferCenter = e.latlng;
        
        if (radiusCircle) shapesGroup.removeLayer(radiusCircle);
        
        const circleGeo = turf.circle([currentBufferCenter.lng, currentBufferCenter.lat], 0.1, { steps: 64, units: 'kilometers' });
        radiusCircle = L.geoJSON(circleGeo, { style: { color: '#6366F1', fillColor: '#4d8eff', fillOpacity: 0.2, weight: 2, dashArray: '5,5' } }).addTo(shapesGroup);

        radiusModeActive = false; document.getElementById('map').classList.remove('radius-active-cursor'); document.getElementById('btn-clear-radius').classList.remove('hidden');
        renderMap();
    });

    function clearRadius() { if (radiusCircle) shapesGroup.removeLayer(radiusCircle); radiusCircle = null; currentBufferCenter = null; document.getElementById('btn-clear-radius').classList.add('hidden'); renderMap(); }
    function centerMap() { map.flyTo({center: [3.048000, 101.725000], zoom: 17}); }

    // --- 24H Time-Lapse Engine ---
    function playTimeline() {
        const btn = document.getElementById('btn-timeline'); const display = document.getElementById('timeline-display');
        if (timelineInterval) { clearInterval(timelineInterval); timelineInterval = null; currentFilters.timelineDay = null; display.classList.add('hidden'); btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">play_arrow</span> Play Timeline'; renderMap(); return; }
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">stop</span> Stop Engine'; display.classList.remove('hidden');
        let day = 6; currentFilters.timelineDay = day; renderMap(); display.innerText = `${day + 1} Days Ago`;
        timelineInterval = setInterval(() => {
            day--;
            if (day < 0) { clearInterval(timelineInterval); timelineInterval = null; currentFilters.timelineDay = null; display.classList.add('hidden'); btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">play_arrow</span> Play Timeline'; renderMap(); } 
            else { currentFilters.timelineDay = day; display.innerText = day === 0 ? "Today" : `${day + 1} Days Ago`; renderMap(); }
        }, 1500); 
    }

    function updateURLParams() { const url = new URL(window.location); url.searchParams.set('type', currentFilters.type); url.searchParams.set('time', currentFilters.time); if (currentFilters.highValueOnly) url.searchParams.set('hv', '1'); else url.searchParams.delete('hv'); if (isHeatmapActive) url.searchParams.set('view', 'heat'); else url.searchParams.delete('view'); window.history.replaceState({}, '', url); }
    function loadURLParams() { const urlParams = new URLSearchParams(window.location.search); if (urlParams.has('type')) setFilter('type', urlParams.get('type')); if (urlParams.has('time')) setFilter('time', urlParams.get('time')); if (urlParams.has('hv') && urlParams.get('hv') === '1') toggleHighValue(); if (urlParams.has('view') && urlParams.get('view') === 'heat') toggleHeatmap(); }
    function copyShareLink() { navigator.clipboard.writeText(window.location.href); Swal.fire({ title: 'Link Copied!', text: 'Current map filters saved to clipboard.', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } }); }

    // Init App
    setTimeout(() => { loadURLParams(); renderMap(); }, 300);

    // Fade in animations
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => { entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('visible'); } }); }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in-up').forEach((el) => { observer.observe(el); });
    });
</script>
</body>
</html>