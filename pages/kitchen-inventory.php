<?php
/**
 * Karibu Pantry Planner — Kitchen Inventory
 * Shows what items the kitchen has received from recent requisitions,
 * usage, and allows stock adjustments for discrepancies.
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Kitchen Stock</h2>
<p class="text-xs text-gray-500 mb-3">Items received from recent orders &amp; current stock levels</p>

<!-- Search -->
<div class="relative mb-3">
    <input type="text" id="kiSearch" placeholder="Search items..." oninput="kiLoad()"
        class="w-full text-sm border border-gray-200 rounded-xl px-4 py-2.5 pl-10 bg-white focus:outline-none focus:ring-2 focus:ring-orange-200">
    <svg class="absolute left-3 top-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
</div>

<!-- Quick Filters -->
<div class="flex gap-2 mb-4 scroll-strip">
    <button onclick="kiFilter='all';kiRender()" class="pill active" id="kiFilterAll">All</button>
    <button onclick="kiFilter='in_kitchen';kiRender()" class="pill" id="kiFilterInKitchen">In Kitchen</button>
    <button onclick="kiFilter='used';kiRender()" class="pill" id="kiFilterUsed">Fully Used</button>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-2 mb-4">
    <div class="bg-white border border-gray-200 rounded-xl p-2.5 text-center">
        <div class="text-xl font-bold text-gray-800" id="kiStatTotal">—</div>
        <div class="text-[9px] text-gray-400">Items</div>
    </div>
    <div class="bg-white border border-green-200 rounded-xl p-2.5 text-center">
        <div class="text-xl font-bold text-green-600" id="kiStatReceived">—</div>
        <div class="text-[9px] text-gray-400">Received (kg)</div>
    </div>
    <div class="bg-white border border-orange-200 rounded-xl p-2.5 text-center">
        <div class="text-xl font-bold text-orange-600" id="kiStatUsed">—</div>
        <div class="text-[9px] text-gray-400">Used (kg)</div>
    </div>
</div>

<!-- Item List -->
<div id="kiItemList" class="space-y-1.5 mb-4"></div>

<!-- Adjust Stock Modal -->
<div id="kiAdjustModal" class="hidden fixed inset-0 z-[200] bg-black/50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-5">
        <h3 class="text-base font-bold text-gray-900 mb-1">Adjust Stock</h3>
        <p class="text-xs text-gray-500 mb-4" id="kiAdjustItemName">—</p>
        <div class="mb-3">
            <label class="text-[10px] text-gray-500 font-medium mb-1 block">Current Store Stock</label>
            <div class="text-lg font-bold text-gray-800" id="kiAdjustCurrentStock">—</div>
        </div>
        <div class="mb-3">
            <label class="text-[10px] text-gray-500 font-medium mb-1 block">Adjustment (+/-)</label>
            <div class="flex items-center gap-2">
                <button onclick="kiAdjStep(-1)" class="stepper-btn bg-red-100 text-red-600 text-lg">−</button>
                <input type="number" id="kiAdjustQty" step="0.5" class="w-20 text-center text-lg font-bold border border-gray-200 rounded-xl py-2" value="0">
                <button onclick="kiAdjStep(1)" class="stepper-btn bg-green-100 text-green-600 text-lg">+</button>
            </div>
        </div>
        <div class="mb-4">
            <label class="text-[10px] text-gray-500 font-medium mb-1 block">Reason (required)</label>
            <input type="text" id="kiAdjustReason" placeholder="e.g. Physical count mismatch" class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5">
        </div>
        <div class="flex gap-2">
            <button onclick="kiCloseAdjust()" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-medium text-sm">Cancel</button>
            <button onclick="kiSubmitAdjust()" class="flex-1 py-2.5 rounded-xl bg-orange-600 text-white font-medium text-sm">Save</button>
        </div>
    </div>
</div>

<script>
const KI_KID = <?= (int)$kitchenId ?>;
let kiData = [];
let kiFilter = 'all';
let kiAdjustItemId = null;

kiLoad();

async function kiLoad() {
    const q = document.getElementById('kiSearch').value.trim();
    const container = document.getElementById('kiItemList');
    container.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">Loading...</p>';

    try {
        const data = await api(`api/inventory.php?action=kitchen_stock&q=${encodeURIComponent(q)}`);
        kiData = data.items || [];
        kiRender();
    } catch(e) {
        container.innerHTML = '<p class="text-xs text-red-400 text-center py-6">Failed to load</p>';
    }
}

function kiRender() {
    // Update filter pills
    document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
    document.getElementById('kiFilter' + (kiFilter === 'all' ? 'All' : kiFilter === 'in_kitchen' ? 'InKitchen' : 'Used')).classList.add('active');

    let items = kiData;
    if (kiFilter === 'in_kitchen') {
        items = items.filter(i => parseFloat(i.in_kitchen) - parseFloat(i.used_total) > 0);
    } else if (kiFilter === 'used') {
        items = items.filter(i => parseFloat(i.in_kitchen) - parseFloat(i.used_total) <= 0 && parseFloat(i.in_kitchen) > 0);
    }

    // Stats
    let totalReceived = 0, totalUsed = 0;
    kiData.forEach(i => {
        totalReceived += parseFloat(i.in_kitchen) || 0;
        totalUsed += parseFloat(i.used_total) || 0;
    });
    document.getElementById('kiStatTotal').textContent = kiData.length;
    document.getElementById('kiStatReceived').textContent = totalReceived.toFixed(1);
    document.getElementById('kiStatUsed').textContent = totalUsed.toFixed(1);

    const container = document.getElementById('kiItemList');
    if (items.length === 0) {
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">No items found</p>';
        return;
    }

    // Group by category
    const byCategory = {};
    items.forEach(i => {
        const cat = i.category || 'Uncategorized';
        if (!byCategory[cat]) byCategory[cat] = [];
        byCategory[cat].push(i);
    });

    let html = '';
    Object.keys(byCategory).sort().forEach(cat => {
        html += `<div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mt-3 mb-1">${escHtml(cat)}</div>`;
        byCategory[cat].forEach(item => {
            const received = parseFloat(item.in_kitchen) || 0;
            const used = parseFloat(item.used_total) || 0;
            const remaining = Math.max(0, received - used);
            const storeStock = parseFloat(item.stock_qty) || 0;
            const meals = item.meal_types || '';

            const statusColor = remaining > 0 ? 'border-green-100' : 'border-gray-100';
            const remainingColor = remaining > 0 ? 'text-green-600' : 'text-gray-400';

            html += `<div class="bg-white border ${statusColor} rounded-xl px-3 py-2.5" onclick="kiShowAdjust(${item.id}, '${escHtml(item.name)}', ${storeStock})">
                <div class="flex items-center justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-medium text-gray-800">${escHtml(item.name)}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            ${meals ? meals.split(',').map(m => `<span class="capitalize">${m.trim()}</span>`).join(' · ') : ''}
                        </div>
                    </div>
                    <div class="text-right shrink-0 ml-3">
                        <div class="flex items-center gap-2">
                            <div>
                                <div class="text-[9px] text-gray-400">Received</div>
                                <div class="text-xs font-semibold text-blue-600">${received.toFixed(1)}</div>
                            </div>
                            <div>
                                <div class="text-[9px] text-gray-400">Used</div>
                                <div class="text-xs font-semibold text-orange-600">${used.toFixed(1)}</div>
                            </div>
                            <div>
                                <div class="text-[9px] text-gray-400">Left</div>
                                <div class="text-xs font-bold ${remainingColor}">${remaining.toFixed(1)}</div>
                            </div>
                        </div>
                        <div class="text-[9px] text-gray-300 mt-0.5">Store: ${storeStock.toFixed(1)} ${item.uom || ''}</div>
                    </div>
                </div>
            </div>`;
        });
    });

    container.innerHTML = html;
}

function kiShowAdjust(itemId, itemName, currentStock) {
    kiAdjustItemId = itemId;
    document.getElementById('kiAdjustItemName').textContent = itemName;
    document.getElementById('kiAdjustCurrentStock').textContent = currentStock.toFixed(1) + ' in store';
    document.getElementById('kiAdjustQty').value = '0';
    document.getElementById('kiAdjustReason').value = '';
    document.getElementById('kiAdjustModal').classList.remove('hidden');
}

function kiCloseAdjust() {
    document.getElementById('kiAdjustModal').classList.add('hidden');
    kiAdjustItemId = null;
}

function kiAdjStep(dir) {
    const inp = document.getElementById('kiAdjustQty');
    inp.value = (parseFloat(inp.value) || 0) + dir * 0.5;
}

async function kiSubmitAdjust() {
    const qty = parseFloat(document.getElementById('kiAdjustQty').value);
    const reason = document.getElementById('kiAdjustReason').value.trim();
    if (!qty || qty === 0) return showToast('Enter an adjustment amount', 'error');
    if (!reason) return showToast('Reason is required', 'error');

    try {
        await api('api/inventory.php?action=adjust', {
            method: 'POST',
            body: JSON.stringify({ item_id: kiAdjustItemId, adjustment: qty, reason: reason })
        });
        showToast('Stock adjusted', 'success');
        kiCloseAdjust();
        kiLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}
</script>
