<!-- Settings Page -->
<?php $user = currentUser(); ?>
<div class="space-y-4">

    <!-- Profile Card -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-14 h-14 bg-<?= isChef() ? 'orange' : (isStorekeeper() ? 'green' : 'blue') ?>-100 rounded-full flex items-center justify-center">
                <span class="text-<?= isChef() ? 'orange' : (isStorekeeper() ? 'green' : 'blue') ?>-700 font-bold text-xl">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </span>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="text-sm text-gray-500"><?= ucfirst($user['role']) ?></p>
            </div>
        </div>

        <div class="space-y-3 text-sm">
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Username</span>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Role</span>
                <span class="font-medium text-gray-800"><?= ucfirst($user['role']) ?></span>
            </div>
            <?php if (!empty($user['camp_name'])): ?>
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Camp</span>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($user['camp_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- App Info -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="font-semibold text-sm text-gray-800 mb-3">About</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">App</span>
                <span class="font-medium text-gray-800">Karibu Pantry Planner</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Version</span>
                <span class="font-medium text-gray-800">1.0.0</span>
            </div>
        </div>
    </div>

    <!-- Logout -->
    <a href="logout.php"
       class="block w-full py-3.5 bg-red-50 text-red-600 text-sm font-semibold rounded-xl text-center active:bg-red-100 transition">
        Log Out
    </a>

</div>
