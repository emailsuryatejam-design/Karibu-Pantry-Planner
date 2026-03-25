<!-- Store Orders — Storekeeper view: see chef orders, mark items as sent -->
<div id="storeOrdersPage">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-green-600"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                Store Orders
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">Requisitions from kitchen</p>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="flex gap-1.5 overflow-x-auto pb-2 mb-3 -mx-1 px-1 scroll-touch">
        <button onclick="soFilter('all')" id="so-tab-all"
            class="so-tab flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-green-600 text-white">
            All <span id="so-count-all" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="soFilter('pending')" id="so-tab-pending"
            class="so-tab flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            New <span id="so-count-pending" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="soFilter('fulfilled')" id="so-tab-fulfilled"
            class="so-tab flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Sent <span id="so-count-fulfilled" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="soFilter('received')" id="so-tab-received"
            class="so-tab flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Received <span id="so-count-received" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="soFilter('closed')" id="so-tab-closed"
            class="so-tab flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Closed <span id="so-count-closed" class="text-[10px] opacity-80">0</span>
        </button>
    </div>

    <!-- Loading -->
    <div id="soLoading" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-green-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading orders...</p>
    </div>

    <!-- Orders List -->
    <div id="soList" class="space-y-2 hidden"></div>

    <!-- Empty State -->
    <div id="soEmpty" class="hidden text-center py-12">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto text-gray-300 mb-3"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
        <p class="text-gray-500 text-sm">No orders found</p>
    </div>
</div>

<script>
let soStatus = 'all';
let soOrders = [];
let soCurrentOrderId = null;

soLoad();

function soFilter(status) {
    soStatus = status;
    document.querySelectorAll('.so-tab').forEach(btn => {
        btn.className = btn.className.replace(/bg-green-600 text-white/g, 'bg-gray-100 text-gray-600');
    });
    const active = document.getElementById('so-tab-' + status);
    if (active) {
        active.className = active.className.replace(/bg-gray-100 text-gray-600/g, 'bg-green-600 text-white');
    }
    soLoad();
}

function soStatusBadge(status, hasDispute) {
    const map = {
        pending: { cls: 'bg-amber-100 text-amber-700', label: 'New' },
        fulfilled: { cls: 'bg-emerald-100 text-emerald-700', label: 'Sent' },
        received: { cls: 'bg-green-100 text-green-700', label: 'Received' },
        closed: { cls: 'bg-gray-100 text-gray-600', label: 'Closed' },
    };
    const s = map[status] || { cls: 'bg-gray-100 text-gray-600', label: status };
    let badge = `<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${s.cls}">${s.label}</span>`;
    if (hasDispute) {
        badge += ` <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700">⚠ Dispute</span>`;
    }
    return badge;
}

async function soLoad() {
    document.getElementById('soLoading').classList.remove('hidden');
    document.getElementById('soList').classList.add('hidden');
    document.getElementById('soEmpty').classList.add('hidden');

    try {
        const res = await api(`api/store-orders.php?action=list&status=${soStatus}`);
        soOrders = res.orders || [];

        const counts = res.counts || {};
        ['all', 'pending', 'fulfilled', 'received', 'closed'].forEach(s => {
            const el = document.getElementById('so-count-' + s);
            if (el) el.textContent = counts[s] || 0;
        });

        soRender();
    } catch (err) {
        document.getElementById('soList').innerHTML =
            `<div class="text-center py-8 text-red-500 text-sm">${err.message}</div>`;
        document.getElementById('soList').classList.remove('hidden');
    } finally {
        document.getElementById('soLoading').classList.add('hidden');
    }
}

function soRender() {
    if (soOrders.length === 0) {
        document.getElementById('soEmpty').classList.remove('hidden');
        return;
    }

    const list = document.getElementById('soList');
    list.classList.remove('hidden');

    list.innerHTML = soOrders.map(order => {
        const date = formatDate(order.order_date);
        const isNew = order.status === 'pending';
        const hasDispute = parseInt(order.has_dispute) === 1;
        const borderCls = hasDispute ? 'border-red-200 shadow-sm' : isNew ? 'border-green-200 shadow-sm' : 'border-gray-100';

        const mealLabel = typeof reqLabel === 'function' ? reqLabel(order) : (order.meals || 'Order');

        return `
            <div onclick="soOpenDetail(${order.id})"
                 class="bg-white rounded-xl border ${borderCls} p-4 active:bg-gray-50 cursor-pointer transition">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <p class="font-semibold text-sm text-gray-800">${mealLabel}</p>
                        <p class="text-xs text-gray-500 mt-0.5">${date} &middot; ${order.total_items} item${order.total_items !== 1 ? 's' : ''}</p>
                    </div>
                    <div class="flex flex-wrap gap-1 justify-end">${soStatusBadge(order.status, hasDispute)}</div>
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] text-gray-400">From ${order.chef_name || 'Chef'}</p>
                    <span class="text-[11px] text-gray-400">#${order.id}</span>
                </div>
            </div>`;
    }).join('');
}

// ── Order Detail (bottom sheet) ──
async function soOpenDetail(orderId) {
    soCurrentOrderId = orderId;
    try {
        const res = await api(`api/store-orders.php?action=get&id=${orderId}`);
        const order = res.order;
        const allLines = res.lines || [];
        const canSend = order.status === 'pending';

        // Separate active and removed lines
        const activeLines = allLines.filter(l => l.line_status !== 'rejected');
        const removedLines = allLines.filter(l => l.line_status === 'rejected');

        let html = `
            <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Order #${order.id}</h3>
                    <p class="text-[10px] text-gray-500">${formatDate(order.order_date)} &middot; From ${order.chef_name || 'Chef'}</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex flex-wrap gap-1">${soStatusBadge(order.status, parseInt(order.has_dispute) === 1)}</div>
                    <button onclick="closeSheet()" class="p-1 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-5 py-4 scroll-touch">`;

        if (canSend) {
            // ── Pending: editable layout — Requested (locked) → Issuing (editable) + Remove button ──
            html += `
                <div class="flex items-center justify-between px-1 mb-2">
                    <span class="text-[10px] text-gray-500 uppercase tracking-wider font-semibold">${activeLines.length} item${activeLines.length !== 1 ? 's' : ''} to issue</span>
                </div>`;
            html += `<div class="space-y-1.5" id="soActiveLines">`;
            activeLines.forEach(line => {
                const reqQty = parseFloat(line.requested_qty) || 0;
                html += soActiveLineHtml(line, reqQty);
            });
            html += `</div>`;

            // ── Removed lines section ──
            if (removedLines.length > 0) {
                html += `
                    <div class="mt-4 mb-2">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] font-semibold text-red-400 uppercase tracking-wider">Removed Items</span>
                            <span class="text-[10px] text-red-300">(${removedLines.length})</span>
                        </div>
                        <div class="space-y-1.5" id="soRemovedLines">`;
                removedLines.forEach(line => {
                    const reqQty = parseFloat(line.requested_qty) || 0;
                    html += `<div class="bg-red-50/50 rounded-xl px-3 py-2.5 border border-red-100 opacity-60" id="so-line-${line.id}">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-sm text-gray-500 truncate line-through">${line.item_name}</p>
                                <p class="text-[10px] text-gray-400">${line.uom} &middot; was ${reqQty} ${line.uom}</p>
                            </div>
                            <button onclick="soRestoreLine(${line.id}, ${orderId})"
                                class="ml-2 px-3 py-1.5 bg-white border border-green-200 rounded-lg text-[11px] font-semibold text-green-600 hover:bg-green-50 active:bg-green-100 transition compact-btn flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                Restore
                            </button>
                        </div>
                    </div>`;
                });
                html += `</div></div>`;
            }

            // ── Add Item button ──
            html += `
                <button onclick="soShowAddItem(${orderId})" id="soAddItemBtn"
                    class="w-full border-2 border-dashed border-gray-200 hover:border-green-300 rounded-xl py-2.5 text-xs font-medium text-gray-400 hover:text-green-600 transition mt-3 flex items-center justify-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    Add Item
                </button>
                <div id="soAddItemPanel" class="hidden mt-3"></div>`;

        } else {
            // ── Fulfilled / Received: unified table (Req | Sent | Received | Diff) ──
            html += `<div class="max-h-[55vh] overflow-y-auto">
                <table class="w-full text-[11px]">
                    <thead><tr class="bg-gray-50">
                        <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">Item</th>
                        <th class="text-center px-1 py-1.5 text-blue-600 font-semibold">Req</th>
                        <th class="text-center px-1 py-1.5 text-green-600 font-semibold">Sent</th>
                        <th class="text-center px-1 py-1.5 text-orange-600 font-semibold">Received</th>
                        <th class="text-center px-1 py-1.5 text-gray-600 font-semibold">Diff</th>
                    </tr></thead>
                    <tbody>`;
            // Only show active lines in fulfilled/received view
            activeLines.forEach(line => {
                const oq = parseFloat(line.requested_qty) || 0;
                const fq = line.fulfilled_qty !== null ? parseFloat(line.fulfilled_qty) : 0;
                const rq = line.received_qty !== null ? parseFloat(line.received_qty) : 0;
                const diff = rq > 0 ? rq - oq : (fq > 0 ? fq - oq : 0);
                const diffLabel = diff > 0 ? '+' + diff.toFixed(1) : diff < 0 ? diff.toFixed(1) : '—';
                const diffCls = diff > 0 ? 'text-blue-600 font-semibold' : diff < 0 ? 'text-red-600 font-semibold' : 'text-gray-300';
                const rowBg = Math.abs(diff) > 0.01 ? 'bg-red-50/50' : '';
                html += `<tr class="${rowBg}">
                    <td class="px-2 py-1.5 text-gray-700">${line.item_code ? '<span class="text-[9px] text-blue-400 mr-1">#'+line.item_code+'</span>' : ''}${line.item_name}${parseInt(line.is_staple) ? ' <span class="text-[8px] px-1 py-0.5 rounded bg-purple-100 text-purple-500">S</span>' : ''} <span class="text-gray-300 text-[9px]">${line.uom || ''}</span></td>
                    <td class="text-center px-1 py-1.5 text-blue-700 font-medium">${oq > 0 ? oq.toFixed(1) : '—'}</td>
                    <td class="text-center px-1 py-1.5 text-green-700 font-medium">${fq > 0 ? fq.toFixed(1) : '—'}</td>
                    <td class="text-center px-1 py-1.5 text-orange-700 font-medium">${rq > 0 ? rq.toFixed(1) : '—'}</td>
                    <td class="text-center px-1 py-1.5 ${diffCls}">${diffLabel}</td>
                </tr>`;
            });
            html += `</tbody></table></div>`;

            // Show removed lines count if any
            if (removedLines.length > 0) {
                html += `<div class="bg-red-50 border border-red-100 rounded-lg px-3 py-2 mt-3">
                    <p class="text-[11px] text-red-500">${removedLines.length} item${removedLines.length > 1 ? 's' : ''} removed by store: ${removedLines.map(l => l.item_name).join(', ')}</p>
                </div>`;
            }
        }

        // Send button (only for pending orders)
        if (canSend) {
            html += `
                <button onclick="soMarkSent(${order.id})" id="soSendBtn"
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-semibold transition mt-4 flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                    Issue to Kitchen
                </button>`;
        }

        // Status info for sent/received orders
        if (order.status === 'fulfilled') {
            html += `
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 mt-4 text-center">
                    <p class="text-xs text-emerald-700 font-medium">Items issued. Waiting for chef to confirm receipt.</p>
                </div>`;
        }
        if (order.status === 'received') {
            const hasDispute = parseInt(order.has_dispute) === 1;
            if (hasDispute) {
                const disputeCount = activeLines.filter(l => {
                    const sent = l.fulfilled_qty !== null ? parseFloat(l.fulfilled_qty) : parseFloat(l.requested_qty);
                    const recv = l.received_qty !== null ? parseFloat(l.received_qty) : sent;
                    return Math.abs(sent - recv) > 0.01;
                }).length;
                html += `
                    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mt-4 text-center">
                        <p class="text-xs text-red-700 font-semibold">⚠ ${disputeCount} item${disputeCount > 1 ? 's' : ''} disputed — Chef received different quantities than sent</p>
                    </div>`;
            } else {
                html += `
                    <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 mt-4 text-center">
                        <p class="text-xs text-green-700 font-medium">✓ Chef confirmed receipt. All quantities match.</p>
                    </div>`;
            }
        }

        // Print button — uses printStoreOrder() from app.js
        html += `
            <button onclick="printStoreOrder(${order.id})" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 rounded-xl text-xs font-medium transition mt-3 flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                Print Order
            </button>`;

        html += `</div>`;

        openSheet(html);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ── Helper: render a single active line row ──
function soActiveLineHtml(line, reqQty) {
    return `<div class="bg-gray-50 rounded-xl px-3 py-3" id="so-line-${line.id}">
        <div class="flex items-center justify-between mb-2">
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-sm text-gray-800 truncate">${line.item_name}${parseInt(line.is_staple) ? ' <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-600 font-semibold ml-1">Staple</span>' : ''}</p>
                <p class="text-[10px] text-gray-400">${line.item_code ? '#'+line.item_code+' · ' : ''}${line.uom}</p>
            </div>
            <button onclick="soRemoveLine(${line.id}, ${soCurrentOrderId || 0})" title="Remove item"
                class="w-8 h-8 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 text-red-400 hover:text-red-600 flex items-center justify-center transition compact-btn ml-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="flex items-center gap-3">
            <div class="bg-orange-50 border border-orange-200 rounded-lg px-3 py-2 text-center min-w-[65px]">
                <span class="text-[9px] text-orange-400 block">Requested</span>
                <span class="text-base font-bold text-orange-700">${reqQty}</span>
            </div>
            <div class="flex-1">
                <span class="text-[9px] text-green-500 font-medium block mb-1">Issuing</span>
                <div class="flex items-center gap-1">
                    <button onclick="soAdjLine(${line.id}, 'qty', -1)" class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-gray-600 font-bold text-lg flex items-center justify-center compact-btn active:bg-gray-100">-</button>
                    <input type="number" value="${reqQty}" step="0.5" min="0" max="${reqQty * 2}" id="send_${line.id}"
                        class="flex-1 text-center text-lg font-bold border-2 border-green-300 rounded-xl px-1 py-2 focus:outline-none focus:ring-2 focus:ring-green-200 compact-btn bg-green-50 min-w-[60px]">
                    <button onclick="soAdjLine(${line.id}, 'qty', 1)" class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-gray-600 font-bold text-lg flex items-center justify-center compact-btn active:bg-gray-100">+</button>
                </div>
            </div>
        </div>
    </div>`;
}

function soAdjLine(lineId, field, delta) {
    const inputId = field === 'qty' ? `send_${lineId}` : `unit_${lineId}`;
    const input = document.getElementById(inputId);
    if (input) {
        const step = field === 'qty' ? 1 : 0.5;
        input.value = Math.max(field === 'qty' ? 0 : 0.1, (parseFloat(input.value) || 0) + delta * step);
    }
}

// ── Remove a line item ──
async function soRemoveLine(lineId, orderId) {
    if (!await customConfirm('Remove Item', 'Remove this item from the order? You can restore it later.')) return;

    try {
        await api('api/store-orders.php?action=remove_line', {
            method: 'POST',
            body: { line_id: lineId, order_id: orderId }
        });
        showToast('Item removed');
        // Refresh the detail sheet
        soOpenDetail(orderId);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ── Restore a removed line item ──
async function soRestoreLine(lineId, orderId) {
    if (!await customConfirm('Restore Item', 'Add this item back to the order?')) return;
    try {
        await api('api/store-orders.php?action=restore_line', {
            method: 'POST',
            body: { line_id: lineId, order_id: orderId }
        });
        showToast('Item restored');
        // Refresh the detail sheet
        soOpenDetail(orderId);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ── Add Item panel ──
let soItemsList = null; // cached items list

async function soShowAddItem(orderId) {
    const panel = document.getElementById('soAddItemPanel');
    const btn = document.getElementById('soAddItemBtn');
    if (!panel) return;

    // Toggle panel
    if (!panel.classList.contains('hidden')) {
        panel.classList.add('hidden');
        btn.classList.remove('border-green-300', 'text-green-600');
        return;
    }

    btn.classList.add('border-green-300', 'text-green-600');
    panel.classList.remove('hidden');
    panel.innerHTML = `
        <div class="bg-white border border-green-200 rounded-xl p-3 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <div class="relative flex-1">
                    <input type="text" id="soAddItemSearch" placeholder="Search by name or item #..."
                        oninput="soSearchItems(this.value)"
                        class="w-full text-sm border border-gray-200 rounded-lg pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-200 focus:border-green-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-2.5 top-2.5 text-gray-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
            </div>
            <div id="soAddCatFilter" class="flex gap-1 overflow-x-auto pb-1 mb-2 scroll-touch"></div>
            <div id="soAddItemResults" class="max-h-48 overflow-y-auto space-y-1">
                <p class="text-xs text-gray-400 text-center py-3">Type to search or pick a category...</p>
            </div>
        </div>`;

    // Focus search input
    setTimeout(() => {
        const input = document.getElementById('soAddItemSearch');
        if (input) input.focus();
    }, 100);

    // Pre-load items if not cached
    if (!soItemsList) {
        try {
            const res = await api('api/items.php?action=list&active=1');
            soItemsList = res.items || [];
        } catch (e) {
            soItemsList = [];
        }
    }

    // Build category filter chips
    soSelectedCat = '';
    const cats = [...new Set(soItemsList.map(i => i.category).filter(Boolean))].sort();
    const foodCats = ['Dry', 'Dairy', 'Veg', 'Meat', 'Fruits', 'Juice', 'Bar'];
    const priorityCats = foodCats.filter(c => cats.includes(c));
    const otherCats = cats.filter(c => !foodCats.includes(c));
    const orderedCats = [...priorityCats, ...otherCats];

    const catContainer = document.getElementById('soAddCatFilter');
    if (catContainer) {
        catContainer.innerHTML = orderedCats.map(c =>
            `<button onclick="soFilterByCat('${c}')" class="so-cat-btn px-2.5 py-1 rounded-full text-[10px] font-medium whitespace-nowrap bg-gray-100 text-gray-600 hover:bg-green-100 hover:text-green-700 transition">${c}</button>`
        ).join('');
    }
}

let soSelectedCat = '';

function soFilterByCat(cat) {
    soSelectedCat = (soSelectedCat === cat) ? '' : cat;
    document.querySelectorAll('.so-cat-btn').forEach(btn => {
        btn.className = btn.textContent === soSelectedCat
            ? 'so-cat-btn px-2.5 py-1 rounded-full text-[10px] font-medium whitespace-nowrap bg-green-600 text-white'
            : 'so-cat-btn px-2.5 py-1 rounded-full text-[10px] font-medium whitespace-nowrap bg-gray-100 text-gray-600 hover:bg-green-100 hover:text-green-700 transition';
    });
    const searchVal = document.getElementById('soAddItemSearch')?.value || '';
    soSearchItems(searchVal);
}

function soSearchItems(query) {
    const results = document.getElementById('soAddItemResults');
    if (!results || !soItemsList) return;

    let filtered = soItemsList;
    if (soSelectedCat) {
        filtered = filtered.filter(item => item.category === soSelectedCat);
    }

    const q = (query || '').toLowerCase();
    if (q.length >= 2) {
        filtered = filtered.filter(item =>
            item.name.toLowerCase().includes(q) || (item.code && item.code.toLowerCase().includes(q))
        );
    } else if (!soSelectedCat) {
        results.innerHTML = '<p class="text-xs text-gray-400 text-center py-3">Type to search or pick a category above</p>';
        return;
    }

    const matches = filtered.slice(0, 20);
    if (matches.length === 0) {
        results.innerHTML = '<p class="text-xs text-gray-400 text-center py-3">No items found</p>';
        return;
    }

    results.innerHTML = matches.map(item => {
        const safeName = (item.name || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        const safeUom = (item.uom || 'kg').replace(/&/g,'&amp;').replace(/</g,'&lt;');
        const safeCat = (item.category || '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
        const safeCode = (item.code || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        return `
        <div class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-green-50 transition" data-item-id="${item.id}" data-item-name="${safeName}" data-item-uom="${safeUom}">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800">${safeCode ? '<span class="text-blue-500 text-[10px] mr-1">#'+safeCode+'</span>' : ''}${safeName}</p>
                <p class="text-[10px] text-gray-400">${safeCat} · ${safeUom}</p>
            </div>
            <input type="number" step="0.5" min="0.5" value="1" placeholder="Qty"
                class="w-16 text-center text-sm border border-gray-200 rounded-lg py-1.5 focus:outline-none focus:ring-2 focus:ring-green-200 bg-white">
            <button onclick="soAddItemFromRow(this)"
                class="px-3 py-1.5 bg-green-500 text-white rounded-lg text-xs font-semibold hover:bg-green-600 transition compact-btn">Add</button>
        </div>`;
    }).join('');
}

async function soAddItemFromRow(btn) {
    const row = btn.closest('[data-item-id]');
    const itemId = parseInt(row.dataset.itemId);
    const itemName = row.dataset.itemName;
    const qtyInput = row.querySelector('input[type="number"]');
    const qty = parseFloat(qtyInput?.value) || 0;

    if (qty <= 0) {
        showToast('Enter a valid quantity', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = '...';

    try {
        await api('api/store-orders.php?action=add_line', {
            method: 'POST',
            body: { order_id: soCurrentOrderId, item_id: itemId, qty: qty }
        });
        showToast(`${itemName} added`);
        soOpenDetail(soCurrentOrderId);
    } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false;
        btn.textContent = 'Add';
    }
}

// ── Mark order as sent ──
async function soMarkSent(orderId) {
    const sendInputs = document.querySelectorAll('[id^="send_"]');
    const lines = [];
    sendInputs.forEach(input => {
        const id = parseInt(input.id.replace('send_', ''));
        const unitInput = document.getElementById(`unit_${id}`);
        lines.push({
            id,
            fulfilled_qty: parseFloat(input.value) || 0,
            unit_size: unitInput ? parseFloat(unitInput.value) || null : null
        });
    });

    if (!await customConfirm('Issue Items', `Issue ${lines.length} items to kitchen?`)) return;

    const btn = document.getElementById('soSendBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin inline-block mr-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Issuing...';
    }

    try {
        await api('api/store-orders.php?action=mark_sent', {
            method: 'POST',
            body: { order_id: orderId, lines }
        });
        closeSheet();
        showToast('Items issued to kitchen!');
        soLoad();
    } catch (err) {
        showToast(err.message, 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Issue to Kitchen';
        }
    }
}
</script>
