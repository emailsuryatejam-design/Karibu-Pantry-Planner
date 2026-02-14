<!-- Daily Groceries Page — All ingredients needed for the day -->
<div id="groceriesApp">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-600"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                Daily Groceries
            </h1>
            <p class="text-xs text-gray-500 mt-0.5" id="gSubtitle">Ingredients needed for today</p>
        </div>
    </div>

    <!-- Date Picker -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-100 px-4 py-3 mb-3">
        <button onclick="gNavDate(-1)" class="p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="text-center">
            <p class="text-sm font-semibold text-gray-900" id="gDateDisplay"></p>
            <span id="gTodayBadge" class="text-[10px] text-green-600 font-medium hidden">Today</span>
            <button id="gGoTodayBtn" onclick="gGoToday()" class="text-[10px] text-orange-600 font-medium hidden">Go to Today</button>
        </div>
        <button onclick="gNavDate(1)" class="p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>

    <!-- Error -->
    <div id="gErrorBox" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-3">
        <p class="text-sm text-red-700" id="gErrorText"></p>
    </div>

    <!-- Loading -->
    <div id="gLoading" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-orange-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading groceries...</p>
    </div>

    <!-- Empty State: no confirmed plans -->
    <div id="gEmpty" class="hidden bg-white rounded-xl border border-gray-100 p-6 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-300 mx-auto mb-2"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
        <p class="text-sm text-gray-500 mb-1" id="gEmptyTitle">No confirmed menu plans</p>
        <p class="text-xs text-gray-400" id="gEmptyDesc">Confirm your menu plan first to see groceries needed</p>
    </div>

    <!-- Main Content -->
    <div id="gContent" class="hidden">

        <!-- Order Status Banner -->
        <div id="gOrderBanner" class="hidden rounded-xl px-4 py-3 mb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" id="gOrderStatus"></span>
                    <span class="text-xs" id="gOrderInfo"></span>
                </div>
            </div>
        </div>

        <!-- Plans Summary -->
        <div id="gPlansSummary" class="bg-orange-50 border border-orange-200 rounded-xl px-4 py-2.5 mb-3">
            <p class="text-xs text-orange-700" id="gPlansInfo"></p>
        </div>

        <!-- Column Headers -->
        <div class="bg-white rounded-t-xl border border-gray-100 border-b-0">
            <div class="grid grid-cols-[1fr_70px_80px] gap-1 px-3 py-2 bg-gray-50 rounded-t-xl text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                <span>Item</span>
                <span class="text-center">Need</span>
                <span class="text-center">Order</span>
            </div>
        </div>

        <!-- Ingredient Rows -->
        <div id="gIngredients" class="bg-white border border-gray-100 border-t-0 rounded-b-xl overflow-hidden"></div>

        <!-- Total items count -->
        <p class="text-[10px] text-gray-400 text-right mt-1 mr-1" id="gItemCount"></p>

        <!-- Submit Order Button -->
        <button onclick="gSubmitOrder()" id="gSubmitBtn"
            class="hidden w-full bg-orange-600 hover:bg-orange-700 text-white py-3.5 rounded-xl text-sm font-semibold transition active:bg-orange-800 mt-3 flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            Submit Order to Store
        </button>

        <!-- Confirm Receipt Button (when order fulfilled/sent by store) -->
        <button onclick="gShowReceiptSheet()" id="gReceiptBtn"
            class="hidden w-full bg-green-600 hover:bg-green-700 text-white py-3.5 rounded-xl text-sm font-semibold transition active:bg-green-800 mt-3 flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
            Confirm Receipt
        </button>
    </div>
</div>

<script>
let gDate = todayStr();
let gPlans = [];
let gItems = [];
let gOrder = null;
let gOrderLines = [];
let gEditedQtys = {}; // track edited order quantities

gLoadData();

function gNavDate(days) { gDate = changeDate(gDate, days); gRenderDate(); gLoadData(); }
function gGoToday() { gDate = todayStr(); gRenderDate(); gLoadData(); }

function gRenderDate() {
    document.getElementById('gDateDisplay').textContent = formatDate(gDate);
    const isToday = gDate === todayStr();
    document.getElementById('gTodayBadge').classList.toggle('hidden', !isToday);
    document.getElementById('gGoTodayBtn').classList.toggle('hidden', isToday);
}

async function gLoadData() {
    gRenderDate();
    gEditedQtys = {};
    document.getElementById('gLoading').classList.remove('hidden');
    ['gContent', 'gEmpty', 'gErrorBox', 'gOrderBanner'].forEach(id =>
        document.getElementById(id).classList.add('hidden'));

    try {
        const data = await api(`api/daily-groceries.php?action=get&date=${gDate}`);
        gPlans = data.plans || [];
        gItems = data.items || [];
        gOrder = data.order;
        gOrderLines = data.order_lines || [];
        gRender();
    } catch (err) {
        document.getElementById('gErrorText').textContent = err.message;
        document.getElementById('gErrorBox').classList.remove('hidden');
    } finally {
        document.getElementById('gLoading').classList.add('hidden');
    }
}

function gRender() {
    // No confirmed plans
    if (gPlans.length === 0) {
        document.getElementById('gEmpty').classList.remove('hidden');
        if (gItems.length === 0) {
            document.getElementById('gEmptyTitle').textContent = 'No confirmed menu plans';
            document.getElementById('gEmptyDesc').textContent = 'Confirm your menu plan first to see groceries needed';
        }
        document.getElementById('gSubtitle').textContent = 'No confirmed plans for this date';
        return;
    }

    document.getElementById('gContent').classList.remove('hidden');

    // Plans summary
    const meals = gPlans.map(p => p.meal.charAt(0).toUpperCase() + p.meal.slice(1));
    document.getElementById('gPlansInfo').textContent = `${meals.join(' + ')} confirmed \u00b7 ${gItems.length} ingredients`;

    // Order banner
    if (gOrder) {
        const banner = document.getElementById('gOrderBanner');
        banner.classList.remove('hidden');
        const statusEl = document.getElementById('gOrderStatus');
        const statusMap = {
            pending: { bg: 'bg-amber-50 border border-amber-200', badge: 'bg-amber-100 text-amber-700', text: 'text-amber-700', label: 'Pending' },
            reviewing: { bg: 'bg-blue-50 border border-blue-200', badge: 'bg-blue-100 text-blue-700', text: 'text-blue-700', label: 'Reviewing' },
            approved: { bg: 'bg-green-50 border border-green-200', badge: 'bg-green-100 text-green-700', text: 'text-green-700', label: 'Approved' },
            partial: { bg: 'bg-orange-50 border border-orange-200', badge: 'bg-orange-100 text-orange-700', text: 'text-orange-700', label: 'Partial' },
            fulfilled: { bg: 'bg-emerald-50 border border-emerald-200', badge: 'bg-emerald-100 text-emerald-700', text: 'text-emerald-700', label: 'Sent by Store' },
            received: { bg: 'bg-green-50 border border-green-200', badge: 'bg-green-100 text-green-700', text: 'text-green-700', label: 'Received' },
        };
        const s = statusMap[gOrder.status] || statusMap.pending;
        banner.className = `rounded-xl px-4 py-3 mb-3 ${s.bg}`;
        statusEl.textContent = s.label;
        statusEl.className = `text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ${s.badge}`;
        document.getElementById('gOrderInfo').textContent = `Order #${gOrder.id} \u00b7 ${gOrder.total_items} items`;
        document.getElementById('gOrderInfo').className = `text-xs ${s.text}`;
    }

    // Subtitle
    document.getElementById('gSubtitle').textContent = gOrder
        ? `Order ${gOrder.status === 'fulfilled' ? 'sent by store' : gOrder.status === 'received' ? 'received' : gOrder.status}`
        : 'Review & order groceries';

    // Build items table
    const container = document.getElementById('gIngredients');
    const hasOrder = !!gOrder;
    const orderMap = {};
    gOrderLines.forEach(l => { orderMap[l.item_name] = l; });

    container.innerHTML = gItems.map(item => {
        const needed = Math.round((parseFloat(item.total_qty) || 0) * 100) / 100;
        const orderLine = orderMap[item.item_name];

        // Order qty: from order line if exists, else default to ceiling of needed
        let orderQty = Math.ceil(needed);
        if (orderLine) {
            orderQty = parseFloat(orderLine.requested_qty) || orderQty;
        }
        if (gEditedQtys[item.item_name] !== undefined) {
            orderQty = gEditedQtys[item.item_name];
        }

        // Show which dishes need this item
        const dishesLabel = item.dishes || '';

        return `
        <div class="grid grid-cols-[1fr_70px_80px] gap-1 px-3 py-2.5 border-b border-gray-50 items-center">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">${item.item_name}</p>
                <p class="text-[10px] text-gray-400 truncate">${dishesLabel}</p>
            </div>
            <div class="text-center">
                <span class="text-sm text-gray-500">${needed}</span>
                <span class="text-[10px] text-gray-400 block">${item.uom}</span>
            </div>
            <div class="text-center">
                ${hasOrder
                    ? `<span class="text-sm font-bold text-orange-700">${orderQty}</span><span class="text-[10px] text-gray-400 block">${item.uom}</span>`
                    : `<div class="flex items-center justify-center gap-0.5">
                        <button onclick="gAdjQty('${item.item_name.replace(/'/g, "\\'")}', -1)" class="w-7 h-7 rounded bg-gray-100 text-gray-600 font-bold text-sm flex items-center justify-center compact-btn active:bg-gray-200">-</button>
                        <span class="w-9 text-center text-sm font-bold text-orange-700" id="oq_${CSS.escape(item.item_name)}">${orderQty}</span>
                        <button onclick="gAdjQty('${item.item_name.replace(/'/g, "\\'")}', 1)" class="w-7 h-7 rounded bg-gray-100 text-gray-600 font-bold text-sm flex items-center justify-center compact-btn active:bg-gray-200">+</button>
                      </div>
                      <span class="text-[10px] text-gray-400">${item.uom}</span>`
                }
            </div>
        </div>`;
    }).join('');

    document.getElementById('gItemCount').textContent = `${gItems.length} items total`;

    // Show/hide buttons
    const submitBtn = document.getElementById('gSubmitBtn');
    const receiptBtn = document.getElementById('gReceiptBtn');

    submitBtn.classList.add('hidden');
    receiptBtn.classList.add('hidden');

    if (!gOrder && gItems.length > 0) {
        submitBtn.classList.remove('hidden');
        submitBtn.classList.add('flex');
    } else if (gOrder && gOrder.status === 'fulfilled') {
        receiptBtn.classList.remove('hidden');
        receiptBtn.classList.add('flex');
    }
}

function gAdjQty(itemName, delta) {
    const item = gItems.find(i => i.item_name === itemName);
    if (!item) return;

    const needed = Math.round((parseFloat(item.total_qty) || 0) * 100) / 100;
    let current = gEditedQtys[itemName] !== undefined ? gEditedQtys[itemName] : Math.ceil(needed);
    current = Math.max(0, current + delta);
    gEditedQtys[itemName] = current;

    // Update display using a safe selector
    const spans = document.querySelectorAll(`[id^="oq_"]`);
    spans.forEach(span => {
        // Match by finding the item name
        if (span.closest('.grid')?.querySelector('.text-sm.font-medium')?.textContent === itemName) {
            span.textContent = current;
        }
    });
}

function gEditQty(itemName, value) {
    gEditedQtys[itemName] = parseFloat(value) || 0;
}

// Submit order
async function gSubmitOrder() {
    const items = gItems.map(item => {
        const needed = Math.round((parseFloat(item.total_qty) || 0) * 100) / 100;
        let qty = Math.ceil(needed); // default: round up
        if (gEditedQtys[item.item_name] !== undefined) {
            qty = gEditedQtys[item.item_name];
        }
        return {
            item_id: item.item_id,
            item_name: item.item_name,
            qty: qty,
            uom: item.uom
        };
    }).filter(i => i.qty > 0);

    if (items.length === 0) return showToast('No items to order', 'warning');
    if (!confirm(`Submit order with ${items.length} items to the store?`)) return;

    const btn = document.getElementById('gSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin inline-block mr-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Submitting...';

    try {
        await api('api/daily-groceries.php', { method: 'POST', body: { action: 'submit_order', date: gDate, items } });
        showToast('Order submitted to store!');
        gLoadData();
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg> Submit Order to Store';
    }
}

// Confirm Receipt — show bottom sheet with line items
function gShowReceiptSheet() {
    let html = `
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Confirm Receipt</h3>
            <button onclick="closeSheet()" class="p-1 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 scroll-touch">
            <p class="text-xs text-gray-500 mb-3">Confirm the quantities you received from the store:</p>
            <div class="space-y-2" id="receiptLines">`;

    gOrderLines.forEach(line => {
        const qty = parseFloat(line.fulfilled_qty || line.requested_qty) || 0;
        html += `
            <div class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2.5">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${line.item_name}</p>
                    <p class="text-[10px] text-gray-400">Sent: ${qty} ${line.uom}</p>
                </div>
                <div class="flex items-center gap-1">
                    <button onclick="gAdjRecv(${line.id}, -1)" class="w-7 h-7 rounded bg-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center compact-btn">-</button>
                    <input type="number" value="${qty}" step="0.5" min="0" id="recv_${line.id}"
                        class="w-14 text-center text-sm font-semibold border border-gray-200 rounded-lg px-1 py-1.5 focus:outline-none focus:ring-2 focus:ring-green-200 compact-btn">
                    <button onclick="gAdjRecv(${line.id}, 1)" class="w-7 h-7 rounded bg-gray-200 text-gray-600 font-bold text-sm flex items-center justify-center compact-btn">+</button>
                </div>
                <span class="text-[10px] text-gray-400 shrink-0">${line.uom}</span>
            </div>`;
    });

    html += `
            </div>
            <button onclick="gConfirmReceipt()" id="confirmReceiptBtn" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-semibold transition mt-4">
                Confirm All Received
            </button>
        </div>`;

    openSheet(html);
}

function gAdjRecv(lineId, delta) {
    const input = document.getElementById(`recv_${lineId}`);
    if (input) {
        input.value = Math.max(0, (parseFloat(input.value) || 0) + delta);
    }
}

async function gConfirmReceipt() {
    const lines = gOrderLines.map(line => {
        const input = document.getElementById(`recv_${line.id}`);
        return {
            id: line.id,
            received_qty: parseFloat(input?.value) || 0
        };
    });

    const btn = document.getElementById('confirmReceiptBtn');
    btn.disabled = true;
    btn.textContent = 'Confirming...';

    try {
        await api('api/daily-groceries.php', { method: 'POST', body: {
            action: 'confirm_receipt',
            order_id: gOrder.id,
            lines
        }});
        closeSheet();
        showToast('Receipt confirmed!');
        gLoadData();
    } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false;
        btn.textContent = 'Confirm All Received';
    }
}
</script>
