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
$storePages = ['store-dashboard', 'store-orders', 'store-history', 'settings'];
$adminPages = array_unique(array_merge($chefPages, $storePages, ['admin-items', 'admin-kitchens', 'admin-req-types']));

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
    <script src="assets/app.js"></script>
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
                   class="flex flex-col items-center justify-center gap-0.5 px-1 py-1 rounded-lg min-w-[48px] <?= in_array($page, ['admin-items','admin-kitchens','admin-req-types']) ? 'text-orange-600' : 'text-gray-400' ?>">
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

    <!-- PWA Install Banner -->
    <div id="pwaInstallBanner" class="hidden fixed top-14 left-0 right-0 bg-orange-500 text-white px-4 py-2.5 z-30 flex items-center justify-between">
        <span class="text-xs font-medium">Install Karibu Pantry for quick access</span>
        <div class="flex gap-2">
            <button onclick="pwaInstall()" class="bg-white text-orange-600 px-3 py-1 rounded-lg text-xs font-semibold">Install</button>
            <button onclick="document.getElementById('pwaInstallBanner').classList.add('hidden')" class="text-white/80 text-xs">Later</button>
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
                    <div><div class="text-sm font-semibold text-gray-800">Kitchen Management</div><div class="text-[10px] text-gray-400">Manage 6 kitchens</div></div>
                </a>
                <a href="app.php?page=admin-req-types" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                    </div>
                    <div><div class="text-sm font-semibold text-gray-800">Requisition Types</div><div class="text-[10px] text-gray-400">Meal types chefs can select</div></div>
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
        'review-supply': 'Supply', 'day-close': 'Close', 'reports': 'Reports',
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
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        document.getElementById('pwaInstallBanner').classList.remove('hidden');
    });

    function pwaInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(() => {
                document.getElementById('pwaInstallBanner').classList.add('hidden');
                deferredPrompt = null;
            });
        }
    }

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
        }).catch(() => {});
    }
    </script>
</body>
</html>
