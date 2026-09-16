<?php
// admin_dashboard.php – MODERN GLASSMORPHISM ADMIN DASHBOARD (Aeon Campus Design System)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
require 'db_connect.php';
require_once 'csrf.php';

$user_id = $_SESSION['user_id'];
$message = '';

// Handle delete actions securely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && validateCsrfToken($_POST['csrf_token'])) {
        if (isset($_POST['delete_report_id'])) {
            $del_id = intval($_POST['delete_report_id']);
            $img_check = $conn->query("SELECT image_path FROM item_reports WHERE id = $del_id");
            if ($img_row = $img_check->fetch_assoc()) {
                if (!empty($img_row['image_path']) && file_exists($img_row['image_path'])) {
                    unlink($img_row['image_path']);
                }
            }
            $conn->query("DELETE FROM item_reports WHERE id = $del_id");
            $message = '✅ Report and associated images deleted successfully.';
        }
        
        if (isset($_POST['delete_user_id'])) {
            $del_uid = intval($_POST['delete_user_id']);
            $conn->query("DELETE FROM users WHERE id = $del_uid");
            $message = '✅ User deleted successfully.';
        }
    } else {
        $message = '❌ Security token invalid. Please refresh the page.';
    }
}

// Fetch statistics (Excluding 'Campus Tag' chats)
$total_reports = $conn->query("SELECT COUNT(*) as count FROM item_reports WHERE category != 'Campus Tag'")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_lost = $conn->query("SELECT COUNT(*) as count FROM item_reports WHERE report_type = 'lost' AND category != 'Campus Tag'")->fetch_assoc()['count'];
$total_found = $conn->query("SELECT COUNT(*) as count FROM item_reports WHERE report_type = 'found' AND category != 'Campus Tag'")->fetch_assoc()['count'];

// Fetch all reports WITH User Identity (Excluding 'Campus Tag' chats)
$reports = $conn->query("
    SELECT ir.id, ir.item_name, ir.report_type, ir.category, ir.location, ir.latitude, ir.longitude, ir.report_date, ir.status, ir.user_id, u.unique_id, u.full_name 
    FROM item_reports ir 
    JOIN users u ON ir.user_id = u.id 
    WHERE ir.category != 'Campus Tag' 
    ORDER BY ir.created_at DESC
");

// Fetch all users
$users = $conn->query("SELECT id, full_name, username, email, role, created_at FROM users ORDER BY created_at DESC");

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
    <title>CampusFind - Admin Dashboard</title>
    
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
                        "surface-container-lowest": "#0b0e15", "success": "#10B981"
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
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 8px;}
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 8px; }
        .swal2-popup.dark-glass-modal { background: rgba(30, 41, 59, 0.95) !important; backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: #e1e2ec; font-family: 'Inter', sans-serif !important; }
        
        .input-glass { background-color: rgba(0, 0, 0, 0.3) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; color: #ffffff !important; transition: all 0.3s ease; }
        .input-glass:focus { border-color: #adc6ff !important; box-shadow: 0 0 10px rgba(173, 198, 255, 0.2) !important; outline: none !important; }
        .input-glass::placeholder { color: rgba(255, 255, 255, 0.4) !important; }
    </style>
</head>
<body class="antialiased selection:bg-primary selection:text-surface relative min-h-screen pb-20 flex flex-col">

<!-- Global Background Shader -->
<div class="fixed inset-0 z-[-1] pointer-events-none opacity-60">
    <div class="absolute inset-0 w-full h-full"><canvas id="shader-canvas-ANIMATION_8" style="display:block;width:100%;height:100%"></canvas></div>
</div>

<?php include 'navbar.php'; ?>

<main class="w-full max-w-7xl mx-auto px-4 sm:px-6 pt-32 pb-10 flex-grow fade-in-up visible">
    
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-4xl">admin_panel_settings</span> Admin Dashboard
            </h1>
            <p class="text-on-surface-variant text-sm mt-1">Manage system reports, users, and generate analytics.</p>
        </div>
        
        <!-- Links for Admin Maps & Reports -->
        <div class="flex gap-2 w-full md:w-auto">
            <a href="admin_locations.php" class="btn-outline-glass flex-1 md:flex-none px-4 py-3 rounded-xl font-bold flex items-center justify-center gap-2 text-sm transition-all text-tertiary border-tertiary/30 hover:bg-tertiary/10">
                <span class="material-symbols-outlined text-[18px]">add_location_alt</span> Map Zones
            </a>
            <a href="admin_spatial.php" class="btn-outline-glass flex-1 md:flex-none px-4 py-3 rounded-xl font-bold flex items-center justify-center gap-2 text-sm transition-all hover:bg-white/10">
                <span class="material-symbols-outlined text-[18px]">satellite_alt</span> Spatial Engine
            </a>
            <a href="admin_weekly_report.php" class="btn-primary flex-1 md:flex-none text-white font-bold py-3 px-6 rounded-xl flex items-center justify-center gap-2 text-sm shadow-glow transition-all">
                <span class="material-symbols-outlined text-[18px]">analytics</span> Weekly Report
            </a>
        </div>
    </div>

    <!-- PHP ALERTS -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-xl bg-success/20 border border-success/50 text-success text-sm font-medium flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">check_circle</span> 
            <?php echo str_replace('✅', '', $message); ?>
        </div>
    <?php endif; ?>

    <!-- KPI Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-10">
        <div class="glass-panel p-6 rounded-2xl hover:border-primary/50 transition-colors group">
            <div class="flex justify-between items-start mb-4"><span class="material-symbols-outlined text-primary text-3xl group-hover:scale-110 transition-transform">inventory_2</span></div>
            <div class="text-4xl font-bold text-white mb-1"><?php echo $total_reports; ?></div>
            <div class="text-on-surface-variant uppercase tracking-wider text-[10px] font-bold">Total Reports</div>
        </div>
        <div class="glass-panel p-6 rounded-2xl hover:border-error/50 transition-colors group">
            <div class="flex justify-between items-start mb-4"><span class="material-symbols-outlined text-error text-3xl group-hover:scale-110 transition-transform">warning</span></div>
            <div class="text-4xl font-bold text-white mb-1"><?php echo $total_lost; ?></div>
            <div class="text-on-surface-variant uppercase tracking-wider text-[10px] font-bold">Lost Items</div>
        </div>
        <div class="glass-panel p-6 rounded-2xl hover:border-secondary/50 transition-colors group">
            <div class="flex justify-between items-start mb-4"><span class="material-symbols-outlined text-secondary text-3xl group-hover:scale-110 transition-transform">check_circle</span></div>
            <div class="text-4xl font-bold text-white mb-1"><?php echo $total_found; ?></div>
            <div class="text-on-surface-variant uppercase tracking-wider text-[10px] font-bold">Found Items</div>
        </div>
        <div class="glass-panel p-6 rounded-2xl hover:border-tertiary/50 transition-colors group">
            <div class="flex justify-between items-start mb-4"><span class="material-symbols-outlined text-tertiary text-3xl group-hover:scale-110 transition-transform">group</span></div>
            <div class="text-4xl font-bold text-white mb-1"><?php echo $total_users; ?></div>
            <div class="text-on-surface-variant uppercase tracking-wider text-[10px] font-bold">Total Users</div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="glass-panel rounded-2xl p-6 sm:p-8 mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h2 class="text-xl font-bold text-white flex items-center gap-2"><span class="material-symbols-outlined text-primary">list_alt</span> All Reports</h2>
            <div class="relative w-full sm:w-64">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                <input type="text" id="reportSearch" onkeyup="filterTable('reportSearch', 'reportsTable')" class="w-full input-glass bg-transparent rounded-xl pl-10 pr-4 py-2 outline-none transition-all" placeholder="Search reports...">
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar pb-4">
            <table class="w-full text-left border-collapse min-w-[900px]" id="reportsTable">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">ID</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Item</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Type</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Category</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Location</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Date</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Status</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-primary border-b border-white/10">Reported By</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $reports->fetch_assoc()): 
                        $typeClass = $row['report_type'] == 'lost' ? 'bg-error/20 text-error border-error/30' : 'bg-secondary/20 text-secondary border-secondary/30';
                        $statusClass = 'bg-surface-container-highest text-on-surface-variant border-white/10';
                        if ($row['status'] === 'returned') $statusClass = 'bg-primary/20 text-primary border-primary/30';
                        elseif ($row['status'] === 'matched') $statusClass = 'bg-tertiary/20 text-tertiary border-tertiary/30';
                        elseif ($row['status'] === 'verifying') $statusClass = 'bg-warning/20 text-warning border-warning/30';
                    ?>
                        <tr class="hover:bg-white/5 transition-colors border-b border-white/5">
                            <td class="px-4 py-3 text-sm text-white/70 whitespace-nowrap">#<?php echo $row['id']; ?></td>
                            <td class="px-4 py-3 text-sm font-bold text-white whitespace-nowrap"><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wider <?php echo $typeClass; ?>"><?php echo ucfirst($row['report_type']); ?></span></td>
                            <td class="px-4 py-3 text-sm text-white/70 whitespace-nowrap"><?php echo htmlspecialchars($row['category']); ?></td>
                            <td class="px-4 py-3 text-sm text-white/70 whitespace-nowrap">
                                <?php if (!empty(trim($row['location']))): echo htmlspecialchars($row['location']); ?>
                                <?php elseif (!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                    <a href="item_detail.php?id=<?php echo $row['id']; ?>" class="text-tertiary hover:underline flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">location_on</span> Pinned</a>
                                <?php else: echo '<span class="opacity-50">N/A</span>'; endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-white/70 whitespace-nowrap"><?php echo formatDateSafe($row['report_date']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wider <?php echo $statusClass; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-bold text-white"><?php echo htmlspecialchars($row['unique_id']); ?></span><br>
                                <span class="text-[10px] uppercase tracking-wider text-primary/70"><?php echo htmlspecialchars($row['full_name']); ?></span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this, 'Delete this report and its associated images?');">
                                    <input type="hidden" name="delete_report_id" value="<?php echo $row['id']; ?>">
                                    <?php csrfInput(); ?>
                                    <button type="submit" class="bg-error/10 hover:bg-error/20 border border-error/20 text-error px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Users Table -->
    <div class="glass-panel rounded-2xl p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h2 class="text-xl font-bold text-white flex items-center gap-2"><span class="material-symbols-outlined text-primary">group</span> All Users</h2>
            <div class="relative w-full sm:w-64">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                <input type="text" id="userSearch" onkeyup="filterTable('userSearch', 'usersTable')" class="w-full input-glass bg-transparent rounded-xl pl-10 pr-4 py-2 outline-none transition-all" placeholder="Search users...">
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar pb-4">
            <table class="w-full text-left border-collapse min-w-[800px]" id="usersTable">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">ID</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Name</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Username</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Email</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10">Role</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant border-b border-white/10 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $users->fetch_assoc()): 
                        $roleClass = $row['role'] == 'admin' ? 'bg-tertiary/20 text-tertiary border-tertiary/30' : 'bg-secondary/20 text-secondary border-secondary/30';
                    ?>
                        <tr class="hover:bg-white/5 transition-colors border-b border-white/5">
                            <td class="px-4 py-3 text-sm text-white/70 whitespace-nowrap">#<?php echo $row['id']; ?></td>
                            <td class="px-4 py-3 text-sm font-bold text-white whitespace-nowrap"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td class="px-4 py-3 text-sm text-white/70 whitespace-nowrap"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="px-4 py-3 text-sm text-white/70 whitespace-nowrap"><?php echo htmlspecialchars($row['email']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wider <?php echo $roleClass; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?php if ($row['role'] !== 'admin'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this, 'Delete this user and all their data?');">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $row['id']; ?>">
                                        <?php csrfInput(); ?>
                                        <button type="submit" class="bg-error/10 hover:bg-error/20 border border-error/20 text-error px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 text-[10px] uppercase tracking-wider font-bold text-white/30 border border-white/5 rounded-lg">Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    // Profile Dropdown
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

    // Filter Table
    function filterTable(inputId, tableId) {
        const filter = document.getElementById(inputId).value.toLowerCase();
        const rows = document.getElementById(tableId).getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            let text = rows[i].textContent || rows[i].innerText;
            rows[i].style.display = text.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
        }
    }

    function confirmDelete(form, msg) {
        Swal.fire({ title: 'Are you sure?', text: msg, icon: 'warning', showCancelButton: true, confirmButtonColor: '#EF4444', cancelButtonColor: '#64748B', confirmButtonText: 'Delete', background: '#1E293B', color: '#FFFFFF', customClass: { popup: 'dark-glass-modal' } }).then((r) => { if (r.isConfirmed) form.submit(); });
        return false;
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