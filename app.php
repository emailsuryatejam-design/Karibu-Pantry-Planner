<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
$page = $_GET['page'] ?? (isStorekeeper() ? 'store-orders' : 'menu-plan');

// Valid pages per role
$chefPages = ['menu-plan', 'daily-groceries', 'recipes', 'settings'];
$storePages = ['store-orders', 'settings'];
$adminPages = ['menu-plan', 'daily-groceries', 'recipes', 'store-orders', 'settings'];

$allowedPages = isAdmin() ? $adminPages : (isChef() ? $chefPages : $storePages);
if (!in_array($page, $allowedPages)) {
    $page = $allowedPages[0];
}

// Page titles
$pageTitles = [
    'menu-plan' => 'Menu Plan',
    'daily-groceries' => 'Daily Groceries',
    'recipes' => 'Recipes',
    'store-orders' => 'Store Orders',
    'settings' => 'Settings',
];
$pageTitle = $pageTitles[$page] ?? 'Pantry Planner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
            </div>
            <span class="text-sm font-semibold text-gray-800 hidden sm:block">Pantry Planner</span>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-<?= isChef() ? 'orange' : 'green' ?>-100 rounded-full flex items-center justify-center">
                    <span class="text-<?= isChef() ? 'orange' : 'green' ?>-700 font-semibold text-xs">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </span>
                </div>
                <div class="hidden sm:block">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user['name']) ?></p>
                    <p class="text-[10px] text-gray-500"><?= ucfirst($user['role']) ?></p>
                </div>
            </div>
            <a href="logout.php" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Logout">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
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
                <a href="app.php?page=menu-plan"
                   class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 rounded-lg min-w-[52px] <?= $page === 'menu-plan' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
                    <span class="text-[10px] font-medium">Plan</span>
                </a>
                <a href="app.php?page=daily-groceries"
                   class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 rounded-lg min-w-[52px] <?= $page === 'daily-groceries' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                    <span class="text-[10px] font-medium">Groceries</span>
                </a>
<a href="app.php?page=recipes"
                   class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 rounded-lg min-w-[52px] <?= $page === 'recipes' ? 'text-orange-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                    <span class="text-[10px] font-medium">Recipes</span>
                </a>
            <?php endif; ?>

            <?php if (isStorekeeper() || isAdmin()): ?>
                <a href="app.php?page=store-orders"
                   class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 rounded-lg min-w-[52px] <?= $page === 'store-orders' ? 'text-green-600' : 'text-gray-400' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    <span class="text-[10px] font-medium">Orders</span>
                </a>
            <?php endif; ?>

            <a href="app.php?page=settings"
               class="flex flex-col items-center justify-center gap-0.5 px-2 py-1 rounded-lg min-w-[52px] <?= $page === 'settings' ? 'text-gray-700' : 'text-gray-400' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="text-[10px] font-medium">Settings</span>
            </a>
        </div>
    </nav>

    <!-- Page transition script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const pageNames = {
            'menu-plan': 'Menu Plan',
            'daily-groceries': 'Groceries',
            'recipes': 'Recipes',
            'store-orders': 'Orders',
            'settings': 'Settings'
        };

        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                // Skip if already on this page
                if (window.location.href.endsWith(href) || window.location.search === href.replace('app.php', '')) return;

                e.preventDefault();

                // Highlight tapped tab immediately
                const isStore = href.includes('store-orders');
                document.querySelectorAll('nav a').forEach(a => a.style.color = '');
                this.style.color = isStore ? '#16a34a' : '#ea580c';

                // Fade out content
                const main = document.querySelector('main');
                main.classList.remove('page-enter');
                main.classList.add('page-exit');

                // Show loading spinner after content fades
                setTimeout(() => {
                    const pageName = href.match(/page=([^&]+)/);
                    const label = pageName ? (pageNames[pageName[1]] || 'Loading') : 'Loading';

                    const loader = document.createElement('div');
                    loader.className = 'page-loader' + (isStore ? ' store' : '');
                    loader.innerHTML = '<div class="spinner"></div><div class="label">Loading ' + label + '...</div>';
                    document.body.appendChild(loader);

                    // Navigate after spinner is visible
                    setTimeout(() => {
                        window.location.href = href;
                    }, 50);
                }, 140);
            });
        });
    });
    </script>
</body>
</html>
