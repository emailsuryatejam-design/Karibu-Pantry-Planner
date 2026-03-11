<!-- Kitchen Inventory Log — Stock levels + movement tracking -->
<?php
$user = currentUser();
$kitchenName = $user['kitchen_name'] ?? 'Store';
?>

<div id="invPage">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-green-600"><path d="m20 7-8.5 8.5-4-4L2 17"/><path d="M23 7h-6v6"/></svg>
                Inventory
            </h1>
            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($kitchenName) ?> — Stock Levels</p>
        </div>
        <button onclick="invShowAdjust()" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-green-700 transition flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Adjust
        </button>
    </div>

    <!-- Stats -->
    <div class="flex gap-2 overflow-x-auto pb-2 mb-3">
        <div class="min-w-[90px] bg-green-50 border border-green-200 rounded-xl p-2.5 text-center flex-1">
            <div class="text-lg font-bold text-green-700" id="invStatIn">—</div>
            <div class="text-[9px] text-green-600 font-medium">In Stock</div>
        </div>
        <div class="min-w-[90px] bg-amber-50 border border-amber-200 rounded-xl p-2.5 text-center flex-1">
            <div class="text-lg font-bold text-amber-700" id="invStatLow">—</div>
            <div class="text-[9px] text-amber-600 font-medium">Low Stock</div>
        </div>
        <div class="min-w-[90px] bg-red-50 border border-red-200 rounded-xl p-2.5 text-center flex-1">
            <div class="text-lg font-bold text-red-700" id="invStatOut">—</div>
            <div class="text-[9px] text-red-600 font-medium">Out of Stock</div>
        </div>
    </div>

    <!-- Search + Filter -->
    <div class="flex gap-2 mb-3">
        <div class="flex-1 relative">
            <input type="text" id="invSearch" placeholder="Search items..." oninput="invDebounceLoad()"
                class="w-full bg-white border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-200 focus:border-green-400">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-3 text-gray-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <select id="invCatFilter" onchange="invLoad()"
            class="bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-200">
            <option value="">All</option>
        </select>
    </div>

    <!-- Quick filters -->
    <div class="flex gap-1.5 mb-3">
        <button onclick="invQuickFilter('all')" id="inv-qf-all"
            class="inv-qf px-3 py-1.5 rounded-full text-[11px] font-medium bg-green-600 text-white">All</button>
        <button onclick="invQuickFilter('low')" id="inv-qf-low"
            class="inv-qf px-3 py-1.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">Low Stock</button>
        <button onclick="invQuickFilter('out')" id="inv-qf-out"
            class="inv-qf px-3 py-1.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">Out of Stock</button>
    </div>

    <!-- Loading -->
    <div id="invLoading" class="flex flex-col items-center justify-center py-12">
        <svg class="animate-spin text-green-500 mb-3" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-xs text-gray-500">Loading inventory...</p>
    </div>

    <!-- Items List -->
    <div id="invList" class="space-y-1 hidden"></div>

    <!-- Empty State -->
    <div id="invEmpty" class="hidden text-center py-12">
        <p class="text-gray-400 text-sm">No items found</p>
    </div>
</div>

<script>
let invItems = [];
let invQuick = 'all';
let invDebounceTimer;

invLoad();

function invDebounceLoad() {
    clearTimeout(invDebounceTimer);
    invDebounceTimer = setTimeout(invLoad, 300);
}

function invQuickFilter(type) {
    invQuick = type;
    document.querySelectorAll('.inv-qf').forEach(b => {
        b.className = b.className.replace(/bg-green-600 text-white/g, 'bg-gray-100 text-gray-600');
    });
    const active = document.getElementById('inv-qf-' + type);
    if (active) active.className = active.className.replace(/bg-gray-100 text-gray-600/g, 'bg-green-600 text-white');
    invRender();
}

async function invLoad() {
    document.getElementById('invLoading').classList.remove('hidden');
    document.getElementById('invList').classList.add('hidden');
    document.getElementById('invEmpty').classList.add('hidden');

    const q = document.getElementById('invSearch').value.trim();
    const cat = document.getElementById('invCatFilter').value;

    try {
        const data = await api(`api/inventory.php?action=stock&q=${encodeURIComponent(q)}&category=${encodeURIComponent(cat)}`);
        invItems = data.items || [];

        // Update stats
        const s = data.stats || {};
        document.getElementById('invStatIn').textContent = s.in_stock || 0;
        document.getElementById('invStatLow').textContent = s.low_stock || 0;
        document.getElementById('invStatOut').textContent = s.out_of_stock || 0;

        // Populate categories
        const catSelect = document.getElementById('invCatFilter');
        const currentCat = catSelect.value;
        const cats = data.categories || [];
        catSelect.innerHTML = '<option value="">All Categories</option>' +
            cats.map(c => `<option value="${escHtml(c)}" ${c === currentCat ? 'selected' : ''}>${escHtml(c)}</option>`).join('');

        invRender();
    } catch (err) {
        document.getElementById('invList').innerHTML =
            `<div class="text-center py-8 text-red-500 text-sm">${err.message}</div>`;
        document.getElementById('invList').classList.remove('hidden');
    } finally {
        document.getElementById('invLoading').classList.add('hidden');
    }
}

function invRender() {
    let filtered = invItems;
    if (invQuick === 'low') {
        filtered = invItems.filter(i => parseFloat(i.stock_qty) > 0 && parseFloat(i.stock_qty) <= 2);
    } else if (invQuick === 'out') {
        filtered = invItems.filter(i => parseFloat(i.stock_qty) <= 0);
    }

    if (filtered.length === 0) {
        document.getElementById('invEmpty').classList.remove('hidden');
        document.getElementById('invList').classList.add('hidden');
        return;
    }

    document.getElementById('invEmpty').classList.add('hidden');
    const list = document.getElementById('invList');
    list.classList.remove('hidden');

    // Group by category
    const groups = {};
    filtered.forEach(item => {
        const cat = item.category || 'Uncategorized';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(item);
    });

    let html = '';
    for (const [cat, items] of Object.entries(groups)) {
        html += `<div class="mb-3">
            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1 px-1">${escHtml(cat)} (${items.length})</div>`;

        items.forEach(item => {
            const qty = parseFloat(item.stock_qty) || 0;
            const isOut = qty <= 0;
            const isLow = qty > 0 && qty <= 2;
            const qtyColor = isOut ? 'text-red-600 bg-red-50' : isLow ? 'text-amber-600 bg-amber-50' : 'text-green-700 bg-green-50';
            const borderCls = isOut ? 'border-red-100' : isLow ? 'border-amber-100' : 'border-gray-100';

            html += `<div onclick="invShowMovements(${item.id}, '${escHtml(item.name).replace(/'/g, "\\'")}')"
                class="bg-white border ${borderCls} rounded-xl px-3 py-2.5 mb-1 flex items-center justify-between cursor-pointer active:bg-gray-50 transition">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-800 truncate">${escHtml(item.name)}</p>
                    <p class="text-[10px] text-gray-400">${escHtml(item.uom || 'kg')}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold px-2.5 py-1 rounded-lg ${qtyColor}">${qty > 0 ? qty.toFixed(1) : '0'}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-300"><path d="m9 18 6-6-6-6"/></svg>
                </div>
            </div>`;
        });
        html += '</div>';
    }
    list.innerHTML = html;
}

// ── Movement log for an item ──
async function invShowMovements(itemId, itemName) {
    try {
        const data = await api(`api/inventory.php?action=movements&item_id=${itemId}&days=14`);
        const item = data.item;
        const moves = data.movements || [];
        const qty = parseFloat(item.stock_qty) || 0;

        let html = `<div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
            <div class="px-5 py-3 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">${escHtml(item.name)}</h3>
                        <p class="text-[10px] text-gray-500">${escHtml(item.category || '')} &middot; ${escHtml(item.uom || 'kg')}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold ${qty <= 0 ? 'text-red-600' : qty <= 2 ? 'text-amber-600' : 'text-green-700'}">${qty.toFixed(1)}</div>
                        <div class="text-[9px] text-gray-400">Current Stock</div>
                    </div>
                </div>
            </div>
            <div class="px-5 py-3 max-h-[55vh] overflow-y-auto scroll-touch">
                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Last 14 Days Activity</div>`;

        if (moves.length === 0) {
            html += '<p class="text-center text-gray-400 text-xs py-6">No activity in the last 14 days</p>';
        } else {
            html += '<div class="space-y-1.5">';
            moves.forEach(m => {
                const meal = (m.meals || '').replace(/^./, c => c.toUpperCase());
                const supp = parseInt(m.supplement_number) || 0;
                const mealLabel = supp > 0 ? `${meal} (${supp + 1})` : meal;
                const ordered = parseFloat(m.order_qty) || 0;
                const sent = parseFloat(m.fulfilled_qty) || 0;
                const received = parseFloat(m.received_qty) || 0;
                const unused = parseFloat(m.unused_qty) || 0;
                const used = received > 0 ? received - unused : sent - unused;

                html += `<div class="bg-gray-50 rounded-lg px-3 py-2">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700">${formatDate(m.req_date)} — ${mealLabel}</span>
                        <span class="text-[10px] text-gray-400">${m.chef_name || ''}</span>
                    </div>
                    <div class="flex gap-3 text-[10px]">
                        <span class="text-blue-600">Ordered: <strong>${ordered}</strong></span>
                        <span class="text-green-600">Sent: <strong>${sent}</strong></span>
                        ${received > 0 ? `<span class="text-emerald-600">Recv: <strong>${received}</strong></span>` : ''}
                        ${unused > 0 ? `<span class="text-amber-600">Unused: <strong>${unused}</strong></span>` : ''}
                        ${used > 0 ? `<span class="text-gray-600">Used: <strong>${used > 0 ? used.toFixed(1) : '0'}</strong></span>` : ''}
                    </div>
                </div>`;
            });
            html += '</div>';
        }

        html += `</div>
            <div class="px-5 py-3 border-t border-gray-100">
                <button onclick="closeSheet()" class="w-full bg-gray-100 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-200 transition">Close</button>
            </div>`;

        openSheet(html);
    } catch (err) {
        showToast('Failed to load movements: ' + (err.message || ''), 'error');
    }
}

// ── Stock adjustment ──
function invShowAdjust() {
    let html = `<div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Adjust Stock</h3>
            <p class="text-[10px] text-gray-500">Add or remove stock for an item</p>
        </div>
        <div class="px-5 py-4 space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Item</label>
                <select id="adjItem" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-200">
                    <option value="">Select item...</option>
                    ${invItems.map(i => `<option value="${i.id}">${escHtml(i.name)} (${parseFloat(i.stock_qty).toFixed(1)} ${i.uom})</option>`).join('')}
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Adjustment (+/- quantity)</label>
                <input type="number" id="adjQty" step="0.5" placeholder="e.g. 5 or -2"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-200">
                <p class="text-[10px] text-gray-400 mt-1">Positive to add, negative to remove</p>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Reason</label>
                <input type="text" id="adjReason" placeholder="e.g. Physical count correction"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-200">
            </div>
            <button onclick="invDoAdjust()" class="w-full bg-green-600 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-700 transition">
                Apply Adjustment
            </button>
        </div>`;
    openSheet(html);
}

async function invDoAdjust() {
    const itemId = document.getElementById('adjItem').value;
    const adjustment = parseFloat(document.getElementById('adjQty').value);
    const reason = document.getElementById('adjReason').value.trim();

    if (!itemId) return showToast('Select an item', 'error');
    if (!adjustment || isNaN(adjustment)) return showToast('Enter a valid quantity', 'error');
    if (!reason) return showToast('Reason is required', 'error');

    try {
        const data = await api('api/inventory.php?action=adjust', {
            method: 'POST',
            body: { item_id: parseInt(itemId), adjustment, reason }
        });
        closeSheet();
        showToast(`Stock updated to ${data.new_stock}`, 'success');
        invLoad();
    } catch (err) {
        showToast(err.message || 'Failed', 'error');
    }
}
</script>
