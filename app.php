<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();

// Default landing pages
$defaultPage = 'dashboard';
if (isStorekeeper()) $defaultPage = 'store-dashboard';

$page = $_GET['page'] ?? $defaultPage;

// Valid pages per role
$chefPages = ['dashboard', 'requisition', 'review-supply', 'day-close', 'menu-plan', 'daily-groceries', 'recipes', 'reports', 'settings'];
$storePages = ['store-dashboard', 'store-orders', 'store-history', 'reports', 'settings'];
$adminPages = array_unique(array_merge($chefPages, $storePages, ['admin-items', 'admin-kitchens', 'admin-req-types', 'admin-set-menus']));

$allowedPages = isAdmin() ? $adminPages : (isChef() ? $chefPages : $storePages);
if (!in_array($page, $allowedPages)) {
    $page = $allowedPages[0];
}

// Page titles
$pageTitles = [
    'dashboard' => 'Dashboard',
    'requisition' => 'Requisition',
    'review-supply' => 'Review Supply',
    'day-close' => 'Day Close',
    'menu-plan' => 'Menu Plan',
    'daily-groceries' => 'Daily Groceries',
    'recipes' => 'Recipes',
    'reports' => 'Reports',
    'store-dashboard' => 'Store Dashboard',
    'store-orders' => 'Store Orders',
    'store-history' => 'History',
    'admin-items' => 'Items',
    'admin-kitchens' => 'Kitchens',
    'admin-req-types' => 'Req Types',
    'admin-set-menus' => 'Set Menus',
    'settings' => 'Settings',
];
$pageTitle = $pageTitles[$page] ?? 'Pantry Planner';

$kitchenName = $user['kitchen_name'] ?? '';
$roleColor = isStorekeeper() ? 'green' : 'orange';
$isAdminRole = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ea580c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <title><?= $pageTitle ?> — Karibu Pantry Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</head>
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
    <main class="pb-20 max-w-2xl mx-auto px-4 py-4 page-enter">
        <?php
        $pageFile = __DIR__ . '/pages/' . $page . '.php';
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
                <!-- Chef: Dashboard -->
                <a href="app.php?page=dashboard"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'dashboard' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span class="text-[9px] font-medium">Home</span>
                </a>
                <!-- Chef: Requisition -->
                <a href="app.php?page=requisition"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'requisition' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                    <span class="text-[9px] font-medium">Order</span>
                </a>
                <!-- Chef: Recipes -->
                <a href="app.php?page=recipes"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'recipes' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                    <span class="text-[9px] font-medium">Recipes</span>
                </a>
            <?php endif; ?>

            <?php if (isStorekeeper() && !isAdmin()): ?>
                <!-- Store: Dashboard -->
                <a href="app.php?page=store-dashboard"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'store-dashboard' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span class="text-[9px] font-medium">Home</span>
                </a>
                <!-- Store: Orders -->
                <a href="app.php?page=store-orders"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'store-orders' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    <span class="text-[9px] font-medium">Orders</span>
                </a>
                <!-- Store: History -->
                <a href="app.php?page=store-history"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'store-history' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    <span class="text-[9px] font-medium">History</span>
                </a>
                <!-- Store: Reports -->
                <a href="app.php?page=reports"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'reports' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                    <span class="text-[9px] font-medium">Reports</span>
                </a>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
                <!-- Admin: Store Orders -->
                <a href="app.php?page=store-dashboard"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= in_array($page, ['store-dashboard','store-orders','store-history']) ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    <span class="text-[9px] font-medium">Store</span>
                </a>
                <!-- Admin: Admin menu -->
                <a href="#" onclick="showAdminMenu();return false"
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= in_array($page, ['admin-items','admin-kitchens','admin-req-types','admin-set-menus']) ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                    <span class="text-[9px] font-medium">Admin</span>
                </a>
            <?php endif; ?>

            <!-- Settings (all roles) -->
            <a href="app.php?page=settings"
               class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= $page === 'settings' ? 'text-gray-700' : 'text-gray-400' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="text-[9px] font-medium">Settings</span>
            </a>
        </div>
    </nav>

    <!-- PWA Install Floating Button -->
    <button id="pwaInstallFab" onclick="pwaInstall()" class="hidden fixed z-40 right-4 bottom-20 w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-full shadow-lg flex items-center justify-center animate-bounce hover:scale-110 active:scale-95 transition-transform"
        style="animation-duration:2s;animation-iteration-count:3">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
    </button>
    <!-- Dismiss × for FAB -->
    <button id="pwaInstallDismiss" onclick="event.stopPropagation();pwaHideFab()" class="hidden fixed z-50 right-3 bottom-[132px] w-6 h-6 bg-gray-700 text-white rounded-full flex items-center justify-center shadow-md hover:bg-gray-900 transition" title="Don't show again">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
    <!-- Tooltip for FAB -->
    <div id="pwaInstallTooltip" class="hidden fixed z-40 right-20 bottom-[88px] bg-gray-900 text-white text-xs font-medium px-3 py-1.5 rounded-lg shadow-lg whitespace-nowrap">
        Install App
        <div class="absolute top-1/2 -right-1 -translate-y-1/2 w-2 h-2 bg-gray-900 rotate-45"></div>
    </div>

    <!-- iOS Install Instructions Modal -->
    <div id="pwaIOSModal" class="hidden fixed inset-0 z-50 flex items-end justify-center bg-black/40" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-t-2xl w-full max-w-lg px-5 py-6 space-y-4 animate-slideUp">
            <h3 class="text-base font-bold text-gray-900 text-center">Install Karibu Pantry</h3>
            <div class="space-y-3">
                <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">1. Tap the Share button</p>
                        <p class="text-[10px] text-gray-500">The square with arrow at the bottom of Safari</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">2. Tap "Add to Home Screen"</p>
                        <p class="text-[10px] text-gray-500">Scroll down in the share menu to find it</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">3. Tap "Add"</p>
                        <p class="text-[10px] text-gray-500">The app icon will appear on your home screen</p>
                    </div>
                </div>
            </div>
            <button onclick="document.getElementById('pwaIOSModal').classList.add('hidden')" class="w-full bg-gray-100 text-gray-700 py-2.5 rounded-xl text-sm font-semibold">Got it</button>
        </div>
    </div>

    <script>
    // Admin menu bottom sheet
    function showAdminMenu() {
        const html = `<div class="p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Admin</h3>
            <div class="space-y-2">
                <a href="app.php?page=admin-items" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    </div>
                    <div><div class="text-sm font-semibold text-gray-800">Item Management</div><div class="text-[10px] text-gray-400">Portion weights, categories, order modes</div></div>
                </a>
                <a href="app.php?page=admin-kitchens" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/><path d="m3 9 2.45-4.9A2 2 0 0 1 7.24 3h9.52a2 2 0 0 1 1.8 1.1L21 9"/><path d="M12 3v6"/></svg>
                    </div>
                    <div><div class="text-sm font-semibold text-gray-800">Kitchen Management</div><div class="text-[10px] text-gray-400">Manage <?php $kc = getDB()->query("SELECT COUNT(*) FROM kitchens")->fetchColumn(); echo $kc; ?> kitchen<?php echo $kc != 1 ? 's' : ''; ?></div></div>
                </a>
                <a href="app.php?page=admin-req-types" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                    </div>
                    <div><div class="text-sm font-semibold text-gray-800">Requisition Types</div><div class="text-[10px] text-gray-400">Meal types chefs can select</div></div>
                </a>
                <a href="app.php?page=admin-set-menus" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                    <div class="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                    </div>
                    <div><div class="text-sm font-semibold text-gray-800">Weekly Set Menu</div><div class="text-[10px] text-gray-400">Rotational dishes for each day</div></div>
                </a>
                <a href="app.php?page=settings" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                    <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div><div class="text-sm font-semibold text-gray-800">User Management</div><div class="text-[10px] text-gray-400">Roles, kitchens, access</div></div>
                </a>
                <a href="app.php?page=reports" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                    <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                    </div>
                    <div><div class="text-sm font-semibold text-gray-800">Reports</div><div class="text-[10px] text-gray-400">Analytics and summaries</div></div>
                </a>
            </div>
        </div>`;
        openSheet(html);
    }

    // Page transition
    const pageNames = {
        'dashboard': 'Dashboard', 'requisition': 'Order', 'recipes': 'Recipes',
        'store-dashboard': 'Store', 'store-orders': 'Orders', 'store-history': 'History',
        'settings': 'Settings', 'admin-items': 'Items', 'admin-kitchens': 'Kitchens', 'admin-req-types': 'Req Types',
        'admin-set-menus': 'Set Menus', 'review-supply': 'Supply', 'day-close': 'Close', 'reports': 'Reports',
        'menu-plan': 'Plan', 'daily-groceries': 'Groceries'
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('nav a[href^="app.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (window.location.href.endsWith(href) || window.location.search === href.replace('app.php', '')) return;
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
        fab.classList.remove('hidden');
        fab.classList.add('flex');
        if (dismiss) dismiss.classList.remove('hidden');
        // Show tooltip for 4 seconds then hide
        tooltip.classList.remove('hidden');
        setTimeout(() => tooltip.classList.add('hidden'), 4000);
    }

    function pwaHideFab() {
        localStorage.setItem('karibu_install_dismissed', '1');
        const fab = document.getElementById('pwaInstallFab');
        const tooltip = document.getElementById('pwaInstallTooltip');
        const dismiss = document.getElementById('pwaInstallDismiss');
        fab.classList.add('hidden');
        fab.classList.remove('flex');
        tooltip.classList.add('hidden');
        if (dismiss) dismiss.classList.add('hidden');
    }

    // Android/Chrome: capture beforeinstallprompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (!isStandalone) pwaShowFab();
    });

    // iOS: show fab if not installed
    if (isIOS && !isStandalone) pwaShowFab();

    function pwaInstall() {
        if (deferredPrompt) {
            // Android/Chrome
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choice) => {
                if (choice.outcome === 'accepted') {
                    document.getElementById('pwaInstallFab').classList.add('hidden');
                    document.getElementById('pwaInstallFab').classList.remove('flex');
                }
                deferredPrompt = null;
            });
        } else if (isIOS) {
            // iOS: show instruction modal
            document.getElementById('pwaIOSModal').classList.remove('hidden');
        }
    }

    // Hide FAB once app is installed
    window.addEventListener('appinstalled', () => {
        document.getElementById('pwaInstallFab').classList.add('hidden');
        document.getElementById('pwaInstallFab').classList.remove('flex');
        const dismiss = document.getElementById('pwaInstallDismiss');
        if (dismiss) dismiss.classList.add('hidden');
        deferredPrompt = null;
    });

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js').then(reg => {
            // Listen for push messages forwarded from service worker
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'push-notification') {
                    const d = event.data.payload;
                    voice.say(d.body || d.title || 'New notification', 'high');
                }
            });

            // Auto-prompt for push notifications if never asked
            setTimeout(() => {
                const isiOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
                const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

                // On iOS, only show push prompt if running as installed PWA
                if (isiOS && !standalone) return;

                if ('Notification' in window && 'PushManager' in window && Notification.permission === 'default') {
                    const dismissed = localStorage.getItem('karibu_push_dismissed');
                    if (!dismissed) {
                        showPushBanner();
                    }
                }
            }, 2000);
        }).catch(() => {});
    }

    function showPushBanner() {
        const banner = document.createElement('div');
        banner.id = 'pushPromptBanner';
        banner.className = 'fixed top-0 left-0 right-0 z-[250] animate-fade-in';
        banner.innerHTML = `
            <div class="mx-3 mt-3 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-xl p-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-white font-semibold text-sm">Enable Notifications</p>
                        <p class="text-white/80 text-xs mt-0.5">Get instant alerts when orders are submitted, fulfilled, or received.</p>
                    </div>
                    <button onclick="dismissPushBanner()" class="text-white/60 hover:text-white p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                <div class="flex gap-2 mt-3">
                    <button onclick="enablePushFromBanner()" class="flex-1 bg-white text-green-700 font-semibold text-sm py-2 rounded-xl hover:bg-green-50 transition">
                        Enable Now
                    </button>
                    <button onclick="dismissPushBanner()" class="px-4 text-white/70 text-sm font-medium hover:text-white transition">
                        Later
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(banner);
    }

    async function enablePushFromBanner() {
        const banner = document.getElementById('pushPromptBanner');
        if (banner) banner.remove();
        localStorage.setItem('karibu_push_dismissed', '1');
        const ok = await pushSubscribe();
        if (ok) {
            showToast('Notifications enabled!', 'success');
        }
    }

    function dismissPushBanner() {
        const banner = document.getElementById('pushPromptBanner');
        if (banner) {
            banner.classList.add('animate-fade-out');
            setTimeout(() => banner.remove(), 300);
        }
        localStorage.setItem('karibu_push_dismissed', '1');
    }
    </script>
</body>
</html>
