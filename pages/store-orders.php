<!-- Store Orders — Storekeeper view -->
<div id="storeOrdersPage">

    <!-- Status Tabs -->
    <div class="flex gap-1.5 overflow-x-auto pb-2 mb-4 -mx-1 px-1 scroll-touch">
        <button onclick="filterOrders('all')" id="tab-all"
            class="tab-btn flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-green-600 text-white">
            All <span id="count-all" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="filterOrders('pending')" id="tab-pending"
            class="tab-btn flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Pending <span id="count-pending" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="filterOrders('reviewing')" id="tab-reviewing"
            class="tab-btn flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Reviewing <span id="count-reviewing" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="filterOrders('approved')" id="tab-approved"
            class="tab-btn flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Approved <span id="count-approved" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="filterOrders('partial')" id="tab-partial"
            class="tab-btn flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Partial <span id="count-partial" class="text-[10px] opacity-80">0</span>
        </button>
        <button onclick="filterOrders('fulfilled')" id="tab-fulfilled"
            class="tab-btn flex items-center gap-1.5 px-3 py-2 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600">
            Fulfilled <span id="count-fulfilled" class="text-[10px] opacity-80">0</span>
        </button>
    </div>

    <!-- Orders List -->
    <div id="ordersList" class="space-y-3">
        <div class="text-center py-8 text-gray-400 text-sm">Loading orders...</div>
    </div>
</div>

<script>
let currentFilter = 'all';
let ordersData = [];

// ── Load orders ──
async function loadOrders() {
    try {
        const res = await api(`api/store-orders.php?action=list&status=${currentFilter}`);
        ordersData = res.orders || [];

        // Update tab counts
        const counts = res.counts || {};
        ['all', 'pending', 'reviewing', 'approved', 'partial', 'fulfilled', 'rejected'].forEach(s => {
            const el = document.getElementById('count-' + s);
            if (el) el.textContent = counts[s] || 0;
        });

        renderOrders();
    } catch (err) {
        document.getElementById('ordersList').innerHTML =
            `<div class="text-center py-8 text-red-500 text-sm">${err.message}</div>`;
    }
}

function filterOrders(status) {
    currentFilter = status;

    // Update tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.className = btn.className.replace(/bg-green-600 text-white/g, 'bg-gray-100 text-gray-600');
    });
    const active = document.getElementById('tab-' + status);
    if (active) {
        active.className = active.className.replace(/bg-gray-100 text-gray-600/g, 'bg-green-600 text-white');
    }

    loadOrders();
}

function statusBadge(status) {
    const map = {
        pending: 'bg-amber-100 text-amber-700',
        reviewing: 'bg-blue-100 text-blue-700',
        approved: 'bg-green-100 text-green-700',
        partial: 'bg-orange-100 text-orange-700',
        rejected: 'bg-red-100 text-red-700',
        fulfilled: 'bg-emerald-100 text-emerald-700',
    };
    const cls = map[status] || 'bg-gray-100 text-gray-600';
    return `<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${cls}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

function renderOrders() {
    const list = document.getElementById('ordersList');
    if (ordersData.length === 0) {
        list.innerHTML = `
            <div class="text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-gray-300 mb-3"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                <p class="text-gray-500 text-sm">No orders found</p>
            </div>`;
        return;
    }

    list.innerHTML = ordersData.map(order => {
        const date = formatDate(order.order_date);
        const meal = order.meal.charAt(0).toUpperCase() + order.meal.slice(1);
        const isActionable = ['pending', 'reviewing', 'approved', 'partial'].includes(order.status);

        return `
            <div onclick="openOrderDetail(${order.id})"
                 class="bg-white rounded-xl border ${isActionable ? 'border-green-200 shadow-sm' : 'border-gray-100'} p-4 active:bg-gray-50 cursor-pointer transition">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <p class="font-semibold text-sm text-gray-800">${date}</p>
                        <p class="text-xs text-gray-500 mt-0.5">${meal} &middot; ${order.total_items} item${order.total_items !== 1 ? 's' : ''}</p>
                    </div>
                    ${statusBadge(order.status)}
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] text-gray-400">By ${order.chef_name || 'Unknown'}</p>
                    <span class="text-[11px] text-gray-400">#${order.id}</span>
                </div>
            </div>`;
    }).join('');
}

// ── Order Detail ──
async function openOrderDetail(orderId) {
    try {
        const res = await api(`api/store-orders.php?action=get&id=${orderId}`);
        const order = res.order;
        const lines = res.lines || [];

        const canReview = ['pending', 'reviewing'].includes(order.status);
        const canFulfill = ['approved', 'partial'].includes(order.status);
        const meal = order.meal.charAt(0).toUpperCase() + order.meal.slice(1);

        let html = `
            <div class="p-4">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Order #${order.id}</h3>
                        <p class="text-xs text-gray-500">${formatDate(order.order_date)} &middot; ${meal}</p>
                    </div>
                    ${statusBadge(order.status)}
                </div>

                <div class="text-xs text-gray-500 mb-4">Submitted by <span class="font-medium text-gray-700">${order.chef_name || 'Unknown'}</span></div>

                <!-- Line Items -->
                <div class="space-y-2" id="orderLines">`;

        lines.forEach((line, i) => {
            const lineStatusCls = {
                pending: 'border-l-amber-400',
                approved: 'border-l-green-400',
                adjusted: 'border-l-blue-400',
                rejected: 'border-l-red-400',
            };
            const borderCls = lineStatusCls[line.status] || 'border-l-gray-200';

            html += `
                    <div class="bg-gray-50 rounded-lg border-l-4 ${borderCls} p-3" id="line-${line.id}">
                        <div class="flex items-start justify-between mb-1.5">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-gray-800 truncate">${line.item_name}</p>
                                <p class="text-[11px] text-gray-500">Requested: <span class="font-semibold text-gray-700">${parseFloat(line.requested_qty)} ${line.uom}</span></p>
                            </div>
                            ${statusBadge(line.status)}
                        </div>`;

            // Show approved/fulfilled qty if set
            if (line.approved_qty !== null && line.status !== 'pending') {
                html += `<p class="text-[11px] text-gray-500 mt-1">Approved: <span class="font-semibold">${parseFloat(line.approved_qty)} ${line.uom}</span></p>`;
            }
            if (line.fulfilled_qty !== null) {
                html += `<p class="text-[11px] text-gray-500">Fulfilled: <span class="font-semibold">${parseFloat(line.fulfilled_qty)} ${line.uom}</span></p>`;
            }
            if (line.store_notes) {
                html += `<p class="text-[11px] text-gray-400 italic mt-1">"${line.store_notes}"</p>`;
            }

            // Review actions (only if pending)
            if (canReview && line.status === 'pending') {
                html += `
                        <div class="flex items-center gap-2 mt-3 pt-2 border-t border-gray-200">
                            <button onclick="reviewLine(${line.id}, 'approved')"
                                class="flex-1 py-2 bg-green-600 text-white text-xs font-medium rounded-lg active:bg-green-700">
                                Approve
                            </button>
                            <button onclick="showAdjustForm(${line.id}, ${parseFloat(line.requested_qty)}, '${line.uom}')"
                                class="flex-1 py-2 bg-blue-600 text-white text-xs font-medium rounded-lg active:bg-blue-700">
                                Adjust
                            </button>
                            <button onclick="reviewLine(${line.id}, 'rejected')"
                                class="px-3 py-2 bg-red-100 text-red-600 text-xs font-medium rounded-lg active:bg-red-200">
                                Reject
                            </button>
                        </div>`;
            }

            // Fulfill input (approved/partial orders)
            if (canFulfill && line.status !== 'rejected') {
                const fulfilledVal = line.fulfilled_qty !== null ? parseFloat(line.fulfilled_qty) : (line.approved_qty !== null ? parseFloat(line.approved_qty) : parseFloat(line.requested_qty));
                html += `
                        <div class="flex items-center gap-2 mt-3 pt-2 border-t border-gray-200">
                            <label class="text-[11px] text-gray-500 whitespace-nowrap">Fulfilled qty:</label>
                            <input type="number" step="0.1" min="0" value="${fulfilledVal}"
                                class="flex-1 px-2 py-1.5 border border-gray-200 rounded-lg text-sm text-center"
                                id="fulfill-${line.id}">
                            <span class="text-xs text-gray-400">${line.uom}</span>
                        </div>`;
            }

            html += `</div>`;
        });

        html += `</div>`;

        // Bottom actions
        if (canReview) {
            const pendingLines = lines.filter(l => l.status === 'pending').length;
            if (pendingLines > 0) {
                html += `
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <button onclick="approveAllLines(${order.id})"
                        class="w-full py-3 bg-green-600 text-white text-sm font-semibold rounded-xl active:bg-green-700">
                        Approve All (${pendingLines} items)
                    </button>
                </div>`;
            }
        }

        if (canFulfill) {
            html += `
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <button onclick="fulfillOrder(${order.id})" id="fulfillBtn"
                        class="w-full py-3 bg-emerald-600 text-white text-sm font-semibold rounded-xl active:bg-emerald-700">
                        Mark as Fulfilled
                    </button>
                </div>`;
        }

        // Notes section
        html += `
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <label class="text-xs font-medium text-gray-600 mb-1.5 block">Order Notes</label>
                    <textarea id="orderNotes" rows="2" placeholder="Add notes..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm resize-none">${order.notes || ''}</textarea>
                    <button onclick="saveNotes(${order.id})" class="mt-2 px-4 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200">
                        Save Notes
                    </button>
                </div>
            </div>`;

        openSheet(html);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ── Adjust form (inline) ──
function showAdjustForm(lineId, requestedQty, uom) {
    const lineEl = document.getElementById('line-' + lineId);
    if (!lineEl) return;

    // Remove existing actions and add adjust form
    const existingActions = lineEl.querySelector('.flex.items-center.gap-2.mt-3.pt-2.border-t');
    if (existingActions) {
        existingActions.innerHTML = `
            <div class="w-full space-y-2">
                <div class="flex items-center gap-2">
                    <label class="text-[11px] text-gray-500 whitespace-nowrap">New qty:</label>
                    <input type="number" step="0.1" min="0" value="${requestedQty}" id="adjustQty-${lineId}"
                        class="flex-1 px-2 py-1.5 border border-blue-300 rounded-lg text-sm text-center focus:ring-1 focus:ring-blue-400">
                    <span class="text-xs text-gray-400">${uom}</span>
                </div>
                <input type="text" placeholder="Reason for adjustment..." id="adjustNote-${lineId}"
                    class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs">
                <div class="flex gap-2">
                    <button onclick="submitAdjust(${lineId})"
                        class="flex-1 py-2 bg-blue-600 text-white text-xs font-medium rounded-lg">Confirm</button>
                    <button onclick="openOrderDetail(currentDetailOrderId)"
                        class="px-3 py-2 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg">Cancel</button>
                </div>
            </div>`;
    }
}

let currentDetailOrderId = null;

// Wrap openOrderDetail to track current order
const _origOpenOrderDetail = openOrderDetail;
openOrderDetail = async function(orderId) {
    currentDetailOrderId = orderId;
    await _origOpenOrderDetail(orderId);
};

async function reviewLine(lineId, status) {
    try {
        await api('api/store-orders.php?action=review_line', {
            method: 'POST',
            body: { line_id: lineId, status }
        });
        showToast(status === 'approved' ? 'Item approved' : status === 'rejected' ? 'Item rejected' : 'Updated', 'success');
        if (currentDetailOrderId) openOrderDetail(currentDetailOrderId);
        loadOrders();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function submitAdjust(lineId) {
    const qty = document.getElementById('adjustQty-' + lineId)?.value;
    const notes = document.getElementById('adjustNote-' + lineId)?.value;

    if (!qty || parseFloat(qty) <= 0) {
        showToast('Enter a valid quantity', 'warning');
        return;
    }

    try {
        await api('api/store-orders.php?action=review_line', {
            method: 'POST',
            body: { line_id: lineId, status: 'adjusted', approved_qty: parseFloat(qty), notes }
        });
        showToast('Quantity adjusted', 'success');
        if (currentDetailOrderId) openOrderDetail(currentDetailOrderId);
        loadOrders();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function approveAllLines(orderId) {
    try {
        await api('api/store-orders.php?action=approve_all', {
            method: 'POST',
            body: { order_id: orderId }
        });
        showToast('All items approved!', 'success');
        openOrderDetail(orderId);
        loadOrders();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function fulfillOrder(orderId) {
    // Collect fulfilled qtys from inputs
    const inputs = document.querySelectorAll('[id^="fulfill-"]');
    const lines = [];
    inputs.forEach(input => {
        const id = parseInt(input.id.replace('fulfill-', ''));
        lines.push({ id, fulfilled_qty: parseFloat(input.value) || 0 });
    });

    const btn = document.getElementById('fulfillBtn');
    if (btn) setLoading(btn, true);

    try {
        await api('api/store-orders.php?action=fulfill', {
            method: 'POST',
            body: { order_id: orderId, lines }
        });
        showToast('Order fulfilled!', 'success');
        closeSheet();
        loadOrders();
    } catch (err) {
        showToast(err.message, 'error');
        if (btn) setLoading(btn, false);
    }
}

async function saveNotes(orderId) {
    const notes = document.getElementById('orderNotes')?.value || '';
    try {
        await api('api/store-orders.php?action=add_notes', {
            method: 'POST',
            body: { order_id: orderId, notes }
        });
        showToast('Notes saved', 'success');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ── Init ──
loadOrders();
</script>
