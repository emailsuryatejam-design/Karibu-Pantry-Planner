<!-- Chef Orders — Review & submit requisition orders to store -->
<?php
$user = currentUser();
$kitchenId = currentKitchenId();
?>
<div id="ordersPage">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-600"><path d="M9 5H2v7l6.29 6.29c.94.94 2.48.94 3.42 0l3.58-3.58c.94-.94.94-2.48 0-3.42L9 5Z"/><path d="M6 9.01V9"/><path d="m15 5 6.3 6.3a2.4 2.4 0 0 1 0 3.4L17 19"/></svg>
                My Orders
            </h1>
        </div>
        <button onclick="ordRefresh()" class="p-2 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 active:bg-gray-300 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
        </button>
    </div>

    <!-- Date Switcher -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-200 px-3 py-2.5 mb-3">
        <button onclick="ordChangeDate(-1)" class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 active:bg-gray-300 flex items-center justify-center transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="text-center flex-1">
            <div class="text-sm font-bold text-gray-800" id="ordDateDisplay"></div>
            <button onclick="ordGoToday()" id="ordTodayBtn" class="text-[10px] text-orange-500 font-semibold hidden">Back to Today</button>
        </div>
        <button onclick="ordChangeDate(1)" class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 active:bg-gray-300 flex items-center justify-center transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>

    <!-- Menu/Staple Tabs -->
    <div class="flex gap-1.5 mb-3">
        <button onclick="ordSwitchTab('menu')" id="ordTabMenu"
            class="flex-1 py-2.5 rounded-xl text-xs font-semibold transition bg-orange-500 text-white">Menu Items</button>
        <button onclick="ordSwitchTab('staple')" id="ordTabStaple"
            class="flex-1 py-2.5 rounded-xl text-xs font-semibold transition bg-gray-100 text-gray-600">Staple Items</button>
    </div>

    <!-- Loading -->
    <div id="ordLoading" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-orange-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading orders...</p>
    </div>

    <!-- Orders List -->
    <div id="ordList" class="space-y-3 hidden"></div>

    <!-- Empty State -->
    <div id="ordEmpty" class="hidden text-center py-16">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto text-gray-300 mb-4"><path d="M9 5H2v7l6.29 6.29c.94.94 2.48.94 3.42 0l3.58-3.58c.94-.94.94-2.48 0-3.42L9 5Z"/><path d="M6 9.01V9"/><path d="m15 5 6.3 6.3a2.4 2.4 0 0 1 0 3.4L17 19"/></svg>
        <p class="text-gray-500 text-sm font-medium mb-1">No orders yet</p>
        <p class="text-gray-400 text-xs">Plan your menu on the Dashboard first.</p>
    </div>

    <!-- Floating Add Item Button -->
    <button onclick="ordShowAddItem()" id="ordAddItemBtn"
        class="fixed bottom-20 right-4 w-14 h-14 bg-orange-500 text-white rounded-full shadow-lg flex items-center justify-center z-50 hover:bg-orange-600 active:bg-orange-700 transition">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
    </button>

    <!-- Add Staple Popup Modal (centered tile, not drawer) -->
    <div id="ordAddModal" class="hidden fixed inset-0 z-[200] bg-black/50 flex items-start justify-center pt-[10vh] p-4 animate-fade-in" onclick="if(event.target===this)ordCloseAddModal()">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full max-h-[80vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-bold text-gray-900">Add Staple Item</h3>
                    <p class="text-xs text-gray-400">Search and tap to add</p>
                </div>
                <button onclick="ordCloseAddModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-3">
                <div class="relative">
                    <input type="text" id="ordAddSearch" placeholder="Search items..."
                        oninput="ordFilterAddItems()"
                        class="w-full text-sm border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-3 text-gray-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
            </div>
            <div id="ordAddResults" class="flex-1 overflow-y-auto px-5 pb-4 space-y-1">
                <p class="text-xs text-gray-400 text-center py-3">Type to search items...</p>
            </div>
        </div>
    </div>

    <!-- Item Detail Popup (qty + UOM picker) -->
    <div id="ordItemDetailModal" class="hidden fixed inset-0 z-[210] bg-black/50 flex items-center justify-center p-4 animate-fade-in" onclick="if(event.target===this)ordCloseItemDetail()">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6" id="ordItemDetailContent"></div>
    </div>
</div>

<script>
const ORD_KITCHEN_ID = <?= (int)$kitchenId ?>;
const ORD_UOM_OPTIONS = ['kg', 'g', 'ltr', 'ml', 'pcs', 'tins', 'box', 'pkt', 'bunch', 'bottle', 'unit'];

let ordDate = todayStr();
let ordActiveTab = 'menu'; // 'menu' or 'staple'
let ordRequisitions = [];
let ordLinesByReq = {};
let ordAdjustments = {};
let ordAllItems = null; // cached for add-item

// Meal colors
const ordMealColors = {
    breakfast: { border: 'border-amber-300', bg: 'bg-amber-50', text: 'text-amber-700', header: 'bg-amber-50 border-amber-200' },
    lunch:     { border: 'border-blue-300', bg: 'bg-blue-50', text: 'text-blue-700', header: 'bg-blue-50 border-blue-200' },
    dinner:    { border: 'border-purple-300', bg: 'bg-purple-50', text: 'text-purple-700', header: 'bg-purple-50 border-purple-200' },
};
const ordDefaultColor = { border: 'border-gray-300', bg: 'bg-gray-50', text: 'text-gray-700', header: 'bg-gray-50 border-gray-200' };
function ordGetColor(meals) { return ordMealColors[(meals||'').toLowerCase()] || ordDefaultColor; }

function ordStatusBadge(status) {
    const map = {
        processing: { cls: 'bg-amber-100 text-amber-700', label: 'Processing' },
        submitted:  { cls: 'bg-blue-100 text-blue-700', label: 'Submitted' },
        fulfilled:  { cls: 'bg-emerald-100 text-emerald-700', label: 'Sent' },
        received:   { cls: 'bg-green-100 text-green-700', label: 'Received' },
    };
    const s = map[status] || { cls: 'bg-gray-100 text-gray-600', label: status };
    return `<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${s.cls}">${s.label}</span>`;
}

// ── Date Switcher ──
document.getElementById('ordDateDisplay').textContent = formatDate(ordDate);
ordLoad();

function ordChangeDate(days) {
    ordDate = changeDate(ordDate, days);
    document.getElementById('ordDateDisplay').textContent = formatDate(ordDate);
    document.getElementById('ordTodayBtn').classList.toggle('hidden', ordDate === todayStr());
    ordLoad();
}

function ordGoToday() {
    ordDate = todayStr();
    document.getElementById('ordDateDisplay').textContent = formatDate(ordDate);
    document.getElementById('ordTodayBtn').classList.add('hidden');
    ordLoad();
}

// ── Tab Switching ──
function ordSwitchTab(tab) {
    ordActiveTab = tab;
    document.getElementById('ordTabMenu').className = `flex-1 py-2.5 rounded-xl text-xs font-semibold transition ${tab === 'menu' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600'}`;
    document.getElementById('ordTabStaple').className = `flex-1 py-2.5 rounded-xl text-xs font-semibold transition ${tab === 'staple' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600'}`;
    ordRender();
}

function ordRefresh() { ordLoad(); }

async function ordLoad() {
    document.getElementById('ordLoading').classList.remove('hidden');
    document.getElementById('ordList').classList.add('hidden');
    document.getElementById('ordEmpty').classList.add('hidden');

    try {
        const res = await api(`api/requisitions.php?action=day_summary&date=${ordDate}&kitchen_id=${ORD_KITCHEN_ID}`);
        const allReqs = res.requisitions || [];
        const linesByReq = res.lines_by_req || {};

        const validStatuses = ['processing', 'submitted', 'fulfilled', 'received'];
        ordRequisitions = allReqs.filter(r => validStatuses.includes(r.status));
        ordLinesByReq = linesByReq;
        ordAdjustments = {};

        // Fetch full lines for processing requisitions
        const processingReqs = ordRequisitions.filter(r => r.status === 'processing');
        await Promise.all(processingReqs.map(r =>
            api(`api/requisitions.php?action=get&id=${r.id}`).then(data => {
                ordLinesByReq[r.id] = data.lines || [];
            }).catch(() => { ordLinesByReq[r.id] = []; })
        ));

        ordRender();
    } catch (err) {
        document.getElementById('ordList').innerHTML =
            `<div class="text-center py-8 text-red-500 text-sm">${escHtml(err.message)}</div>`;
        document.getElementById('ordList').classList.remove('hidden');
    } finally {
        document.getElementById('ordLoading').classList.add('hidden');
    }
}

function ordRender() {
    if (ordRequisitions.length === 0) {
        document.getElementById('ordEmpty').classList.remove('hidden');
        document.getElementById('ordList').classList.add('hidden');
        return;
    }

    const list = document.getElementById('ordList');
    list.classList.remove('hidden');
    document.getElementById('ordEmpty').classList.add('hidden');

    // Render cards, filtering lines by active tab
    list.innerHTML = ordRequisitions.map(req => ordRenderCard(req)).join('');
}

function ordRenderCard(req) {
    const color = ordGetColor(req.meals);
    const mealLabel = typeof reqLabel === 'function' ? reqLabel(req) : (req.meals || 'Order');
    const isProcessing = req.status === 'processing';
    const allLines = ordLinesByReq[req.id] || [];

    // Filter lines by tab
    const lines = allLines.filter(l => {
        const staple = parseInt(l.is_staple) || 0;
        return ordActiveTab === 'staple' ? staple === 1 : staple === 0;
    });

    if (lines.length === 0 && allLines.length > 0) {
        // Has lines but not in this tab — show minimal indicator
        const otherCount = allLines.filter(l => {
            const s = parseInt(l.is_staple) || 0;
            return ordActiveTab === 'staple' ? s === 0 : s === 1;
        }).length;
        return `<div class="bg-white rounded-xl border ${color.border} overflow-hidden opacity-50">
            <div class="flex items-center justify-between px-4 py-3 ${color.header} border-b">
                <span class="text-sm font-bold ${color.text}">${escHtml(mealLabel)} Order</span>
                ${ordStatusBadge(req.status)}
            </div>
            <div class="px-4 py-3 text-center text-xs text-gray-400">
                No ${ordActiveTab} items — ${otherCount} item${otherCount !== 1 ? 's' : ''} in other tab
            </div>
        </div>`;
    }

    if (lines.length === 0) return '';

    let html = `<div class="bg-white rounded-xl border ${color.border} overflow-hidden shadow-sm" id="ord-card-${req.id}">`;
    html += `<div class="flex items-center justify-between px-4 py-3 ${color.header} border-b">
        <div class="flex items-center gap-2">
            <span class="text-sm font-bold ${color.text}">${escHtml(mealLabel)} Order</span>
            <span class="text-[10px] text-gray-400">#${req.id}</span>
        </div>
        <div>${ordStatusBadge(req.status)}</div>
    </div>`;

    if (isProcessing) {
        html += ordRenderEditableLines(req, lines);
    } else {
        html += ordRenderReadOnlyLines(req, lines);
    }

    html += '</div>';
    return html;
}

// ── Editable lines (processing) — tap to edit ──
function ordRenderEditableLines(req, lines) {
    let html = `<div class="px-4 py-3">
        <div class="text-[10px] text-gray-500 uppercase tracking-wider font-semibold mb-2">${lines.length} item${lines.length !== 1 ? 's' : ''}</div>
        <div class="space-y-2">`;

    lines.forEach(line => {
        const qty = parseFloat(line.order_qty) || 0;
        if (ordAdjustments[line.id] === undefined) ordAdjustments[line.id] = qty;
        const currentQty = ordAdjustments[line.id];

        html += `<div class="bg-gray-50 rounded-xl px-3 py-3 cursor-pointer active:bg-gray-100 transition" onclick="ordShowEditLine(${line.id}, ${req.id})">
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm text-gray-800 truncate">${escHtml(line.item_name)}</p>
                    <p class="text-[10px] text-gray-400">${escHtml(line.uom || 'kg')}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="bg-orange-50 border border-orange-200 rounded-lg px-2 py-1 text-center">
                        <span class="text-[9px] text-orange-400 block">Calc</span>
                        <span class="text-xs font-bold text-orange-700">${qty}</span>
                    </div>
                    <div class="bg-green-50 border border-green-300 rounded-lg px-3 py-1 text-center min-w-[50px]">
                        <span class="text-[9px] text-green-500 block">Order</span>
                        <span class="text-sm font-bold text-green-700" id="ordQtyLabel_${line.id}">${currentQty}</span>
                    </div>
                </div>
            </div>
        </div>`;
    });

    html += `</div>`;

    // Submit + Delete buttons
    html += `<div class="flex gap-2 mt-3">
        <button onclick="ordDeleteOrder(${req.id})"
            class="px-4 py-3 rounded-xl border-2 border-red-200 text-red-500 font-semibold text-sm hover:bg-red-50 flex items-center justify-center gap-1.5 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
        </button>
        <button onclick="ordSubmitToStore(${req.id})" id="ord-submit-${req.id}"
            class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            Submit to Store
        </button>
    </div>`;

    html += '</div>';
    return html;
}

// ── Read-only lines ──
function ordRenderReadOnlyLines(req, lines) {
    let html = `<div class="px-3 py-3">
        <div class="overflow-x-auto">
            <table class="w-full text-[11px]">
                <thead><tr class="bg-gray-50">
                    <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">Item</th>
                    <th class="text-center px-1 py-1.5 text-blue-600 font-semibold">Req</th>
                    <th class="text-center px-1 py-1.5 text-green-600 font-semibold">Sent</th>
                    <th class="text-center px-1 py-1.5 text-orange-600 font-semibold">Recv</th>
                </tr></thead>
                <tbody>`;

    lines.forEach(line => {
        const oq = parseFloat(line.order_qty) || 0;
        const fq = parseFloat(line.fulfilled_qty) || 0;
        const rq = parseFloat(line.received_qty) || 0;
        html += `<tr class="border-b border-gray-50">
            <td class="px-2 py-2 text-gray-700">${escHtml(line.item_name)} <span class="text-gray-300 text-[9px]">${escHtml(line.uom || '')}</span></td>
            <td class="text-center px-1 py-2 text-blue-700 font-medium">${oq > 0 ? oq.toFixed(1) : '\u2014'}</td>
            <td class="text-center px-1 py-2 text-green-700 font-medium">${fq > 0 ? fq.toFixed(1) : '\u2014'}</td>
            <td class="text-center px-1 py-2 text-orange-700 font-medium">${rq > 0 ? rq.toFixed(1) : '\u2014'}</td>
        </tr>`;
    });

    html += `</tbody></table></div></div>`;
    return html;
}

// ══════════════════════════════════════════════
//  Item Edit Popup (tap on item)
// ══════════════════════════════════════════════
function ordShowEditLine(lineId, reqId) {
    const lines = ordLinesByReq[reqId] || [];
    const line = lines.find(l => parseInt(l.id) === lineId);
    if (!line) return;

    const qty = ordAdjustments[lineId] !== undefined ? ordAdjustments[lineId] : (parseFloat(line.order_qty) || 0);
    const uom = line.uom || 'kg';
    const uomOptions = ORD_UOM_OPTIONS.map(u => `<option value="${u}" ${u === uom ? 'selected' : ''}>${u}</option>`).join('');

    document.getElementById('ordItemDetailContent').innerHTML = `
        <h3 class="text-base font-bold text-gray-900 mb-1">${escHtml(line.item_name)}</h3>
        <p class="text-xs text-gray-400 mb-4">Edit quantity, UOM, or remove</p>
        <div class="space-y-4">
            <div>
                <label class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider mb-1 block">Quantity</label>
                <div class="flex items-center gap-2">
                    <button onclick="document.getElementById('ordEditQty').value = Math.max(0, parseFloat(document.getElementById('ordEditQty').value||0) - 1)"
                        class="w-10 h-10 rounded-xl bg-gray-100 text-gray-600 font-bold text-lg flex items-center justify-center active:bg-gray-200">-</button>
                    <input type="number" id="ordEditQty" value="${qty}" step="0.5" min="0"
                        class="flex-1 text-center text-xl font-bold border-2 border-green-300 rounded-xl py-2.5 focus:outline-none focus:ring-2 focus:ring-green-200 bg-green-50">
                    <button onclick="document.getElementById('ordEditQty').value = parseFloat(document.getElementById('ordEditQty').value||0) + 1"
                        class="w-10 h-10 rounded-xl bg-gray-100 text-gray-600 font-bold text-lg flex items-center justify-center active:bg-gray-200">+</button>
                </div>
            </div>
            <div>
                <label class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider mb-1 block">Unit of Measure</label>
                <select id="ordEditUom" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 bg-white">${uomOptions}</select>
            </div>
            <div class="flex gap-3 pt-1">
                <button onclick="ordRemoveLine(${lineId}, ${reqId})"
                    class="flex-1 py-3 rounded-xl border-2 border-red-200 text-red-600 font-semibold text-sm hover:bg-red-50 flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    Remove
                </button>
                <button onclick="ordSaveLine(${lineId}, ${reqId})"
                    class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-semibold text-sm hover:bg-orange-600 flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                    Save
                </button>
            </div>
        </div>`;
    document.getElementById('ordItemDetailModal').classList.remove('hidden');
}

async function ordSaveLine(lineId, reqId) {
    const qty = parseFloat(document.getElementById('ordEditQty').value) || 0;
    const uom = document.getElementById('ordEditUom').value;
    try {
        await api('api/requisitions.php?action=update_line', {
            method: 'POST',
            body: { line_id: lineId, order_qty: qty, uom: uom }
        });
        ordAdjustments[lineId] = qty;
        ordCloseItemDetail();
        showToast('Item updated');
        ordLoad();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function ordRemoveLine(lineId, reqId) {
    if (!await customConfirm('Remove Item', 'Remove this item from the order?')) return;
    try {
        await api('api/requisitions.php?action=chef_remove_line', {
            method: 'POST',
            body: { line_id: lineId }
        });
        ordCloseItemDetail();
        showToast('Item removed');
        ordLoad();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ══════════════════════════════════════════════
//  Delete/Cancel Order (before store accepts)
// ══════════════════════════════════════════════
async function ordDeleteOrder(reqId) {
    const req = ordRequisitions.find(r => r.id == reqId);
    if (!req) return;
    const mealLabel = typeof reqLabel === 'function' ? reqLabel(req) : (req.meals || 'Order');

    if (!await customConfirm('Delete Order', `Cancel and delete the ${mealLabel} order? This will remove all items and reset to draft.`)) return;

    try {
        await api('api/requisitions.php?action=cancel_order', {
            method: 'POST',
            body: { requisition_id: reqId }
        });
        showToast(`${mealLabel} order cancelled`, 'success');
        ordLoad();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ══════════════════════════════════════════════
//  Add Staple Item (popup modal, not drawer)
// ══════════════════════════════════════════════
let ordAddTargetReqId = null;

async function ordShowAddItem() {
    let targetReq = ordRequisitions.find(r => ['draft', 'processing', 'submitted'].includes(r.status));
    if (!targetReq) {
        try {
            showToast('Creating order...', 'info');
            const initData = await api('api/requisitions.php?action=page_init', {
                method: 'POST',
                body: { req_date: ordDate, kitchen_id: ORD_KITCHEN_ID, guest_count: 20 }
            });
            const newReqs = initData.requisitions || [];
            if (newReqs.length > 0) targetReq = newReqs[0];
        } catch (e) {}
    }
    if (!targetReq) { showToast('Could not create order. Try again.', 'error'); return; }
    ordAddTargetReqId = targetReq.id;

    if (!ordAllItems) {
        try { const res = await api('api/items.php?action=list&active=1'); ordAllItems = res.items || []; } catch (e) { ordAllItems = []; }
    }

    document.getElementById('ordAddModal').classList.remove('hidden');
    document.getElementById('ordAddSearch').value = '';
    document.getElementById('ordAddResults').innerHTML = '<p class="text-xs text-gray-400 text-center py-3">Type to search items...</p>';
    setTimeout(() => document.getElementById('ordAddSearch')?.focus(), 100);
}

function ordCloseAddModal() {
    document.getElementById('ordAddModal').classList.add('hidden');
}

function ordFilterAddItems() {
    const q = (document.getElementById('ordAddSearch')?.value || '').toLowerCase().trim();
    const results = document.getElementById('ordAddResults');
    if (!results || !ordAllItems) return;
    if (q.length < 2) { results.innerHTML = '<p class="text-xs text-gray-400 text-center py-3">Type at least 2 characters...</p>'; return; }

    const matches = ordAllItems.filter(item =>
        item.name.toLowerCase().includes(q) || (item.code && item.code.toLowerCase().includes(q))
    ).slice(0, 15);

    if (matches.length === 0) { results.innerHTML = '<p class="text-xs text-gray-400 text-center py-3">No items found</p>'; return; }

    results.innerHTML = matches.map(item => {
        const safeName = escHtml(item.name);
        return `<button onclick="ordShowItemDetail(${item.id}, '${safeName.replace(/'/g, "\\'")}', '${escHtml(item.uom||'kg')}')"
            class="w-full flex items-center gap-3 px-3 py-3 hover:bg-orange-50 active:bg-orange-100 transition text-left border-b border-gray-100 last:border-0 rounded-lg">
            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">${safeName}</p>
                <p class="text-[10px] text-gray-400">${escHtml(item.category || '')} &middot; ${escHtml(item.uom||'kg')}</p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
        </button>`;
    }).join('');
}

// ── Item detail popup tile (centered modal) ──
function ordShowItemDetail(itemId, itemName, itemUom) {
    ordCloseAddModal(); // close search modal
    const uomOptions = ORD_UOM_OPTIONS.map(u => `<option value="${u}" ${u === itemUom ? 'selected' : ''}>${u}</option>`).join('');

    document.getElementById('ordItemDetailContent').innerHTML = `
        <div class="flex items-center gap-3 mb-5">
            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">${itemName}</h3>
                <p class="text-xs text-gray-400">Add to staple order</p>
            </div>
        </div>
        <div class="space-y-4">
            <div>
                <label class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider mb-1.5 block">Quantity</label>
                <div class="flex items-center gap-2">
                    <button onclick="document.getElementById('ordAddQty').value = Math.max(0.5, parseFloat(document.getElementById('ordAddQty').value||0) - 1)"
                        class="w-11 h-11 rounded-xl bg-gray-100 text-gray-600 font-bold text-xl flex items-center justify-center active:bg-gray-200">-</button>
                    <input type="number" id="ordAddQty" value="1" step="0.5" min="0.5"
                        class="flex-1 text-center text-2xl font-bold border-2 border-green-300 rounded-xl py-3 focus:outline-none focus:ring-2 focus:ring-green-200 bg-green-50">
                    <button onclick="document.getElementById('ordAddQty').value = parseFloat(document.getElementById('ordAddQty').value||0) + 1"
                        class="w-11 h-11 rounded-xl bg-gray-100 text-gray-600 font-bold text-xl flex items-center justify-center active:bg-gray-200">+</button>
                </div>
            </div>
            <div>
                <label class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider mb-1.5 block">Unit of Measure</label>
                <select id="ordAddUom" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 bg-white">${uomOptions}</select>
            </div>
            <div class="flex gap-3 pt-1">
                <button onclick="ordCloseItemDetail()" class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-semibold text-sm">Cancel</button>
                <button onclick="ordConfirmAddItem(${itemId}, '${itemName.replace(/'/g, "\\'")}')" id="ordAddConfirmBtn"
                    class="flex-1 py-3 rounded-xl bg-green-600 text-white font-bold text-sm hover:bg-green-700 flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> Add
                </button>
            </div>
        </div>`;
    document.getElementById('ordItemDetailModal').classList.remove('hidden');
}

function ordCloseItemDetail() {
    document.getElementById('ordItemDetailModal').classList.add('hidden');
}

async function ordConfirmAddItem(itemId, itemName) {
    const qty = parseFloat(document.getElementById('ordAddQty')?.value) || 0;
    const uom = document.getElementById('ordAddUom')?.value || 'kg';
    if (qty <= 0) { showToast('Enter a valid quantity', 'error'); return; }

    const btn = document.getElementById('ordAddConfirmBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }

    try {
        await api('api/requisitions.php?action=add_line_to_order', {
            method: 'POST',
            body: { requisition_id: ordAddTargetReqId, item_id: itemId, item_name: itemName, order_qty: qty, uom: uom, is_staple: 1 }
        });
        showToast(`${itemName} added!`, 'success');
        ordCloseItemDetail();
        ordActiveTab = 'staple';
        ordSwitchTab('staple');
        ordLoad();
    } catch (err) {
        showToast(err.message, 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Add'; }
    }
}

// ── Submit order ──
async function ordSubmitToStore(reqId) {
    const allLines = ordLinesByReq[reqId] || [];
    if (allLines.length === 0) { showToast('No items to submit', 'error'); return; }

    const lineData = allLines.map(line => ({
        id: parseInt(line.id),
        order_qty: ordAdjustments[line.id] !== undefined ? ordAdjustments[line.id] : (parseFloat(line.order_qty) || 0)
    }));

    const nonZero = lineData.filter(l => l.order_qty > 0);
    if (nonZero.length === 0) { showToast('All quantities are zero', 'error'); return; }

    const zeroCount = lineData.length - nonZero.length;
    const msg = zeroCount > 0
        ? `${zeroCount} item${zeroCount > 1 ? 's have' : ' has'} zero qty and will be skipped. Submit ${nonZero.length} item${nonZero.length > 1 ? 's' : ''}?`
        : `Send ${nonZero.length} item${nonZero.length > 1 ? 's' : ''} to the store?`;

    if (!await customConfirm('Submit to Store', msg)) return;

    const btn = document.getElementById('ord-submit-' + reqId);
    if (btn) setLoading(btn, true, 'Submitting...');

    try {
        await api('api/requisitions.php?action=submit_order', {
            method: 'POST',
            body: { requisition_id: reqId, lines: lineData }
        });
        showToast('Order submitted to store!', 'success');
        ordLoad();
    } catch (err) {
        showToast(err.message || 'Failed to submit', 'error');
    } finally {
        if (btn) setLoading(btn, false);
    }
}
</script>
