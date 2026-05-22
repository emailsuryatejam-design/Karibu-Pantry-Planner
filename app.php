<?php
require_once __DIR__ . '/config.php';

// Prevent LiteSpeed/proxy caching — dynamic content per user
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-LiteSpeed-Cache-Control: no-cache');

requireLogin();

$user = currentUser();

// Default landing pages
$defaultPage = 'dashboard';
if (isStorekeeper()) $defaultPage = 'store-dashboard';

$page = $_GET['page'] ?? $defaultPage;

// Valid pages per role
$chefPages  = ['dashboard', 'orders', 'requisition', 'review-supply', 'day-close', 'menu-plan', 'recipes', 'kitchen-inventory', 'reports', 'settings'];
$storePages = ['store-dashboard', 'store-orders', 'store-inventory', 'store-history', 'reports', 'settings'];
$adminPages = array_unique(array_merge($chefPages, $storePages, [
    'admin-home', 'admin-users', 'admin-camps', 'admin-meal-types',
    'admin-items', 'admin-kitchens', 'admin-req-types', 'admin-set-menus',
    'admin-emails', 'admin-orders', 'admin-stock', 'admin-recipes',
    'admin-reports', 'admin-audit', 'admin-recycle',
]));

$allowedPages = isAdmin() ? $adminPages : (isChef() ? $chefPages : $storePages);
if (!in_array($page, $allowedPages)) {
    $page = $allowedPages[0];
}

// Page titles
$pageTitles = [
    'dashboard'        => 'Dashboard',
    'orders'           => 'Orders',
    'requisition'      => 'Requisition',
    'review-supply'    => 'Review Supply',
    'day-close'        => 'Day Close',
    'menu-plan'        => 'Menu Plan',
    'recipes'          => 'Recipes',
    'kitchen-inventory'=> 'Kitchen Stock',
    'reports'          => 'Reports',
    'store-dashboard'  => 'Store Dashboard',
    'store-orders'     => 'Store Orders',
    'store-inventory'  => 'Inventory',
    'store-history'    => 'History',
    'admin-home'       => 'Overview',
    'admin-orders'     => 'All Orders',
    'admin-recipes'    => 'Recipe Library',
    'admin-stock'      => 'Stock Control',
    'admin-reports'    => 'Reports & Analytics',
    'admin-users'      => 'Users',
    'admin-camps'      => 'Camps',
    'admin-meal-types' => 'Meal Types',
    'admin-items'      => 'Items',
    'admin-kitchens'   => 'Kitchens',
    'admin-req-types'  => 'Req Types',
    'admin-set-menus'  => 'Set Menus',
    'admin-emails'     => 'Email Alerts',
    'admin-audit'      => 'Audit Log',
    'settings'         => 'Settings',
];
$pageTitle = $pageTitles[$page] ?? 'Pantry Planner';

$kitchenName  = $user['kitchen_name'] ?? '';
$roleColor    = isStorekeeper() ? 'green' : 'orange';
$isAdminRole  = isAdmin();

// Admin portal pages get a completely different layout
$adminPortalPages = [
    'admin-home','admin-orders','admin-recipes','admin-stock','admin-reports',
    'admin-users','admin-camps','admin-items','admin-meal-types',
    'admin-emails','admin-audit','admin-kitchens','admin-req-types','admin-set-menus',
];
$isAdminPage = $isAdminRole && in_array($page, $adminPortalPages);

// Nav group membership for active-state highlighting
$opsPages  = ['admin-orders','admin-reports'];
$cntPages  = ['admin-recipes','admin-stock'];
$mgmtPages = ['admin-users','admin-camps','admin-items','admin-meal-types','admin-emails','admin-kitchens','admin-req-types','admin-set-menus'];

$pageFile = __DIR__ . '/pages/' . $page . '.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php if ($isAdminPage): ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php else: ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php endif; ?>
    <meta name="theme-color" content="#0f172a">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <title><?= htmlspecialchars($pageTitle) ?> — Karibu Pantry Planner</title>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>

<?php if ($isAdminPage): ?>
<style>
/* ── Admin Portal Shell ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

body.admin-portal {
    background: #f1f5f9;
    font-family: 'Inter', system-ui, sans-serif;
    margin: 0;
}

/* ── Top Navigation ─────────────────────────────────────────────── */
.admin-topnav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 200;
    background: #0f172a;
    height: 56px;
    display: flex; align-items: center;
    padding: 0 20px;
    box-shadow: 0 1px 0 rgba(255,255,255,0.06), 0 4px 20px rgba(0,0,0,0.4);
}
.admin-topnav-inner {
    display: flex; align-items: center; gap: 4px;
    width: 100%; max-width: 1400px; margin: 0 auto;
}

/* ── Logo ──────────────────────────────────────────────────────── */
.admin-logo {
    display: flex; align-items: center; gap: 8px;
    text-decoration: none; flex-shrink: 0; margin-right: 20px;
    flex-direction: column; align-items: flex-start; justify-content: center;
}
.admin-logo-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(234,88,12,0.4);
}
.admin-logo-title {
    font-size: 14px; font-weight: 700; color: #f8fafc; line-height: 1.1;
    letter-spacing: -0.3px;
}
.admin-logo-sub {
    font-size: 10px; color: #64748b; line-height: 1; margin-top: 1px;
}

/* ── Nav Items ─────────────────────────────────────────────────── */
.admin-nav {
    display: flex; align-items: center; gap: 1px; flex: 1;
}
.admin-nav-item {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 11px; border-radius: 7px;
    font-size: 13px; font-weight: 500; color: #94a3b8;
    text-decoration: none; background: none; border: none;
    cursor: pointer; white-space: nowrap; font-family: inherit;
    transition: color 0.12s, background 0.12s;
    line-height: 1;
}
.admin-nav-item:hover {
    color: #f1f5f9; background: rgba(255,255,255,0.07);
}
.admin-nav-item.active {
    color: #fb923c;
    background: rgba(251,146,60,0.12);
}
.admin-nav-group:hover > .admin-nav-item {
    color: #f1f5f9; background: rgba(255,255,255,0.07);
}
.admin-nav-group:hover > .admin-nav-item.active,
.admin-nav-item.active:hover {
    color: #fb923c;
    background: rgba(251,146,60,0.15);
}

/* ── Dropdown ──────────────────────────────────────────────────── */
.admin-nav-group {
    position: relative;
}
.admin-dropdown {
    position: absolute; top: 100%; left: 0;
    padding-top: 8px;          /* visual gap via padding — keeps hover area solid */
    background: transparent;   /* outer wrapper is transparent */
    min-width: 210px;
    opacity: 0; visibility: hidden;
    transform: translateY(-6px) scale(0.98);
    transition: opacity 0.14s ease, transform 0.14s ease, visibility 0.14s;
    z-index: 300;
    pointer-events: none;
}
/* Inner card carries the visual styling */
.admin-dropdown-inner {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18), 0 4px 12px rgba(0,0,0,0.08);
    padding: 6px;
}
.admin-nav-group:hover .admin-dropdown,
.admin-nav-group.open .admin-dropdown {
    opacity: 1; visibility: visible;
    transform: translateY(0) scale(1);
    pointer-events: all;
}
.admin-dropdown a {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 11px; border-radius: 8px;
    font-size: 13px; font-weight: 500; color: #374151;
    text-decoration: none; transition: background 0.1s, color 0.1s;
}
.admin-dropdown a .dd-icon {
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: #f8fafc;
    transition: background 0.1s;
}
.admin-dropdown a:hover {
    background: #f8fafc; color: #111827;
}
.admin-dropdown a:hover .dd-icon { background: #f1f5f9; }
.admin-dropdown a.active {
    background: #fff7ed; color: #ea580c; font-weight: 600;
}
.admin-dropdown a.active .dd-icon { background: #ffedd5; }
.admin-dd-divider {
    height: 1px; background: #f1f5f9; margin: 4px 8px;
}
.admin-dd-label {
    font-size: 10px; font-weight: 700; color: #9ca3af;
    text-transform: uppercase; letter-spacing: 0.06em;
    padding: 6px 11px 3px; line-height: 1;
}

/* Chevron icon */
.admin-chevron {
    width: 12px; height: 12px; opacity: 0.6;
    transition: transform 0.14s ease;
}
.admin-nav-group:hover .admin-chevron { transform: rotate(180deg); opacity: 1; }

/* Separator in nav */
.admin-nav-sep {
    width: 1px; height: 18px; background: rgba(255,255,255,0.08);
    margin: 0 6px; flex-shrink: 0;
}

/* ── Right side: user info ─────────────────────────────────────── */
.admin-topnav-right {
    display: flex; align-items: center; gap: 10px;
    margin-left: auto; flex-shrink: 0;
}
.admin-user-group {
    display: flex; align-items: center; gap: 8px;
    padding: 4px 10px 4px 5px;
    border-radius: 8px; background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
}
.admin-user-avatar {
    width: 28px; height: 28px;
    background: linear-gradient(135deg, #ea580c, #f97316);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.admin-user-name {
    font-size: 12px; font-weight: 600; color: #e2e8f0; line-height: 1.1;
}
.admin-user-role {
    font-size: 10px; color: #64748b; line-height: 1;
}
.admin-divider-v {
    width: 1px; height: 20px; background: rgba(255,255,255,0.08);
}
.admin-logout-btn {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 7px;
    color: #64748b; text-decoration: none;
    transition: color 0.12s, background 0.12s;
}
.admin-logout-btn:hover { color: #f87171; background: rgba(248,113,113,0.1); }

/* ── Content Area ──────────────────────────────────────────────── */
.admin-content {
    padding-top: 56px; min-height: 100vh;
}
.admin-content-inner {
    max-width: 1400px; margin: 0 auto;
    padding: 28px 24px 48px;
}

/* ── Page Section Look ─────────────────────────────────────────── */
/* The card-based page partials look nicer on a wider canvas */
.admin-content-inner .bg-white {
    box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
}

/* ── Badge / stat tweaks for wider layout ──────────────────────── */
.admin-content-inner input[type="text"],
.admin-content-inner input[type="email"],
.admin-content-inner input[type="number"],
.admin-content-inner input[type="date"],
.admin-content-inner select,
.admin-content-inner textarea {
    font-size: 13px;
}

/* ── Responsive breakpoints ────────────────────────────────────── */
@media (max-width: 900px) {
    .admin-user-name, .admin-user-role { display: none; }
    .admin-user-group { padding: 4px; }
}
@media (max-width: 640px) {
    .admin-logo-sub { display: none; }
    .admin-topnav { padding: 0 12px; }
    .admin-content-inner { padding: 16px 12px 40px; }
    .admin-logo { margin-right: 8px; }
}

/* ── Sheet/modal overlay ────────────────────────────────────────── */
/* openSheet() still works — just needs backdrop */
#sheetOverlay { z-index: 400; }
#sheetPanel   { z-index: 401; }
</style>
<?php endif; ?>
</head>

<?php if ($isAdminPage): ?>
<!-- ════════════════════════════════════════════════════════════════
     ADMIN PORTAL LAYOUT
     ════════════════════════════════════════════════════════════════ -->
<body class="admin-portal font-sans">

    <!-- ── Top Navigation ── -->
    <header class="admin-topnav" role="banner">
        <div class="admin-topnav-inner">

            <!-- Logo -->
            <a href="app.php?page=admin-home" class="admin-logo" title="Karibu Admin Portal">
                <img src="/assets/karibu-logo.png" alt="Karibu Camps &amp; Lodges"
                     style="height:38px;width:auto;mix-blend-mode:screen;filter:brightness(1.1);">
            </a>

            <!-- ── Navigation ── -->
            <nav class="admin-nav" role="navigation" aria-label="Admin navigation">

                <!-- Overview -->
                <a href="app.php?page=admin-home"
                   class="admin-nav-item <?= $page === 'admin-home' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    Overview
                </a>

                <div class="admin-nav-sep"></div>

                <!-- Operations group -->
                <div class="admin-nav-group">
                    <button class="admin-nav-item <?= in_array($page, $opsPages) ? 'active' : '' ?>"
                            onclick="adminNavToggle(this)" aria-haspopup="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                        Operations
                        <svg class="admin-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="admin-dropdown" role="menu">
                      <div class="admin-dropdown-inner">
                        <a href="app.php?page=admin-orders" class="<?= $page === 'admin-orders' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">📋</span>
                            <div><div style="font-weight:600">All Orders</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">Cross-kitchen order view</div></div>
                        </a>
                        <a href="app.php?page=admin-reports" class="<?= $page === 'admin-reports' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">📈</span>
                            <div><div style="font-weight:600">Reports & Analytics</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">Usage, waste, summaries</div></div>
                        </a>
                      </div>
                    </div>
                </div>

                <!-- Content group -->
                <div class="admin-nav-group">
                    <button class="admin-nav-item <?= in_array($page, $cntPages) ? 'active' : '' ?>"
                            onclick="adminNavToggle(this)" aria-haspopup="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                        Content
                        <svg class="admin-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="admin-dropdown" role="menu">
                      <div class="admin-dropdown-inner">
                        <a href="app.php?page=admin-recipes" class="<?= $page === 'admin-recipes' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">📖</span>
                            <div><div style="font-weight:600">Recipe Library</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">All chefs · manage defaults</div></div>
                        </a>
                        <a href="app.php?page=admin-stock" class="<?= $page === 'admin-stock' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">🏪</span>
                            <div><div style="font-weight:600">Stock Control</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">Live inventory · inline edit</div></div>
                        </a>
                      </div>
                    </div>
                </div>

                <!-- Management group -->
                <div class="admin-nav-group">
                    <button class="admin-nav-item <?= in_array($page, $mgmtPages) ? 'active' : '' ?>"
                            onclick="adminNavToggle(this)" aria-haspopup="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Management
                        <svg class="admin-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="admin-dropdown" role="menu">
                      <div class="admin-dropdown-inner">
                        <div class="admin-dd-label">People</div>
                        <a href="app.php?page=admin-users" class="<?= $page === 'admin-users' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">👥</span>
                            <div><div style="font-weight:600">Users</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">Chefs, storekeepers, admins</div></div>
                        </a>
                        <a href="app.php?page=admin-camps" class="<?= $page === 'admin-camps' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">🏕️</span>
                            <div><div style="font-weight:600">Camps</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">Kitchen locations & codes</div></div>
                        </a>
                        <div class="admin-dd-divider"></div>
                        <div class="admin-dd-label">Catalogue</div>
                        <a href="app.php?page=admin-items" class="<?= $page === 'admin-items' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">📦</span>
                            <div><div style="font-weight:600">Items</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">Portions, weights, UOM</div></div>
                        </a>
                        <a href="app.php?page=admin-meal-types" class="<?= $page === 'admin-meal-types' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">🍽️</span>
                            <div><div style="font-weight:600">Meal Types</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">Breakfast, lunch, dinner…</div></div>
                        </a>
                        <div class="admin-dd-divider"></div>
                        <div class="admin-dd-label">Notifications</div>
                        <a href="app.php?page=admin-emails" class="<?= $page === 'admin-emails' ? 'active' : '' ?>" role="menuitem">
                            <span class="dd-icon">📧</span>
                            <div><div style="font-weight:600">Email Alerts</div><div style="font-size:11px;color:#9ca3af;margin-top:1px">External notification recipients</div></div>
                        </a>
                      </div>
                    </div>
                </div>

                <div class="admin-nav-sep"></div>

                <!-- Audit Log -->
                <a href="app.php?page=admin-audit"
                   class="admin-nav-item <?= $page === 'admin-audit' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    Audit Log
                </a>

                <!-- Recycle Bin -->
                <a href="app.php?page=admin-recycle"
                   class="admin-nav-item <?= $page === 'admin-recycle' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    Recycle Bin
                </a>

            </nav>

            <!-- ── Right: user + logout ── -->
            <div class="admin-topnav-right">
                <div class="admin-user-group">
                    <div class="admin-user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                    <div>
                        <div class="admin-user-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="admin-user-role"><?= ucfirst($user['role']) ?></div>
                    </div>
                </div>
                <div class="admin-divider-v"></div>
                <a href="logout.php" class="admin-logout-btn" title="Sign out">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                </a>
            </div>

        </div>
    </header>

    <!-- ── Page Content ── -->
    <main class="admin-content" role="main">
        <div class="admin-content-inner">
            <?php
            if (file_exists($pageFile)) {
                include $pageFile;
            } else {
                echo '<div class="text-center py-20"><p class="text-gray-400 text-sm">Page not found</p></div>';
            }
            ?>
        </div>
    </main>

    <script>
    // ── Dropdown click-toggle (for touch devices) ──────────────────
    function adminNavToggle(btn) {
        const group = btn.closest('.admin-nav-group');
        const isOpen = group.classList.contains('open');
        // Close all others
        document.querySelectorAll('.admin-nav-group.open').forEach(g => g.classList.remove('open'));
        if (!isOpen) group.classList.add('open');
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.admin-nav-group')) {
            document.querySelectorAll('.admin-nav-group.open').forEach(g => g.classList.remove('open'));
        }
    });
    // Escape key closes dropdowns
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.admin-nav-group.open').forEach(g => g.classList.remove('open'));
        }
    });
    </script>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════════════
     MOBILE APP LAYOUT (chefs, storekeepers, admin on non-portal pages)
     ════════════════════════════════════════════════════════════════ -->
<body class="bg-gray-50 font-sans">
    <!-- Top Bar -->
    <header class="bg-white border-b border-gray-200 px-4 h-14 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
            </div>
            <?php if ($kitchenName): ?>
                <span class="text-[10px] bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-semibold truncate max-w-[100px]"><?= htmlspecialchars($kitchenName) ?></span>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1.5">
                <div class="w-7 h-7 bg-<?= $roleColor ?>-100 rounded-full flex items-center justify-center">
                    <span class="text-<?= $roleColor ?>-700 font-semibold text-[10px]">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </span>
                </div>
                <div class="hidden sm:block">
                    <p class="text-xs font-medium text-gray-800"><?= htmlspecialchars($user['name']) ?></p>
                    <p class="text-[9px] text-gray-500"><?= ucfirst($user['role']) ?></p>
                </div>
            </div>
            <a href="logout.php" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Logout">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
            </a>
        </div>
    </header>

    <!-- Content -->
    <main class="pb-20 max-w-2xl lg:max-w-5xl mx-auto px-4 py-4 page-enter">
        <?php
        if (file_exists($pageFile)) {
            include $pageFile;
        } else {
            echo '<div class="text-center py-12"><p class="text-gray-500">Page not found</p></div>';
        }
        ?>
    </main>

    <!-- Mobile Bottom Nav -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
        <div class="flex justify-around items-center h-16 px-1">
            <?php if (isChef() || isAdmin()): ?>
                <a href="app.php?page=dashboard" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'dashboard' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span class="text-[9px] font-medium">Home</span>
                </a>
                <a href="app.php?page=orders" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'orders' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                    <span class="text-[9px] font-medium">Orders</span>
                </a>
                <a href="app.php?page=recipes" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'recipes' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                    <span class="text-[9px] font-medium">Recipes</span>
                </a>
                <a href="app.php?page=kitchen-inventory" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'kitchen-inventory' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m20 7-8.5 8.5-4-4L2 17"/><path d="M23 7h-6v6"/></svg>
                    <span class="text-[9px] font-medium">Stock</span>
                </a>
            <?php endif; ?>

            <?php if (isStorekeeper() && !isAdmin()): ?>
                <a href="app.php?page=store-dashboard" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'store-dashboard' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span class="text-[9px] font-medium">Home</span>
                </a>
                <a href="app.php?page=store-orders" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'store-orders' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    <span class="text-[9px] font-medium">Orders</span>
                </a>
                <a href="app.php?page=store-history" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'store-history' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    <span class="text-[9px] font-medium">History</span>
                </a>
                <a href="app.php?page=reports" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'reports' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                    <span class="text-[9px] font-medium">Reports</span>
                </a>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
                <a href="app.php?page=store-dashboard" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= in_array($page, ['store-dashboard','store-orders','store-inventory','store-history']) ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    <span class="text-[9px] font-medium">Store</span>
                </a>
                <a href="app.php?page=admin-home" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    <span class="text-[9px] font-medium">Admin</span>
                </a>
            <?php endif; ?>

            <a href="app.php?page=settings" class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'settings' ? 'text-gray-700' : 'text-gray-400' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="text-[9px] font-medium">Settings</span>
            </a>
        </div>
    </nav>

    <!-- PWA Install FAB -->
    <button id="pwaInstallFab" onclick="pwaInstall()" class="hidden fixed z-40 right-4 bottom-20 w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-full shadow-lg flex items-center justify-center animate-bounce hover:scale-110 active:scale-95 transition-transform" style="animation-duration:2s;animation-iteration-count:3">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
    </button>
    <button id="pwaInstallDismiss" onclick="event.stopPropagation();pwaHideFab()" class="hidden fixed z-50 right-3 bottom-[132px] w-6 h-6 bg-gray-700 text-white rounded-full flex items-center justify-center shadow-md hover:bg-gray-900 transition" title="Don't show again">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
    <div id="pwaInstallTooltip" class="hidden fixed z-40 right-20 bottom-[88px] bg-gray-900 text-white text-xs font-medium px-3 py-1.5 rounded-lg shadow-lg whitespace-nowrap">
        Install App
        <div class="absolute top-1/2 -right-1 -translate-y-1/2 w-2 h-2 bg-gray-900 rotate-45"></div>
    </div>

    <!-- iOS Install Modal -->
    <div id="pwaIOSModal" class="hidden fixed inset-0 z-50 flex items-end justify-center bg-black/40" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-t-2xl w-full max-w-lg px-5 py-6 space-y-4 animate-slideUp">
            <h3 class="text-base font-bold text-gray-900 text-center">Install Karibu Pantry</h3>
            <div class="space-y-3">
                <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>
                    </div>
                    <div><p class="text-sm font-semibold text-gray-800">1. Tap the Share button</p><p class="text-[10px] text-gray-500">The square with arrow at the bottom of Safari</p></div>
                </div>
                <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                    </div>
                    <div><p class="text-sm font-semibold text-gray-800">2. Tap "Add to Home Screen"</p><p class="text-[10px] text-gray-500">Scroll down in the share menu to find it</p></div>
                </div>
                <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div><p class="text-sm font-semibold text-gray-800">3. Tap "Add"</p><p class="text-[10px] text-gray-500">The app icon will appear on your home screen</p></div>
                </div>
            </div>
            <button onclick="document.getElementById('pwaIOSModal').classList.add('hidden')" class="w-full bg-gray-100 text-gray-700 py-2.5 rounded-xl text-sm font-semibold">Got it</button>
        </div>
    </div>

    <script>
    const pageNames = {
        'dashboard':'Dashboard','requisition':'Order','recipes':'Recipes','kitchen-inventory':'Kitchen Stock',
        'store-dashboard':'Store','store-orders':'Orders','store-inventory':'Inventory','store-history':'History',
        'settings':'Settings','review-supply':'Supply','day-close':'Close','reports':'Reports','menu-plan':'Plan'
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('nav a[href^="app.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (window.location.href.endsWith(href) || window.location.search === href.replace('app.php','')) return;
                e.preventDefault();
                document.querySelectorAll('nav a').forEach(a => a.style.color = '');
                const isStore = href.includes('store');
                this.style.color = isStore ? '#16a34a' : '#ea580c';
                const main = document.querySelector('main');
                main.classList.remove('page-enter');
                main.classList.add('page-exit');
                setTimeout(() => {
                    const pageName = href.match(/page=([^&]+)/);
                    const label = pageName ? (pageNames[pageName[1]] || 'Loading') : 'Loading';
                    const loader = document.createElement('div');
                    loader.className = 'page-loader' + (isStore ? ' store' : '');
                    loader.innerHTML = '<div class="spinner"></div><div class="label">Loading ' + label + '...</div>';
                    document.body.appendChild(loader);
                    setTimeout(() => { window.location.href = href; }, 50);
                }, 140);
            });
        });
    });

    // PWA Install
    let deferredPrompt;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);

    function pwaShowFab() {
        if (localStorage.getItem('karibu_install_dismissed')) return;
        const fab = document.getElementById('pwaInstallFab');
        const tooltip = document.getElementById('pwaInstallTooltip');
        const dismiss = document.getElementById('pwaInstallDismiss');
        fab.classList.remove('hidden'); fab.classList.add('flex');
        if (dismiss) dismiss.classList.remove('hidden');
        tooltip.classList.remove('hidden');
        setTimeout(() => tooltip.classList.add('hidden'), 4000);
    }
    function pwaHideFab() {
        localStorage.setItem('karibu_install_dismissed','1');
        const fab = document.getElementById('pwaInstallFab');
        const tooltip = document.getElementById('pwaInstallTooltip');
        const dismiss = document.getElementById('pwaInstallDismiss');
        fab.classList.add('hidden'); fab.classList.remove('flex');
        tooltip.classList.add('hidden');
        if (dismiss) dismiss.classList.add('hidden');
    }
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); deferredPrompt = e;
        if (!isStandalone) pwaShowFab();
    });
    if (isIOS && !isStandalone) pwaShowFab();
    function pwaInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choice) => {
                if (choice.outcome === 'accepted') {
                    document.getElementById('pwaInstallFab').classList.add('hidden');
                    document.getElementById('pwaInstallFab').classList.remove('flex');
                }
                deferredPrompt = null;
            });
        } else if (isIOS) {
            document.getElementById('pwaIOSModal').classList.remove('hidden');
        }
    }
    window.addEventListener('appinstalled', () => {
        document.getElementById('pwaInstallFab').classList.add('hidden');
        document.getElementById('pwaInstallFab').classList.remove('flex');
        const dismiss = document.getElementById('pwaInstallDismiss');
        if (dismiss) dismiss.classList.add('hidden');
        deferredPrompt = null;
    });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js').then(reg => {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'push-notification') { }
            });
            setTimeout(() => {
                const isiOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
                const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                if (isiOS && !standalone) return;
                if ('Notification' in window && 'PushManager' in window && Notification.permission === 'default') {
                    const dismissed = localStorage.getItem('karibu_push_dismissed');
                    if (!dismissed) showPushBanner();
                }
            }, 2000);
        }).catch(() => {});
    }

    function showPushBanner() {
        const banner = document.createElement('div');
        banner.id = 'pushPromptBanner';
        banner.className = 'fixed top-0 left-0 right-0 z-[250] animate-fade-in';
        banner.innerHTML = `<div class="mx-3 mt-3 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-xl p-4"><div class="flex items-start gap-3"><div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></div><div class="flex-1"><p class="text-white font-semibold text-sm">Enable Notifications</p><p class="text-white/80 text-xs mt-0.5">Get instant alerts when orders are submitted, fulfilled, or received.</p></div><div class="flex gap-2 mt-3"><button onclick="pushRequestPermission()" class="bg-white text-green-700 font-semibold text-xs px-3 py-1.5 rounded-lg">Enable</button><button onclick="pushDismiss()" class="text-white/70 text-xs px-2 py-1.5 rounded-lg">Not now</button></div></div></div>`;
        document.body.appendChild(banner);
    }
    function pushDismiss() {
        localStorage.setItem('karibu_push_dismissed','1');
        const b = document.getElementById('pushPromptBanner');
        if (b) b.remove();
    }
    async function pushRequestPermission() {
        pushDismiss();
        const permission = await Notification.requestPermission();
        if (permission === 'granted') showToast('Notifications enabled!', 'success');
    }
    </script>

<?php endif; ?>
</body>
</html>
