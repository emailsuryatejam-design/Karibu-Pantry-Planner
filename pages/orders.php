<!-- Chef Orders — Review & submit requisition orders to store -->
<?php
$user = currentUser();
$kitchenId = currentKitchenId();
?>
<div id="ordersPage">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-600"><path d="M9 5H2v7l6.29 6.29c.94.94 2.48.94 3.42 0l3.58-3.58c.94-.94.94-2.48 0-3.42L9 5Z"/><path d="M6 9.01V9"/><path d="m15 5 6.3 6.3a2.4 2.4 0 0 1 0 3.4L17 19"/></svg>
                My Orders
            </h1>
            <p class="text-xs text-gray-500 mt-0.5" id="ordDateLabel">Loading...</p>
        </div>
        <button onclick="ordRefresh()" class="p-2 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 active:bg-gray-300 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
        </button>
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
</div>

<script>
const ORD_KITCHEN_ID = <?= (int)$kitchenId ?>;
const ORD_TODAY = todayStr();

// State
let ordRequisitions = [];
let ordLinesByReq = {};      // { reqId: [lines] }
let ordAdjustments = {};     // { lineId: adjustedQty }

// ── Meal type colors ──
const ordMealColors = {
    breakfast: { border: 'border-amber-300', bg: 'bg-amber-50', text: 'text-amber-700', accent: 'bg-amber-100 text-amber-700', header: 'bg-amber-50 border-amber-200' },
    lunch:     { border: 'border-blue-300', bg: 'bg-blue-50', text: 'text-blue-700', accent: 'bg-blue-100 text-blue-700', header: 'bg-blue-50 border-blue-200' },
    dinner:    { border: 'border-purple-300', bg: 'bg-purple-50', text: 'text-purple-700', accent: 'bg-purple-100 text-purple-700', header: 'bg-purple-50 border-purple-200' },
    supper:    { border: 'border-indigo-300', bg: 'bg-indigo-50', text: 'text-indigo-700', accent: 'bg-indigo-100 text-indigo-700', header: 'bg-indigo-50 border-indigo-200' },
    snack:     { border: 'border-pink-300', bg: 'bg-pink-50', text: 'text-pink-700', accent: 'bg-pink-100 text-pink-700', header: 'bg-pink-50 border-pink-200' },
};
const ordDefaultColor = { border: 'border-gray-300', bg: 'bg-gray-50', text: 'text-gray-700', accent: 'bg-gray-100 text-gray-700', header: 'bg-gray-50 border-gray-200' };

function ordGetColor(meals) {
    const key = (meals || '').toLowerCase();
    return ordMealColors[key] || ordDefaultColor;
}

// ── Status badges ──
function ordStatusBadge(status) {
    const map = {
        processing:  { cls: 'bg-amber-100 text-amber-700', label: 'Processing' },
        submitted:   { cls: 'bg-blue-100 text-blue-700', label: 'Submitted' },
        fulfilled:   { cls: 'bg-emerald-100 text-emerald-700', label: 'Sent' },
        received:    { cls: 'bg-green-100 text-green-700', label: 'Received' },
        closed:      { cls: 'bg-gray-200 text-gray-500', label: 'Closed' },
    };
    const s = map[status] || { cls: 'bg-gray-100 text-gray-600', label: status };
    return `<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${s.cls}">${s.label}</span>`;
}

// ── Init ──
ordLoad();

async function ordLoad() {
    document.getElementById('ordLoading').classList.remove('hidden');
    document.getElementById('ordList').classList.add('hidden');
    document.getElementById('ordEmpty').classList.add('hidden');

    document.getElementById('ordDateLabel').textContent = 'Today \u2014 ' + formatDate(ORD_TODAY);

    try {
        const res = await api(`api/requisitions.php?action=day_summary&date=${ORD_TODAY}&kitchen_id=${ORD_KITCHEN_ID}`);
        const allReqs = res.requisitions || [];
        const linesByReq = res.lines_by_req || {};

        // Filter to relevant statuses
        const validStatuses = ['processing', 'submitted', 'fulfilled', 'received'];
        ordRequisitions = allReqs.filter(r => validStatuses.includes(r.status));
        ordLinesByReq = linesByReq;
        ordAdjustments = {};

        // For processing requisitions, fetch their lines
        const processingReqs = ordRequisitions.filter(r => r.status === 'processing');
        const linePromises = processingReqs.map(r =>
            api(`api/requisitions.php?action=get&id=${r.id}`).then(data => {
                ordLinesByReq[r.id] = data.lines || [];
            }).catch(() => { ordLinesByReq[r.id] = []; })
        );
        await Promise.all(linePromises);

        ordRender();
    } catch (err) {
        document.getElementById('ordList').innerHTML =
            `<div class="text-center py-8 text-red-500 text-sm">${escHtml(err.message)}</div>`;
        document.getElementById('ordList').classList.remove('hidden');
    } finally {
        document.getElementById('ordLoading').classList.add('hidden');
    }
}

function ordRefresh() {
    ordLoad();
}

function ordRender() {
    if (ordRequisitions.length === 0) {
        document.getElementById('ordEmpty').classList.remove('hidden');
        return;
    }

    const list = document.getElementById('ordList');
    list.classList.remove('hidden');
    list.innerHTML = ordRequisitions.map(req => ordRenderCard(req)).join('');
}

function ordRenderCard(req) {
    const color = ordGetColor(req.meals);
    const mealLabel = reqLabel(req);
    const isProcessing = req.status === 'processing';
    const lines = ordLinesByReq[req.id] || [];

    let html = `<div class="bg-white rounded-xl border ${color.border} overflow-hidden shadow-sm" id="ord-card-${req.id}">`;

    // Card header
    html += `<div class="flex items-center justify-between px-4 py-3 ${color.header} border-b">
        <div class="flex items-center gap-2">
            <span class="text-sm font-bold ${color.text}">${escHtml(mealLabel)} Order</span>
            <span class="text-[10px] text-gray-400">#${req.id}</span>
        </div>
        <div>${ordStatusBadge(req.status)}</div>
    </div>`;

    if (isProcessing) {
        // ── Editable card: show lines with +/- qty controls ──
        html += ordRenderEditableLines(req, lines, color);
    } else {
        // ── Read-only card: table view ──
        html += ordRenderReadOnlyLines(req, lines);
    }

    html += '</div>';
    return html;
}

// ── Editable lines for 'processing' orders ──
function ordRenderEditableLines(req, lines, color) {
    if (lines.length === 0) {
        return `<div class="px-4 py-6 text-center text-xs text-gray-400">No items in this order</div>`;
    }

    let html = `<div class="px-4 py-3">
        <div class="text-[10px] text-gray-500 uppercase tracking-wider font-semibold mb-2">${lines.length} item${lines.length !== 1 ? 's' : ''} to review</div>
        <div class="space-y-2" id="ord-lines-${req.id}">`;

    lines.forEach(line => {
        const qty = parseFloat(line.order_qty) || 0;
        // Initialize adjustment state if not set
        if (ordAdjustments[line.id] === undefined) {
            ordAdjustments[line.id] = qty;
        }
        const currentQty = ordAdjustments[line.id];

        html += `<div class="bg-gray-50 rounded-xl px-3 py-3" id="ord-line-${line.id}">
            <div class="flex items-center justify-between mb-2">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm text-gray-800 truncate">${escHtml(line.item_name)}</p>
                    <p class="text-[10px] text-gray-400">${escHtml(line.uom || '')}</p>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg px-2.5 py-1.5 text-center min-w-[55px] ml-2">
                    <span class="text-[9px] text-orange-400 block">Calc</span>
                    <span class="text-xs font-bold text-orange-700">${qty}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[9px] text-green-600 font-medium w-10 shrink-0">Order:</span>
                <div class="flex items-center gap-1 flex-1">
                    <button onclick="ordAdj(${line.id}, ${req.id}, -0.5)" class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-gray-600 font-bold text-lg flex items-center justify-center compact-btn active:bg-gray-100">-</button>
                    <input type="number" value="${currentQty}" step="0.5" min="0" id="ordQty_${line.id}"
                        onchange="ordQtyChanged(${line.id})"
                        class="flex-1 text-center text-lg font-bold border-2 border-green-300 rounded-xl px-1 py-2 focus:outline-none focus:ring-2 focus:ring-green-200 compact-btn bg-green-50 min-w-[60px]">
                    <button onclick="ordAdj(${line.id}, ${req.id}, 0.5)" class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-gray-600 font-bold text-lg flex items-center justify-center compact-btn active:bg-gray-100">+</button>
                </div>
            </div>
        </div>`;
    });

    html += `</div>`;

    // Submit button
    html += `<button onclick="ordSubmitToStore(${req.id})" id="ord-submit-${req.id}"
        class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-semibold transition mt-3 flex items-center justify-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
        Submit to Store
    </button>`;

    html += '</div>';
    return html;
}

// ── Read-only lines for submitted/fulfilled/received orders ──
function ordRenderReadOnlyLines(req, lines) {
    if (lines.length === 0) {
        return `<div class="px-4 py-6 text-center text-xs text-gray-400">No items</div>`;
    }

    let html = `<div class="px-3 py-3">
        <div class="overflow-x-auto">
            <table class="w-full text-[11px]">
                <thead><tr class="bg-gray-50">
                    <th class="text-left px-2 py-1.5 text-gray-500 font-semibold rounded-tl-lg">Item</th>
                    <th class="text-center px-1 py-1.5 text-blue-600 font-semibold">Req</th>
                    <th class="text-center px-1 py-1.5 text-green-600 font-semibold">Sent</th>
                    <th class="text-center px-1 py-1.5 text-orange-600 font-semibold rounded-tr-lg">Received</th>
                </tr></thead>
                <tbody>`;

    lines.forEach(line => {
        const oq = parseFloat(line.order_qty) || 0;
        const fq = line.fulfilled_qty !== null && line.fulfilled_qty !== undefined ? parseFloat(line.fulfilled_qty) : 0;
        const rq = line.received_qty !== null && line.received_qty !== undefined ? parseFloat(line.received_qty) : 0;

        html += `<tr class="border-b border-gray-50">
            <td class="px-2 py-2 text-gray-700">
                ${escHtml(line.item_name)}
                <span class="text-gray-300 text-[9px] ml-0.5">${escHtml(line.uom || '')}</span>
            </td>
            <td class="text-center px-1 py-2 text-blue-700 font-medium">${oq > 0 ? oq.toFixed(1) : '\u2014'}</td>
            <td class="text-center px-1 py-2 text-green-700 font-medium">${fq > 0 ? fq.toFixed(1) : '\u2014'}</td>
            <td class="text-center px-1 py-2 text-orange-700 font-medium">${rq > 0 ? rq.toFixed(1) : '\u2014'}</td>
        </tr>`;
    });

    html += `</tbody></table></div>`;

    // Status footer
    if (req.status === 'submitted') {
        html += `<div class="bg-blue-50 border border-blue-200 rounded-xl px-3 py-2.5 mt-3 text-center">
            <p class="text-[11px] text-blue-700 font-medium">Order submitted. Waiting for store to prepare.</p>
        </div>`;
    } else if (req.status === 'fulfilled') {
        html += `<div class="bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2.5 mt-3 text-center">
            <p class="text-[11px] text-emerald-700 font-medium">Items sent from store. Check and confirm receipt.</p>
        </div>`;
    } else if (req.status === 'received') {
        html += `<div class="bg-green-50 border border-green-200 rounded-xl px-3 py-2.5 mt-3 text-center">
            <p class="text-[11px] text-green-700 font-medium">All items received.</p>
        </div>`;
    }

    html += '</div>';
    return html;
}

// ── Quantity adjustment ──
function ordAdj(lineId, reqId, delta) {
    const input = document.getElementById('ordQty_' + lineId);
    if (!input) return;
    const current = parseFloat(input.value) || 0;
    const newVal = Math.max(0, +(current + delta).toFixed(2));
    input.value = newVal;
    ordAdjustments[lineId] = newVal;
}

function ordQtyChanged(lineId) {
    const input = document.getElementById('ordQty_' + lineId);
    if (!input) return;
    const val = Math.max(0, parseFloat(input.value) || 0);
    input.value = val;
    ordAdjustments[lineId] = val;
}

// ── Submit order to store ──
async function ordSubmitToStore(reqId) {
    const lines = ordLinesByReq[reqId] || [];
    if (lines.length === 0) {
        showToast('No items to submit', 'error');
        return;
    }

    const lineData = lines.map(line => ({
        id: parseInt(line.id),
        order_qty: ordAdjustments[line.id] !== undefined ? ordAdjustments[line.id] : (parseFloat(line.order_qty) || 0)
    }));

    // Check for zero quantities
    const nonZero = lineData.filter(l => l.order_qty > 0);
    if (nonZero.length === 0) {
        showToast('All quantities are zero', 'error');
        return;
    }

    const zeroCount = lineData.length - nonZero.length;
    if (zeroCount > 0) {
        const ok = await customConfirm(
            'Submit Order',
            `${zeroCount} item${zeroCount > 1 ? 's have' : ' has'} zero quantity and will be skipped. Submit the remaining ${nonZero.length} item${nonZero.length > 1 ? 's' : ''}?`
        );
        if (!ok) return;
    } else {
        const ok = await customConfirm(
            'Submit to Store',
            `Send ${nonZero.length} item${nonZero.length > 1 ? 's' : ''} to the store for fulfillment?`
        );
        if (!ok) return;
    }

    const btn = document.getElementById('ord-submit-' + reqId);
    if (btn) setLoading(btn, true, 'Submitting...');

    try {
        await api('api/requisitions.php?action=submit_order', {
            method: 'POST',
            body: { requisition_id: reqId, lines: lineData }
        });
        showToast('Order submitted to store!', 'success');
        // Reload to reflect new status
        ordLoad();
    } catch (err) {
        showToast(err.message || 'Failed to submit order', 'error');
    } finally {
        if (btn) setLoading(btn, false);
    }
}
</script>
