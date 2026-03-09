<?php
/**
 * Karibu Pantry Planner — Chef Dashboard
 */
$user = currentUser();
$kitchenName = $user['kitchen_name'] ?? 'No Kitchen';
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<!-- Greeting -->
<div class="mb-4">
    <h2 class="text-lg font-bold text-gray-800">Hello, <?= htmlspecialchars($user['name']) ?></h2>
    <p class="text-xs text-gray-500"><?= htmlspecialchars($kitchenName) ?> &mdash; <?= date('l, d M Y') ?></p>
</div>

<!-- Stats Cards -->
<div class="flex gap-3 overflow-x-auto pb-2 mb-4" id="dbStats">
    <div class="min-w-[120px] bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="dbStatSessions">—</div>
        <div class="text-[10px] opacity-80 font-medium">Active Requisitions</div>
    </div>
    <div class="min-w-[120px] bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="dbStatAwaiting">—</div>
        <div class="text-[10px] opacity-80 font-medium">Awaiting Supply</div>
    </div>
    <div class="min-w-[120px] bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="dbStatReceive">—</div>
        <div class="text-[10px] opacity-80 font-medium">Ready to Close</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-2 gap-3 mb-4">
    <a href="app.php?page=requisition" class="bg-white border border-gray-200 rounded-xl p-4 hover:border-orange-300 hover:bg-orange-50 transition group">
        <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center mb-2 group-hover:bg-orange-200 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
        </div>
        <div class="text-sm font-semibold text-gray-800">New Requisition</div>
        <div class="text-[10px] text-gray-400">Order items for kitchen</div>
    </a>
    <a href="app.php?page=store-history" class="bg-white border border-gray-200 rounded-xl p-4 hover:border-green-300 hover:bg-green-50 transition group">
        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center mb-2 group-hover:bg-green-200 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
        </div>
        <div class="text-sm font-semibold text-gray-800">Order History</div>
        <div class="text-[10px] text-gray-400">Past orders & records</div>
    </a>
    <a href="app.php?page=day-close" class="bg-white border border-gray-200 rounded-xl p-4 hover:border-blue-300 hover:bg-blue-50 transition group">
        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mb-2 group-hover:bg-blue-200 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        </div>
        <div class="text-sm font-semibold text-gray-800">Day Close</div>
        <div class="text-[10px] text-gray-400">End of day closeout</div>
    </a>
    <a href="app.php?page=reports" class="bg-white border border-gray-200 rounded-xl p-4 hover:border-purple-300 hover:bg-purple-50 transition group">
        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center mb-2 group-hover:bg-purple-200 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
        </div>
        <div class="text-sm font-semibold text-gray-800">Reports</div>
        <div class="text-[10px] text-gray-400">View analytics</div>
    </a>
</div>

<!-- Today's Requisitions -->
<div class="mb-3">
    <h3 class="text-sm font-semibold text-gray-700 mb-2">Today's Requisitions</h3>
    <div id="dbSessionList" class="space-y-2">
        <div class="text-center py-6 text-xs text-gray-400">Loading...</div>
    </div>
</div>

<script>
const DB_KITCHEN_ID = <?= (int)$kitchenId ?>;

dbLoadStats();
dbLoadSessions();

// Welcome voice on dashboard load
voice.welcome('<?= addslashes($user['name']) ?>', '<?= addslashes($user['role']) ?>');

async function dbLoadStats() {
    try {
        const data = await api(`api/requisitions.php?action=dashboard_stats&kitchen_id=${DB_KITCHEN_ID}`);
        const s = data.stats || {};
        document.getElementById('dbStatSessions').textContent = s.active_sessions || 0;
        document.getElementById('dbStatAwaiting').textContent = s.awaiting_supply || 0;
        document.getElementById('dbStatReceive').textContent = (s.ready_close || 0);
    } catch(e) {}
}

async function dbLoadSessions() {
    try {
        const data = await api(`api/requisitions.php?action=day_summary&date=${todayStr()}&kitchen_id=${DB_KITCHEN_ID}`);
        const reqs = data.requisitions || [];
        const container = document.getElementById('dbSessionList');

        // Filter out empty drafts (0 items)
        const visibleReqs = reqs.filter(r => !(r.status === 'draft' && parseInt(r.line_count) === 0));

        if (visibleReqs.length === 0) {
            container.innerHTML = `<div class="text-center py-6">
                <p class="text-xs text-gray-400 mb-2">No requisitions today</p>
                <a href="app.php?page=requisition" class="text-xs text-orange-500 font-semibold hover:text-orange-600">+ Create Requisition</a>
            </div>`;
            return;
        }

        const statusColors = {
            draft: 'bg-gray-100 text-gray-700',
            submitted: 'bg-blue-100 text-blue-700',
            processing: 'bg-amber-100 text-amber-700',
            fulfilled: 'bg-green-100 text-green-700',
            received: 'bg-green-100 text-green-700',
            closed: 'bg-gray-200 text-gray-500'
        };

        let html = '';
        visibleReqs.forEach(r => {
            const color = statusColors[r.status] || 'bg-gray-100 text-gray-700';
            html += `<a href="app.php?page=requisition" class="block bg-white border border-gray-200 rounded-xl px-4 py-3 hover:border-orange-200 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">${reqLabel(r)}</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[10px] text-gray-400">${r.line_count || 0} items</span>
                            <span class="text-[10px] text-gray-400">${parseFloat(r.total_kg || 0).toFixed(1)} kg</span>
                        </div>
                    </div>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${color}">${r.status}</span>
                </div>
            </a>`;
        });
        container.innerHTML = html;
    } catch(e) {
        document.getElementById('dbSessionList').innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}
</script>
