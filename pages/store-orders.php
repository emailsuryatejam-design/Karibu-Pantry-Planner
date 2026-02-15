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
        ['all', 'pending', 'fulfilled', 'received'].forEach(s => {
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

        return `
            <div onclick="soOpenDetail(${order.id})"
                 class="bg-white rounded-xl border ${borderCls} p-4 active:bg-gray-50 cursor-pointer transition">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <p class="font-semibold text-sm text-gray-800">${date}</p>
                        <p class="text-xs text-gray-500 mt-0.5">${order.total_items} item${order.total_items !== 1 ? 's' : ''}</p>
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
    try {
        const res = await api(`api/store-orders.php?action=get&id=${orderId}`);
        const order = res.order;
        const lines = res.lines || [];
        const canSend = order.status === 'pending';

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
            // Column header for pending orders
            html += `
                <div class="grid grid-cols-[1fr_80px_100px] gap-2 px-1 mb-1">
                    <span class="text-[9px] text-gray-400 uppercase tracking-wider font-semibold">Item</span>
                    <span class="text-[9px] text-orange-500 uppercase tracking-wider font-semibold text-center">Requested</span>
                    <span class="text-[9px] text-green-600 uppercase tracking-wider font-semibold text-center">Issuing</span>
                </div>`;
        }

        html += `<div class="space-y-1.5">`;

        lines.forEach(line => {
            const reqQty = parseFloat(line.requested_qty) || 0;
            const sentQty = line.fulfilled_qty !== null ? parseFloat(line.fulfilled_qty) : reqQty;
            const unitSize = line.unit_size ? parseFloat(line.unit_size) : null;

            html += `<div class="bg-gray-50 rounded-xl px-3 py-2.5">`;

            if (canSend) {
                // ── Pending: table-like layout — Requested (locked) → Issuing (editable) ──
                html += `
                    <div class="grid grid-cols-[1fr_80px_100px] gap-2 items-center">
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-gray-800 truncate">${line.item_name}</p>
                            <p class="text-[10px] text-gray-400">${line.uom}</p>
                        </div>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg py-1.5 text-center">
                            <span class="text-sm font-bold text-orange-700">${reqQty}</span>
                            <span class="text-[10px] text-orange-500 ml-0.5">${line.uom}</span>
                        </div>
                        <div class="flex items-center justify-center gap-0.5">
                            <button onclick="soAdjLine(${line.id}, 'qty', -1)" class="w-7 h-7 rounded bg-white border border-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center compact-btn">-</button>
                            <input type="number" value="${reqQty}" step="0.5" min="0" id="send_${line.id}"
                                class="w-14 text-center text-sm font-semibold border border-green-300 rounded-lg px-0.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-green-200 compact-btn bg-green-50">
                            <button onclick="soAdjLine(${line.id}, 'qty', 1)" class="w-7 h-7 rounded bg-white border border-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center compact-btn">+</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-1.5 pl-0">
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-gray-400">Pack:</span>
                            <input type="number" value="1" step="0.5" min="0.1" id="unit_${line.id}"
                                class="w-12 text-center text-[11px] border border-gray-200 rounded px-0.5 py-0.5 focus:outline-none focus:ring-1 focus:ring-green-200 compact-btn bg-white">
                            <span class="text-[10px] text-gray-400">${line.uom}</span>
                        </div>
                    </div>`;
            } else if (order.status === 'received') {
                // ── Received: show Sent vs Chef Received vs Diff (dispute view) ──
                const recvQty = line.received_qty !== null ? parseFloat(line.received_qty) : sentQty;
                const diff = recvQty - sentQty;
                const diffLabel = diff > 0 ? `+${diff}` : diff < 0 ? `${diff}` : '—';
                const diffColor = diff > 0 ? 'text-blue-600' : diff < 0 ? 'text-red-600' : 'text-gray-400';
                const diffBg = diff !== 0 ? 'bg-red-50' : 'bg-gray-50';
                const isDispute = Math.abs(diff) > 0.01;

                html += `
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-semibold text-sm text-gray-800 truncate flex-1">${line.item_name}</p>
                        ${isDispute ? '<span class="text-[10px] text-red-600 font-semibold">⚠ Dispute</span>' : ''}
                        ${unitSize ? `<span class="text-[10px] text-gray-400 ml-1">${unitSize} ${line.uom} packs</span>` : ''}
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="bg-green-50 rounded-lg py-1.5">
                            <p class="text-[9px] text-gray-500 uppercase tracking-wider font-medium">I Sent</p>
                            <p class="text-sm font-bold text-green-700">${sentQty} <span class="text-[10px] font-normal text-green-500">${line.uom}</span></p>
                        </div>
                        <div class="${isDispute ? 'bg-red-50 border border-red-200' : 'bg-orange-50'} rounded-lg py-1.5">
                            <p class="text-[9px] text-gray-500 uppercase tracking-wider font-medium">Chef Got</p>
                            <p class="text-sm font-bold ${isDispute ? 'text-red-700' : 'text-orange-700'}">${recvQty} <span class="text-[10px] font-normal ${isDispute ? 'text-red-500' : 'text-orange-500'}">${line.uom}</span></p>
                        </div>
                        <div class="${diffBg} rounded-lg py-1.5">
                            <p class="text-[9px] text-gray-500 uppercase tracking-wider font-medium">Diff</p>
                            <p class="text-sm font-bold ${diffColor}">${diffLabel}</p>
                        </div>
                    </div>`;
            } else {
                // ── Fulfilled (sent but not yet received): show Asked vs Issued vs Diff ──
                const diff = sentQty - reqQty;
                const diffLabel = diff > 0 ? `+${diff}` : diff < 0 ? `${diff}` : '—';
                const diffColor = diff > 0 ? 'text-blue-600' : diff < 0 ? 'text-red-600' : 'text-gray-400';
                const diffBg = diff > 0 ? 'bg-blue-50' : diff < 0 ? 'bg-red-50' : 'bg-gray-50';

                html += `
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-semibold text-sm text-gray-800 truncate flex-1">${line.item_name}</p>
                        ${unitSize ? `<span class="text-[10px] text-gray-400">${unitSize} ${line.uom} packs</span>` : ''}
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="bg-orange-50 rounded-lg py-1.5">
                            <p class="text-[9px] text-gray-500 uppercase tracking-wider font-medium">Asked</p>
                            <p class="text-sm font-bold text-orange-700">${reqQty} <span class="text-[10px] font-normal text-orange-500">${line.uom}</span></p>
                        </div>
                        <div class="bg-green-50 rounded-lg py-1.5">
                            <p class="text-[9px] text-gray-500 uppercase tracking-wider font-medium">Issued</p>
                            <p class="text-sm font-bold text-green-700">${sentQty} <span class="text-[10px] font-normal text-green-500">${line.uom}</span></p>
                        </div>
                        <div class="${diffBg} rounded-lg py-1.5">
                            <p class="text-[9px] text-gray-500 uppercase tracking-wider font-medium">Diff</p>
                            <p class="text-sm font-bold ${diffColor}">${diffLabel}</p>
                        </div>
                    </div>`;
            }

            html += `</div>`;
        });

        html += `</div>`;

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
                // Count disputed lines
                const disputeCount = lines.filter(l => {
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

        // Download/Print button for all statuses
        html += `
            <button onclick="soDownloadOrder(${order.id})" class="w-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 rounded-xl text-xs font-medium transition mt-3 flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download / Print
            </button>`;

        html += `</div>`;

        openSheet(html);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function soAdjLine(lineId, field, delta) {
    const inputId = field === 'qty' ? `send_${lineId}` : `unit_${lineId}`;
    const input = document.getElementById(inputId);
    if (input) {
        const step = field === 'qty' ? 1 : 0.5;
        input.value = Math.max(field === 'qty' ? 0 : 0.1, (parseFloat(input.value) || 0) + delta * step);
    }
}

// ── Download / Print order ──
async function soDownloadOrder(orderId) {
    try {
        const res = await api(`api/store-orders.php?action=get&id=${orderId}`);
        const order = res.order;
        const lines = res.lines || [];
        const date = formatDate(order.order_date);
        const hasDispute = parseInt(order.has_dispute) === 1;
        const statusLabel = order.status.charAt(0).toUpperCase() + order.status.slice(1);

        let headerRow = '';
        let bodyRows = '';

        if (order.status === 'received') {
            // Received: show Sent vs Chef Got vs Diff
            headerRow = '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ddd">Item</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">UOM</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">Sent</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">Chef Got</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">Diff</th>';
            lines.forEach(l => {
                const sent = l.fulfilled_qty !== null ? parseFloat(l.fulfilled_qty) : parseFloat(l.requested_qty);
                const recv = l.received_qty !== null ? parseFloat(l.received_qty) : sent;
                const diff = recv - sent;
                const diffStr = diff > 0 ? `+${diff}` : diff < 0 ? `${diff}` : '—';
                const diffStyle = Math.abs(diff) > 0.01 ? (diff > 0 ? 'color:#2563eb;font-weight:700' : 'color:#dc2626;font-weight:700') : 'color:#999';
                const rowBg = Math.abs(diff) > 0.01 ? 'background:#fef2f2' : '';
                bodyRows += `<tr style="${rowBg}"><td style="padding:5px 8px;border-bottom:1px solid #eee">${l.item_name}${Math.abs(diff) > 0.01 ? ' ⚠' : ''}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center">${l.uom}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center">${sent}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center;font-weight:600">${recv}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center;${diffStyle}">${diffStr}</td></tr>`;
            });
        } else if (order.status === 'fulfilled') {
            // Fulfilled: show Requested vs Issued
            headerRow = '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ddd">Item</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">UOM</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">Requested</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">Issued</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">Pack Size</th>';
            lines.forEach(l => {
                const req = parseFloat(l.requested_qty) || 0;
                const sent = l.fulfilled_qty !== null ? parseFloat(l.fulfilled_qty) : req;
                const pack = l.unit_size ? parseFloat(l.unit_size) : '';
                bodyRows += `<tr><td style="padding:5px 8px;border-bottom:1px solid #eee">${l.item_name}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center">${l.uom}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center">${req}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center;font-weight:600">${sent}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center">${pack ? pack + ' ' + l.uom : ''}</td></tr>`;
            });
        } else {
            // Pending: show requested items
            headerRow = '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ddd">Item</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">UOM</th><th style="padding:6px 8px;border-bottom:2px solid #ddd;text-align:center">Requested Qty</th>';
            lines.forEach(l => {
                bodyRows += `<tr><td style="padding:5px 8px;border-bottom:1px solid #eee">${l.item_name}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center">${l.uom}</td><td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:center;font-weight:600">${parseFloat(l.requested_qty) || 0}</td></tr>`;
            });
        }

        const disputeNote = hasDispute ? '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px;margin-top:16px;font-size:12px;color:#b91c1c;font-weight:600">⚠ This order has disputed items — Chef received different quantities than sent</div>' : '';

        const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Order #${order.id}</title><style>
            body{font-family:Arial,sans-serif;margin:20px;color:#333}
            h1{font-size:18px;margin:0 0 4px}
            .sub{font-size:12px;color:#666;margin-bottom:16px}
            table{width:100%;border-collapse:collapse;font-size:13px}
            th{background:#f9fafb;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#666}
            .footer{margin-top:20px;font-size:11px;color:#999;border-top:1px solid #eee;padding-top:10px}
            @media print{body{margin:10px}button{display:none!important}}
        </style></head><body>
            <div style="display:flex;justify-content:space-between;align-items:start">
                <div><h1>Store Order #${order.id}</h1><div class="sub">${date} · ${statusLabel} · ${lines.length} items · From ${order.chef_name || 'Chef'}</div></div>
                <button onclick="window.print()" style="padding:8px 16px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer">Print</button>
            </div>
            <table><thead><tr>${headerRow}</tr></thead><tbody>${bodyRows}</tbody></table>
            ${disputeNote}
            <div class="footer">Karibu Pantry Planner · Printed ${new Date().toLocaleString()}</div>
        </body></html>`;

        const win = window.open('', '_blank');
        win.document.write(html);
        win.document.close();
    } catch (err) {
        showToast('Failed to generate printout', 'error');
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

    if (!confirm(`Issue ${lines.length} items to kitchen?`)) return;

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
