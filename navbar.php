<!-- navbar.php – PREMIUM SAAS NAVBAR (Aeon Campus Design System) -->
<?php
$unreadNotifCount = 0;
if (isset($_SESSION['user_id'])) {
    if (!function_exists('getUnreadNotificationCount')) { require_once 'notifications.php'; }
    $unreadNotifCount = getUnreadNotificationCount($_SESSION['user_id']);
}
$unreadCount = isset($unreadCount) ? $unreadCount : 0;
$userInitial = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 1)) : 'U';

// Identify the current page to set the active state in the navbar
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* =========================================
       LIGHT THEME GLOBAL OVERRIDES
       ========================================= */
    body.light-theme {
        background-color: #f8fafc !important;
        color: #1e293b !important;
    }
    
    body.light-theme .glass-panel, 
    body.light-theme nav.glass-panel {
        background: rgba(255, 255, 255, 0.85) !important;
        border-top: 1px solid rgba(255, 255, 255, 1) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05) !important;
    }

    body.light-theme .text-white { color: #0f172a !important; }
    body.light-theme .text-on-surface-variant { color: #64748b !important; }
    body.light-theme .text-on-surface { color: #0f172a !important; }
    
    body.light-theme .bg-white\/5, 
    body.light-theme .bg-white\/10,
    body.light-theme .bg-surface-container-high,
    body.light-theme .bg-surface-container-low,
    body.light-theme .bg-surface-container-highest { 
        background-color: rgba(0, 0, 0, 0.03) !important; 
    }
    body.light-theme .border-white\/10,
    body.light-theme .border-white\/5 { 
        border-color: rgba(0, 0, 0, 0.1) !important; 
    }

    body.light-theme .bg-surface-container-high\/50,
    body.light-theme .bg-surface-container-high\/40 {
        background-color: #ffffff !important;
        border-color: rgba(0, 0, 0, 0.08) !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02) !important;
    }

    body.light-theme .hover\:bg-white\/10:hover {
        background-color: #f1f5f9 !important;
        border-color: rgba(0, 0, 0, 0.15) !important;
    }

    body.light-theme .input-glass {
        background-color: rgba(255, 255, 255, 0.9) !important;
        border-color: rgba(0, 0, 0, 0.15) !important;
        color: #0f172a !important;
    }
    body.light-theme .input-glass::placeholder { color: #94a3b8 !important; }

    body.light-theme .btn-outline-glass {
        background: rgba(0,0,0,0.03);
        border-color: rgba(0,0,0,0.15);
        color: #334155;
    }
    body.light-theme .btn-outline-glass:hover {
        background: rgba(0,0,0,0.08);
        color: #0f172a;
    }

    body.light-theme canvas#shader-canvas-ANIMATION_8 {
        filter: invert(1) hue-rotate(180deg);
        opacity: 0.25 !important;
    }
</style>

<nav class="fixed top-0 w-full glass-panel z-[100] border-b-0">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20 items-center">
            
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center gap-2 cursor-pointer" onclick="window.location.href='dashboard.php'">
                <span class="material-symbols-outlined text-primary text-3xl" data-weight="fill" style="font-variation-settings: 'FILL' 1;">explore</span>
                <span class="font-headline-md text-headline-md font-bold text-on-surface tracking-tight hidden sm:block">CampusFind</span>
            </div>

            <!-- Desktop Links -->
            <div class="hidden md:flex space-x-2">
                <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> px-4 py-2 rounded-lg font-label-md text-label-md transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">dashboard</span> Dashboard
                </a>
                <a href="my_reports.php" class="<?php echo ($current_page == 'my_reports.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> px-4 py-2 rounded-lg font-label-md text-label-md transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">folder_open</span> My Reports
                </a>
                <a href="search.php" class="<?php echo ($current_page == 'search.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> px-4 py-2 rounded-lg font-label-md text-label-md transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">search</span> Search
                </a>
                <a href="map.php" class="<?php echo ($current_page == 'map.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> px-4 py-2 rounded-lg font-label-md text-label-md transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">map</span> Map
                </a>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-4">
                
                <!-- Report Button (Desktop) -->
                <div class="hidden sm:block relative">
                    <button id="navReportBtn" class="btn-primary text-white font-label-md px-5 py-2.5 rounded-xl flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">add_circle</span> Report
                    </button>
                    <!-- Dropdown -->
                    <div id="navReportMenu" class="absolute right-0 mt-2 w-48 glass-panel rounded-xl shadow-glass opacity-0 invisible transition-all duration-200 transform translate-y-[-10px] z-50">
                        <div class="p-2 space-y-1">
                            <a href="report_lost.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/10 text-on-surface transition-colors">
                                <span class="material-symbols-outlined text-error text-[20px]">warning</span> Report Lost
                            </a>
                            <a href="report_found.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/10 text-on-surface transition-colors">
                                <span class="material-symbols-outlined text-secondary text-[20px]">check_circle</span> Report Found
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Theme Toggle Button -->
                <button id="themeToggleBtn" class="relative text-on-surface-variant hover:text-white transition-colors p-2 flex items-center justify-center" title="Toggle Light/Dark Mode">
                    <span class="material-symbols-outlined text-[24px]" id="themeIcon">light_mode</span>
                </button>

                <!-- Messages -->
                <a href="messages_list.php" class="relative text-on-surface-variant hover:text-white transition-colors p-2">
                    <span class="material-symbols-outlined">chat_bubble</span>
                    <span id="globalMsgBadge" class="absolute top-1 right-1 w-4 h-4 bg-error text-white text-[10px] font-bold flex items-center justify-center rounded-full <?php echo $unreadCount > 0 ? '' : 'hidden'; ?>"><?php echo $unreadCount; ?></span>
                </a>

                <!-- Notifications -->
                <div class="relative">
                    <button id="navNotifBtn" class="relative text-on-surface-variant hover:text-white transition-colors p-2 flex items-center justify-center">
                        <span class="material-symbols-outlined">notifications</span>
                        <span id="globalNotifBadge" class="absolute top-1 right-1 w-4 h-4 bg-error text-white text-[10px] font-bold flex items-center justify-center rounded-full <?php echo $unreadNotifCount > 0 ? '' : 'hidden'; ?>"><?php echo $unreadNotifCount; ?></span>
                    </button>
                    <!-- Dropdown -->
                    <div id="navNotifMenu" class="absolute right-0 mt-2 w-80 glass-panel rounded-xl shadow-glass opacity-0 invisible transition-all duration-200 transform translate-y-[-10px] z-50">
                        <div class="p-3 border-b border-white/10 flex justify-between items-center">
                            <span class="font-label-md text-white font-bold">Notifications</span>
                            <a href="#" id="markAllRead" class="text-[11px] text-primary hover:text-white transition-colors">Mark all read</a>
                        </div>
                        <div id="navNotifList" class="max-h-64 overflow-y-auto custom-scrollbar p-2">
                            <div class="p-3 text-center text-on-surface-variant text-sm">Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <div class="relative">
                    <button id="navProfileBtn" class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold border border-white/20 hover:shadow-glow transition-all">
                        <?php echo $userInitial; ?>
                    </button>
                    <!-- Dropdown -->
                    <div id="navProfileMenu" class="absolute right-0 mt-2 w-56 glass-panel rounded-xl shadow-glass opacity-0 invisible transition-all duration-200 transform translate-y-[-10px] z-50">
                        <div class="p-4 border-b border-white/10">
                            <p class="text-white font-bold text-sm truncate"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                            <p class="text-on-surface-variant text-xs mt-1 truncate"><?php echo htmlspecialchars($_SESSION['unique_id']); ?></p>
                        </div>
                        <div class="p-2 space-y-1">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 text-tertiary transition-colors text-sm">
                                    <span class="material-symbols-outlined text-[18px]">admin_panel_settings</span> Admin Panel
                                </a>
                            <?php endif; ?>
                            <a href="campus_tag_manage.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 text-on-surface transition-colors text-sm">
                                <span class="material-symbols-outlined text-[18px]">qr_code</span> Campus Tag
                            </a>
                            <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-error/20 text-error transition-colors text-sm mt-2">
                                <span class="material-symbols-outlined text-[18px]">logout</span> Sign Out
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mobile Menu Button -->
                <button id="navMobileBtn" class="md:hidden text-on-surface-variant hover:text-white p-2">
                    <span class="material-symbols-outlined text-2xl">menu</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <div id="navMobileMenu" class="md:hidden glass-panel border-x-0 border-t-0 opacity-0 invisible transition-all duration-300 absolute w-full top-20 left-0 max-h-[calc(100vh-80px)] overflow-y-auto z-50">
        <div class="px-4 py-4 space-y-2">
            <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> block px-4 py-3 rounded-xl font-label-md transition-colors flex items-center gap-3">
                <span class="material-symbols-outlined">dashboard</span> Dashboard
            </a>
            <a href="my_reports.php" class="<?php echo ($current_page == 'my_reports.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> block px-4 py-3 rounded-xl font-label-md transition-colors flex items-center gap-3">
                <span class="material-symbols-outlined">folder_open</span> My Reports
            </a>
            <a href="search.php" class="<?php echo ($current_page == 'search.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> block px-4 py-3 rounded-xl font-label-md transition-colors flex items-center gap-3">
                <span class="material-symbols-outlined">search</span> Search
            </a>
            <a href="map.php" class="<?php echo ($current_page == 'map.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> block px-4 py-3 rounded-xl font-label-md transition-colors flex items-center gap-3">
                <span class="material-symbols-outlined">map</span> Map
            </a>
            <a href="ai_matching.php" class="<?php echo ($current_page == 'ai_matching.php') ? 'text-white bg-white/10' : 'text-on-surface-variant hover:text-white hover:bg-white/5'; ?> block px-4 py-3 rounded-xl font-label-md transition-colors flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">memory</span> AI Match
            </a>
            
            <div class="h-px bg-white/10 my-4"></div>
            
            <a href="report_lost.php" class="block text-error hover:bg-white/5 px-4 py-3 rounded-xl font-label-md transition-colors flex items-center gap-3">
                <span class="material-symbols-outlined">warning</span> Report Lost Item
            </a>
            <a href="report_found.php" class="block text-secondary hover:bg-white/5 px-4 py-3 rounded-xl font-label-md transition-colors flex items-center gap-3">
                <span class="material-symbols-outlined">check_circle</span> Report Found Item
            </a>
        </div>
    </div>
</nav>

<!-- ========================================== -->
<!-- GLOBAL NAVBAR JAVASCRIPT (RUNS ON ALL PAGES) -->
<!-- ========================================== -->
<script>
    document.addEventListener('DOMContentLoaded', () => {

        // --- 1. DARK/LIGHT THEME LOGIC ---
        if (localStorage.getItem('campusfind_theme') === 'light') { document.body.classList.add('light-theme'); }
        const themeBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');
        if (document.body.classList.contains('light-theme') && themeIcon) { themeIcon.textContent = 'dark_mode'; }

        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                document.body.classList.toggle('light-theme');
                if (document.body.classList.contains('light-theme')) {
                    localStorage.setItem('campusfind_theme', 'light');
                    themeIcon.textContent = 'dark_mode';
                } else {
                    localStorage.setItem('campusfind_theme', 'dark');
                    themeIcon.textContent = 'light_mode';
                }
            });
        }

        // --- 2. DROPDOWN MENU LOGIC ---
        function setupNavDropdown(btnId, menuId) {
            const btn = document.getElementById(btnId);
            const menu = document.getElementById(menuId);
            if(!btn || !menu) return;

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isClosed = menu.classList.contains('opacity-0');
                
                // Close all other nav menus
                ['navReportMenu', 'navNotifMenu', 'navProfileMenu'].forEach(id => {
                    const m = document.getElementById(id);
                    if(m && m.id !== menuId) {
                        m.classList.add('opacity-0', 'invisible');
                        m.style.transform = 'translateY(-10px)';
                    }
                });
                
                if (isClosed) {
                    menu.classList.remove('opacity-0', 'invisible');
                    menu.style.transform = 'translateY(0)';
                    
                    // If it's the notification menu, fetch them!
                    if (btnId === 'navNotifBtn') { fetchGlobalNotifications(); }
                } else {
                    menu.classList.add('opacity-0', 'invisible');
                    menu.style.transform = 'translateY(-10px)';
                }
            });
        }

        setupNavDropdown('navReportBtn', 'navReportMenu');
        setupNavDropdown('navNotifBtn', 'navNotifMenu');
        setupNavDropdown('navProfileBtn', 'navProfileMenu');

        // Mobile Menu
        const mobileBtn = document.getElementById('navMobileBtn');
        const mobileMenu = document.getElementById('navMobileMenu');
        if(mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                mobileMenu.classList.toggle('opacity-0');
                mobileMenu.classList.toggle('invisible');
            });
        }

        // Close dropdowns on outside click
        document.addEventListener('click', (e) => {
            ['navReportMenu', 'navNotifMenu', 'navProfileMenu'].forEach(id => {
                const menu = document.getElementById(id);
                const btn = document.getElementById(id.replace('Menu', 'Btn'));
                if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
                    menu.classList.add('opacity-0', 'invisible');
                    menu.style.transform = 'translateY(-10px)';
                }
            });
            if(mobileMenu && mobileBtn && !mobileMenu.contains(e.target) && !mobileBtn.contains(e.target)) {
                mobileMenu.classList.add('opacity-0', 'invisible');
            }
        });

        // --- 3. GLOBAL AJAX POLLING (NOTIFICATIONS & MESSAGES) ---
        const globalNotifBadge = document.getElementById('globalNotifBadge');
        const globalNotifList = document.getElementById('navNotifList');
        const globalMsgBadge = document.getElementById('globalMsgBadge');
        const markAllReadBtn = document.getElementById('markAllRead');
        
        function fetchGlobalNotifications() {
            fetch('notifications_ajax.php?action=get_notifications_html')
                .then(res => res.json())
                .then(data => { if(data.success && globalNotifList) globalNotifList.innerHTML = data.html; })
                .catch(() => {});
            
            fetch('notifications_ajax.php?action=get_unread_count')
                .then(res => res.json())
                .then(data => {
                    if(data.success && globalNotifBadge) {
                        if(data.count > 0) { globalNotifBadge.textContent = data.count; globalNotifBadge.classList.remove('hidden'); }
                        else { globalNotifBadge.classList.add('hidden'); }
                    }
                }).catch(() => {});
        }

        function fetchGlobalMessagesCount() {
            fetch('messages_ajax.php?action=get_unread_count')
                .then(res => res.json())
                .then(data => {
                    if(data.success && globalMsgBadge) {
                        if(data.count > 0) { globalMsgBadge.textContent = data.count; globalMsgBadge.classList.remove('hidden'); }
                        else { globalMsgBadge.classList.add('hidden'); }
                    }
                }).catch(() => {});
        }

        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                fetch('notifications_ajax.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=mark_all_read' })
                .then(() => fetchGlobalNotifications());
            });
        }

        // Run immediately on page load
        fetchGlobalNotifications();
        fetchGlobalMessagesCount();

        // Poll every 15 seconds if tab is active
        setInterval(() => { 
            if(!document.hidden) { 
                fetchGlobalNotifications(); 
                fetchGlobalMessagesCount(); 
            } 
        }, 15000);

    });
</script>