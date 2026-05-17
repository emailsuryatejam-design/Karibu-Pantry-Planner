<?php
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
$db = getDB();

// Quick stats
$stats = [];
try {
    $stats['camps']   = $db->query("SELECT COUNT(*) FROM kitchens WHERE is_active=1")->fetchColumn();
    $stats['users']   = $db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
    $stats['items']   = $db->query("SELECT COUNT(*) FROM items WHERE is_active=1")->fetchColumn();
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM requisitions WHERE status IN ('submitted','processing')")->fetchColumn();
    $stats['chefs']   = $db->query("SELECT COUNT(*) FROM users WHERE role='chef' AND is_active=1")->fetchColumn();
    $stats['recipes'] = $db->query("SELECT COUNT(*) FROM recipes")->fetchColumn();
} catch(Exception $e) {}
?>

<div class="mb-5">
    <h1 class="text-lg font-bold text-gray-900">Admin Overview</h1>
    <p class="text-xs text-gray-400 mt-0.5">Karibu Pantry Planner — System Status</p>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-2 gap-3 mb-5">
    <a href="app.php?page=admin-camps" class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm active:bg-gray-50 transition">
        <div class="text-2xl font-bold text-slate-800"><?= $stats['camps'] ?? 0 ?></div>
        <div class="text-xs text-gray-400 mt-0.5">Active Camps</div>
        <div class="text-[10px] text-slate-400 mt-2 flex items-center gap-1">🏕️ Tap to manage</div>
    </a>
    <a href="app.php?page=admin-users" class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm active:bg-gray-50 transition">
        <div class="text-2xl font-bold text-slate-800"><?= $stats['users'] ?? 0 ?></div>
        <div class="text-xs text-gray-400 mt-0.5">Active Users</div>
        <div class="text-[10px] text-blue-400 mt-2 flex items-center gap-1">👥 <?= $stats['chefs'] ?? 0 ?> chefs</div>
    </a>
    <a href="app.php?page=admin-items" class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm active:bg-gray-50 transition">
        <div class="text-2xl font-bold text-slate-800"><?= $stats['items'] ?? 0 ?></div>
        <div class="text-xs text-gray-400 mt-0.5">Pantry Items</div>
        <div class="text-[10px] text-orange-400 mt-2 flex items-center gap-1">📦 Tap to manage</div>
    </a>
    <a href="app.php?page=store-dashboard" class="bg-white rounded-xl border border-<?= ($stats['pending'] ?? 0) > 0 ? 'orange-200' : 'gray-100' ?> p-4 shadow-sm active:bg-gray-50 transition">
        <div class="text-2xl font-bold text-<?= ($stats['pending'] ?? 0) > 0 ? 'orange-600' : 'slate-800' ?>"><?= $stats['pending'] ?? 0 ?></div>
        <div class="text-xs text-gray-400 mt-0.5">Pending Orders</div>
        <div class="text-[10px] text-<?= ($stats['pending'] ?? 0) > 0 ? 'orange-500' : 'gray-400' ?> mt-2">
            <?= ($stats['pending'] ?? 0) > 0 ? '⚠️ Needs attention' : '✅ All clear' ?>
        </div>
    </a>
</div>

<!-- Quick links -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
    <?php
    $links = [
        ['page' => 'admin-users',      'icon' => '👥', 'label' => 'Manage Users',       'sub' => 'Create, edit, assign chefs to camps'],
        ['page' => 'admin-camps',      'icon' => '🏕️', 'label' => 'Manage Camps',       'sub' => 'Kitchen locations and settings'],
        ['page' => 'admin-items',      'icon' => '📦', 'label' => 'Pantry Items',        'sub' => 'Items, portions, order modes'],
        ['page' => 'admin-meal-types', 'icon' => '🍽️', 'label' => 'Meal Types',         'sub' => 'Breakfast, Lunch, Dinner etc.'],
        ['page' => 'admin-emails',     'icon' => '📧', 'label' => 'Email Notifications', 'sub' => 'Who gets PDF order alerts'],
        ['page' => 'reports',          'icon' => '📈', 'label' => 'Reports',             'sub' => 'Usage and order history'],
    ];
    foreach ($links as $l):
    ?>
    <a href="app.php?page=<?= $l['page'] ?>" class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 active:bg-gray-100 transition">
        <span class="text-lg w-7 text-center"><?= $l['icon'] ?></span>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-800"><?= $l['label'] ?></p>
            <p class="text-[10px] text-gray-400"><?= $l['sub'] ?></p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-300 shrink-0"><path d="m9 18 6-6-6-6"/></svg>
    </a>
    <?php endforeach; ?>
</div>
