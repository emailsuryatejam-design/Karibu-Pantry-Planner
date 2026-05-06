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
        <div class="flex items-center gap-1.5">
            <button onclick="ordPrintOrder()" class="p-2 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 active:bg-gray-300 transition" title="Print Order Report">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            </button>
            <button onclick="ordRefresh()" class="p-2 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 active:bg-gray-300 transition" title="Refresh">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
            </button>
        </div>
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

    <!-- Add Item Popup Modal (centered tile, not drawer) -->
    <div id="ordAddModal" class="hidden fixed inset-0 z-[200] bg-black/50 flex items-start justify-center pt-[10vh] p-4 animate-fade-in" onclick="if(event.target===this)ordCloseAddModal()">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full max-h-[80vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-bold text-gray-900" id="ordAddModalTitle">Add Item</h3>
                    <p class="text-xs text-gray-400" id="ordAddModalSub">Search and tap to add</p>
                </div>
                <button onclick="ordCloseAddModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-3">
                <div class="relative mb-2">
                    <input type="text" id="ordAddSearch" placeholder="Search by name or item #..."
                        oninput="ordFilterAddItems()"
                        class="w-full text-sm border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-3 text-gray-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
                <div id="ordAddCatFilter" class="flex gap-1 overflow-x-auto pb-1 scroll-touch"></div>
            </div>
            <div id="ordAddResults" class="flex-1 overflow-y-auto px-5 pb-4 space-y-1">
                <p class="text-xs text-gray-400 text-center py-3">Type to search or pick a category</p>
            </div>
        </div>
    </div>

    <!-- Item Detail Popup (qty + UOM picker) -->
    <div id="ordItemDetailModal" class="hidden fixed inset-0 z-[210] bg-black/50 flex items-center justify-center p-4 animate-fade-in" onclick="if(event.target===this)ordCloseItemDetail()">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6" id="ordItemDetailContent"></div>
    </div>

    <!-- Print View (hidden, revealed only during print) -->
    <div id="ordPrintView" class="hidden"></div>
</div>

<!-- Print CSS -->
<style>
@media print {
    body > *:not(#ordPrintView),
    #ordersPage > *:not(#ordPrintView),
    nav, .nav-bar, .bottom-nav,
    #ordAddItemBtn, #ordAddModal, #ordItemDetailModal,
    .fixed, [class*="fixed"] {
        display: none !important;
    }
    #ordPrintView {
        display: block !important;
        position: fixed;
        top: 0; left: 0;
        width: 100%;
        z-index: 99999;
        background: white;
        padding: 20px;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        font-size: 12px;
    }
    #ordPrintView .print-header {
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    #ordPrintView .print-header h1 { font-size: 18px; font-weight: bold; margin: 0 0 4px 0; }
    #ordPrintView .print-header p  { font-size: 12px; margin: 2px 0; color: #333; }
    #ordPrintView .print-section   { margin-bottom: 16px; page-break-inside: avoid; }
    #ordPrintView .print-section h2 {
        font-size: 14px; font-weight: bold;
        margin: 0 0 6px 0; padding: 4px 8px;
        background: #f0f0f0; border: 1px solid #ccc;
    }
    #ordPrintView table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    #ordPrintView th, #ordPrintView td { border: 1px solid #999; padding: 4px 8px; text-align: left; font-size: 11px; }
    #ordPrintView th { background: #e8e8e8; font-weight: bold; font-size: 10px; text-transform: uppercase; }
    #ordPrintView td.num { text-align: center; }
    #ordPrintView .print-summary { margin: 16px 0; font-size: 12px; border-top: 1px solid #999; padding-top: 8px; }
    #ordPrintView .print-signatures { margin-top: 40px; page-break-inside: avoid; }
    #ordPrintView .sig-table { width: 100%; border-collapse: collapse; }
    #ordPrintView .sig-table td { border: none; padding: 6px 4px; font-size: 12px; vertical-align: bottom; }
    #ordPrintView .sig-table .sig-underline { border-bottom: 1px solid #000; min-width: 120px; display: inline-block; margin-left: 4px; }
    @page { margin: 15mm; size: A4; }
}
</style>

<script>
const ORD_KITCHEN_ID = <?= (int)$kitchenId ?>;
const ORD_UOM_OPTIONS = ['kg', 'g', 'ltr', 'ml', 'pcs', 'tins', 'box', 'pkt', 'bunch', 'bottle', 'unit'];

let ordDate = todayStr();
let ordActiveTab = 'menu'; // 'menu' or 'staple'
let ordRequisitions = [];
let ordLinesByReq = {};
let ordAdjustments = {};
let ordAllItems = null; // cached for add-item
let ordCollapsed = {}; // reqId -> true/false
let ordDishBreakdown = {}; // reqId -> { itemId: [{dish_name, qty, uom}] }
let ordTypes = []; // all active requisition types (meal codes)

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
        draft:      { cls: 'bg-gray-100 text-gray-600', label: 'Draft' },
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

// ── Update guest count (calls API directly) ──
async function ordUpdateGuestCount(reqId, newCount) {
    newCount = Math.max(1, parseInt(newCount) || 1);
    try {
        await api('api/requisitions.php?action=recalculate_order', {
            method: 'POST',
            body: { requisition_id: reqId, guest_count: newCount }
        });
        await ordLoad();
        showToast(`Guest count updated to ${newCount}`, 'success');
    } catch(e) {
        showToast(e.message || 'Failed to update', 'error');
    }
}

function ordStepGuests(reqId, delta) {
    const inp = document.getElementById('ordGuestInput_' + reqId);
    if (!inp) return;
    const next = Math.max(1, (parseInt(inp.value) || 20) + delta);
    inp.value = next;
    ordUpdateGuestCount(reqId, next);
}

function ordToggleCollapse(reqId) {
    ordCollapsed[reqId] = !ordCollapsed[reqId];
    ordRender();
}

async function ordLoad() {
    document.getElementById('ordLoading').classList.remove('hidden');
    document.getElementById('ordList').classList.add('hidden');
    document.getElementById('ordEmpty').classList.add('hidden');

    try {
        const res = await api(`api/requisitions.php?action=day_summary&date=${ordDate}&kitchen_id=${ORD_KITCHEN_ID}`);
        const allReqs = res.requisitions || [];

        const validStatuses = ['draft', 'processing', 'submitted', 'fulfilled', 'received', 'closed'];
        ordRequisitions = allReqs.filter(r => validStatuses.includes(r.status));

        // Fetch all active requisition types so we know which meal cards to show
        try {
            const typesRes = await api('api/requisition-types.php?action=list');
            ordTypes = typesRes.types || [];
        } catch(e) { console.log('Could not fetch requisition types:', e); }

        ordLinesByReq = res.lines_by_req || {};
        ordAdjustments = {};

        // Fetch full lines for all requisitions
        const reqsNeedingLines = ordRequisitions.filter(r => ['draft', 'processing', 'submitted', 'fulfilled', 'received'].includes(r.status));
        await Promise.all(reqsNeedingLines.map(r =>
            api(`api/requisitions.php?action=get&id=${r.id}`).then(data => {
                ordLinesByReq[r.id] = data.lines || [];
            }).catch(() => { ordLinesByReq[r.id] = []; })
        ));

        // Fetch dish breakdown for each requisition
        ordDishBreakdown = {};
        await Promise.all(ordRequisitions.map(r =>
            api(`api/requisitions.php?action=get_dishes_with_ingredients&requisition_id=${r.id}`).then(data => {
                const dishes = data.dishes || [];
                const ingsByRecipe = data.ingredients_by_recipe || {};
                const breakdown = {}; // itemId -> [{dish_name, qty, uom}]
                const seenRecipes = new Set();
                for (const dish of dishes) {
                    if (seenRecipes.has(dish.recipe_id)) continue;
                    seenRecipes.add(dish.recipe_id);
                    const ings = ingsByRecipe[dish.recipe_id] || [];
                    const guestCount = parseInt(dish.guest_count) || 20;
                    const servings = parseInt(dish.recipe_servings) || 4;
                    const scale = guestCount / servings;
                    for (const ing of ings) {
                        const itemId = ing.item_id;
                        const scaledQty = parseFloat(ing.qty) * scale;
                        if (!breakdown[itemId]) breakdown[itemId] = [];
                        breakdown[itemId].push({ dish_name: dish.recipe_name, qty: scaledQty, uom: ing.uom || 'kg' });
                    }
                }
                ordDishBreakdown[r.id] = breakdown;
            }).catch(() => { ordDishBreakdown[r.id] = {}; })
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
    const list = document.getElementById('ordList');

    if (ordActiveTab === 'staple') {
        list.classList.remove('hidden');
        document.getElementById('ordEmpty').classList.add('hidden');
        list.innerHTML = ordRenderStapleTab();
        return;
    }

    // Menu tab: show a card for EACH meal type
    if (ordTypes.length === 0 && ordRequisitions.length === 0) {
        document.getElementById('ordEmpty').classList.remove('hidden');
        document.getElementById('ordList').classList.add('hidden');
        return;
    }

    list.classList.remove('hidden');
    document.getElementById('ordEmpty').classList.add('hidden');

    let html = '';
    const shownMeals = new Set();

    // For each known meal type, find matching requisition or show empty card
    ordTypes.forEach(t => {
        const code = (t.code || '').toLowerCase();
        const name = t.name || t.code || 'Order';
        shownMeals.add(code);
        const req = ordRequisitions.find(r => (r.meals || '').toLowerCase() === code);
        if (req) {
            html += ordRenderCard(req);
        } else {
            html += ordRenderEmptyMealCard(code, name);
        }
    });

    // Also show any requisitions whose meal type is NOT in ordTypes (edge case)
    ordRequisitions.forEach(req => {
        const code = (req.meals || '').toLowerCase();
        if (!shownMeals.has(code)) {
            html += ordRenderCard(req);
        }
    });

    // Fallback: if ordTypes is empty but we have requisitions, show them all
    if (ordTypes.length === 0) {
        ordRequisitions.forEach(req => { html += ordRenderCard(req); });
    }

    list.innerHTML = html;
}

// ── Empty meal card (no requisition yet for this meal type) ──
function ordRenderEmptyMealCard(mealCode, mealName) {
    const color = ordGetColor(mealCode);
    return `<div class="bg-white rounded-xl border ${color.border} overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 ${color.header} border-b">
            <span class="text-sm font-bold ${color.text}">${escHtml(mealName)} Order</span>
            <button onclick="ordCreateAndAddItem('${mealCode}')"
                class="px-3 py-1.5 rounded-lg bg-orange-500 text-white text-xs font-semibold hover:bg-orange-600 active:bg-orange-700 transition flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                Add item
            </button>
        </div>
        <div class="px-4 py-4 text-center">
            <p class="text-sm text-gray-400">No items yet — tap + to add</p>
        </div>
    </div>`;
}

async function ordCreateAndAddItem(meal) {
    try {
        showToast('Creating ' + meal + ' order...', 'info');
        const res = await api('api/requisitions.php?action=page_init', {
            method: 'POST',
            body: { req_date: ordDate, kitchen_id: ORD_KITCHEN_ID, guest_count: 20 }
        });
        const allReqs = res.requisitions || [];
        const newReq = allReqs.find(r => (r.meals || '').toLowerCase() === meal.toLowerCase());
        if (newReq) {
            if (!ordRequisitions.find(r => r.id == newReq.id)) {
                ordRequisitions.push(newReq);
                ordLinesByReq[newReq.id] = [];
            }
            ordAddTargetReqId = newReq.id;
            ordRender();
            ordShowAddItem(newReq.id);
        } else {
            showToast('Could not create ' + meal + ' order', 'error');
        }
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function ordRenderCard(req) {
    const color = ordGetColor(req.meals);
    const typeInfo = ordTypes.find(t => (t.code || '').toLowerCase() === (req.meals || '').toLowerCase());
    const mealLabel = typeInfo ? typeInfo.name : (typeof reqLabel === 'function' ? reqLabel(req) : (req.meals || 'Order'));
    const allLines = ordLinesByReq[req.id] || [];

    // Filter lines by tab
    const lines = allLines.filter(l => {
        const staple = parseInt(l.is_staple) || 0;
        return ordActiveTab === 'staple' ? staple === 1 : staple === 0;
    });

    const canAddItems = ['draft', 'processing', 'submitted'].includes(req.status);

    if (lines.length === 0 && allLines.length > 0) {
        const otherCount = allLines.filter(l => {
            const s = parseInt(l.is_staple) || 0;
            return ordActiveTab === 'staple' ? s === 0 : s === 1;
        }).length;
        return `<div class="bg-white rounded-xl border ${color.border} overflow-hidden opacity-50">
            <div class="flex items-center justify-between px-4 py-3 ${color.header} border-b">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold ${color.text}">${escHtml(mealLabel)} Order</span>
                    ${ordStatusBadge(req.status)}
                </div>
                ${canAddItems ? `<button onclick="event.stopPropagation();ordShowAddItem(${req.id})" class="w-8 h-8 rounded-lg bg-white/80 border border-green-300 text-green-600 flex items-center justify-center hover:bg-green-50 active:bg-green-100 transition" title="Add item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                </button>` : ''}
            </div>
            <div class="px-4 py-3 text-center text-xs text-gray-400">
                No ${ordActiveTab} items — ${otherCount} item${otherCount !== 1 ? 's' : ''} in other tab
            </div>
        </div>`;
    }

    if (lines.length === 0) {
        // Draft with no lines
        const gc = parseInt(req.guest_count) || 20;
        return `<div class="bg-white rounded-xl border ${color.border} overflow-hidden shadow-sm" id="ord-card-${req.id}">
            <div class="flex items-center justify-between px-4 py-3 ${color.header} border-b">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold ${color.text}">${escHtml(mealLabel)} Order</span>
                    <span class="text-[10px] text-gray-400">#${req.id}</span>
                </div>
                ${canAddItems ? `<button onclick="ordShowAddItem(${req.id})"
                    class="px-3 py-1.5 rounded-lg bg-orange-500 text-white text-xs font-semibold hover:bg-orange-600 active:bg-orange-700 transition flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    Add item
                </button>` : ordStatusBadge(req.status)}
            </div>
            ${canAddItems ? `<div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Guests</div>
                <div class="flex items-center gap-1.5">
                    <button onclick="event.stopPropagation(); ordStepGuests(${req.id}, -5)"
                        class="w-7 h-7 rounded-lg bg-gray-100 text-gray-500 font-bold flex items-center justify-center text-xs active:bg-gray-200">-5</button>
                    <input type="number" id="ordGuestInput_${req.id}" value="${gc}" min="1"
                        onchange="ordUpdateGuestCount(${req.id}, this.value)"
                        class="w-14 text-center text-sm font-bold text-gray-800 border border-gray-200 rounded-lg py-1 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <button onclick="event.stopPropagation(); ordStepGuests(${req.id}, 5)"
                        class="w-7 h-7 rounded-lg bg-orange-100 text-orange-600 font-bold flex items-center justify-center text-xs active:bg-orange-200">+5</button>
                </div>
            </div>` : ''}
            <div class="px-4 py-4 text-center">
                <p class="text-sm text-gray-400">No items yet — tap + to add</p>
            </div>
        </div>`;
    }

    const isCollapsed = !!ordCollapsed[req.id];
    const chevronSvg = isCollapsed
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>';

    let html = `<div class="bg-white rounded-xl border ${color.border} overflow-hidden shadow-sm" id="ord-card-${req.id}">`;
    html += `<div class="flex items-center justify-between px-4 py-3 ${color.header} border-b cursor-pointer select-none" onclick="ordToggleCollapse(${req.id})">
        <div class="flex items-center gap-2">
            <span class="text-sm font-bold ${color.text}">${escHtml(mealLabel)} Order</span>
            <span class="text-[10px] text-gray-400">#${req.id}</span>
            <span class="text-[10px] text-gray-500 font-medium">${lines.length} item${lines.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="flex items-center gap-2">
            ${ordStatusBadge(req.status)}
            <span class="text-gray-400">${chevronSvg}</span>
        </div>
    </div>`;

    if (!isCollapsed) {
        const isEditable = ['draft', 'processing', 'submitted'].includes(req.status);
        const gc = parseInt(req.guest_count) || 20;

        if (isEditable) {
            html += `<div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Guests</div>
                <div class="flex items-center gap-1.5">
                    <button onclick="event.stopPropagation(); ordStepGuests(${req.id}, -5)"
                        class="w-7 h-7 rounded-lg bg-gray-100 text-gray-500 font-bold flex items-center justify-center text-xs active:bg-gray-200">-5</button>
                    <input type="number" id="ordGuestInput_${req.id}" value="${gc}" min="1"
                        onchange="ordUpdateGuestCount(${req.id}, this.value)"
                        class="w-14 text-center text-sm font-bold text-gray-800 border border-gray-200 rounded-lg py-1 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <button onclick="event.stopPropagation(); ordStepGuests(${req.id}, 5)"
                        class="w-7 h-7 rounded-lg bg-orange-100 text-orange-600 font-bold flex items-center justify-center text-xs active:bg-orange-200">+5</button>
                </div>
            </div>`;
        } else if (gc) {
            html += `<div class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-100">
                <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Guest Count</span>
                <span class="text-xs font-bold text-gray-500">${gc} pax</span>
            </div>`;
        }

        if (isEditable) {
            html += ordRenderEditableLines(req, lines);
        } else {
            html += ordRenderReadOnlyLines(req, lines);
        }
    }

    html += '</div>';
    return html;
}

// ── Editable lines — table with Calc / Order columns + dish breakdown ──
function ordRenderEditableLines(req, lines) {
    let html = `<div class="px-3 py-3">
        <div class="overflow-x-auto">
            <table class="w-full text-[11px]">
                <thead><tr class="bg-gray-50">
                    <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">Item</th>
                    <th class="text-center px-1 py-1.5 text-blue-600 font-semibold w-16">Calc</th>
                    <th class="text-center px-1 py-1.5 text-green-600 font-semibold w-20">Order</th>
                    <th class="text-center px-1 py-1.5 w-8"></th>
                </tr></thead>
                <tbody>`;

    lines.forEach(line => {
        const calcQty = parseFloat(line.calc_qty || line.required_qty || 0);
        const orderQty = parseFloat(line.order_qty) || 0;
        if (ordAdjustments[line.id] === undefined) ordAdjustments[line.id] = orderQty;
        const currentQty = ordAdjustments[line.id];

        // Dish breakdown for this item
        const dishSources = (ordDishBreakdown[req.id] || {})[line.item_id] || [];
        const breakdownHtml = dishSources.length > 0
            ? dishSources.map(s => `<span class="inline-flex items-center gap-0.5"><span class="text-gray-500">${escHtml(s.dish_name)}</span> <span class="text-blue-500 font-medium">${s.qty.toFixed(1)}</span></span>`).join('<span class="text-gray-300 mx-0.5">&middot;</span>')
            : '';

        html += `<tr class="border-b border-gray-50">
            <td class="px-2 py-2">
                <p class="text-xs font-medium text-gray-800 truncate">${escHtml(line.item_name)}</p>
                <p class="text-[9px] text-gray-400">${escHtml(line.uom || 'kg')}</p>
                ${breakdownHtml ? `<div class="text-[9px] text-gray-400 mt-0.5 leading-tight">${breakdownHtml}</div>` : ''}
            </td>
            <td class="text-center px-1 py-2 text-blue-700 font-medium text-xs">${calcQty > 0 ? calcQty.toFixed(1) : '—'}</td>
            <td class="text-center px-1 py-2">
                <input type="number" value="${currentQty}" step="0.5" min="0"
                    onchange="ordAdjustments[${line.id}] = parseFloat(this.value)||0"
                    class="w-16 text-center text-xs font-bold border border-green-300 rounded-lg py-1 bg-green-50 focus:outline-none focus:ring-1 focus:ring-green-300">
            </td>
            <td class="text-center px-1 py-2">
                <button onclick="event.stopPropagation(); ordShowEditLine(${line.id}, ${req.id})" class="w-6 h-6 rounded-md bg-gray-100 text-gray-500 flex items-center justify-center hover:bg-gray-200 transition" title="Edit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
            </td>
        </tr>`;
    });

    html += `</tbody></table></div>`;

    // Add item + Submit + Delete buttons
    html += `<div class="flex gap-2 mt-3">
        <button onclick="ordDeleteOrder(${req.id})"
            class="px-4 py-3 rounded-xl border-2 border-red-200 text-red-500 font-semibold text-sm hover:bg-red-50 flex items-center justify-center gap-1.5 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
        </button>
        <button onclick="ordShowAddItem(${req.id})"
            class="px-4 py-3 rounded-xl border-2 border-orange-200 text-orange-500 font-semibold text-sm hover:bg-orange-50 flex items-center justify-center gap-1.5 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
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

// ── Read-only lines — table with dish breakdown ──
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
        const dishSources = (ordDishBreakdown[req.id] || {})[line.item_id] || [];
        const breakdownHtml = dishSources.length > 0
            ? dishSources.map(s => `<span class="text-gray-400">${escHtml(s.dish_name)}</span> <span class="text-blue-400">${s.qty.toFixed(1)}</span>`).join(' · ')
            : '';
        html += `<tr class="border-b border-gray-50">
            <td class="px-2 py-2">
                <span class="text-gray-700">${escHtml(line.item_name)}</span> <span class="text-gray-300 text-[9px]">${escHtml(line.uom || '')}</span>
                ${breakdownHtml ? `<div class="text-[9px] leading-tight mt-0.5">${breakdownHtml}</div>` : ''}
            </td>
            <td class="text-center px-1 py-2 text-blue-700 font-medium">${oq > 0 ? oq.toFixed(1) : '—'}</td>
            <td class="text-center px-1 py-2 text-green-700 font-medium">${fq > 0 ? fq.toFixed(1) : '—'}</td>
            <td class="text-center px-1 py-2 text-orange-700 font-medium">${rq > 0 ? rq.toFixed(1) : '—'}</td>
        </tr>`;
    });

    html += `</tbody></table></div></div>`;
    return html;
}

// ── Staple Tab ──
function ordRenderStapleTab() {
    let allStapleLines = [];
    let editableReqId = null;
    let editableReqIds = [];

    ordRequisitions.forEach(req => {
        const lines = ordLinesByReq[req.id] || [];
        const staples = lines.filter(l => parseInt(l.is_staple) === 1);
        staples.forEach(l => {
            allStapleLines.push({ ...l, reqId: req.id, reqStatus: req.status });
        });
        if (['draft', 'processing', 'submitted'].includes(req.status) && staples.length > 0) {
            if (!editableReqId) editableReqId = req.id;
            editableReqIds.push(req.id);
        }
    });

    const anyEditableReq = ordRequisitions.find(r => ['draft', 'processing', 'submitted'].includes(r.status));

    if (allStapleLines.length === 0) {
        let html = `<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="flex items-center justify-between px-4 py-3 bg-purple-50 border-b border-purple-100">
                <span class="text-sm font-bold text-purple-700">Staple Items</span>
            </div>
            <div class="px-4 py-6 text-center">
                <p class="text-sm text-gray-400 mb-1">No staple items yet</p>
                <p class="text-xs text-gray-300">Use the + button to add staple items</p>
            </div>
        </div>`;
        if (anyEditableReq) {
            html += `<button onclick="ordShowAddItem(${anyEditableReq.id})"
                class="w-full border-2 border-dashed border-gray-200 hover:border-green-300 rounded-xl py-3 text-xs font-medium text-gray-400 hover:text-green-600 transition flex items-center justify-center gap-1.5 mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                Add Staple Item
            </button>`;
        }
        return html;
    }

    const isEditable = allStapleLines.some(l => ['draft', 'processing', 'submitted'].includes(l.reqStatus));

    let html = `<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">`;
    html += `<div class="flex items-center justify-between px-4 py-3 bg-purple-50 border-b border-purple-100">
        <div class="flex items-center gap-2">
            <span class="text-sm font-bold text-purple-700">Staple Items</span>
            <span class="text-[10px] bg-purple-100 rounded-full px-2 py-0.5 text-purple-600 font-medium">${allStapleLines.length}</span>
        </div>
    </div>`;

    html += `<div class="px-3 py-3"><div class="overflow-x-auto">
        <table class="w-full text-[11px]">
            <thead><tr class="bg-gray-50">
                <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">Item</th>
                <th class="text-center px-1 py-1.5 text-green-600 font-semibold w-20">${isEditable ? 'Qty' : 'Req'}</th>
                ${isEditable ? '<th class="text-center px-1 py-1.5 w-8"></th>' : '<th class="text-center px-1 py-1.5 text-green-600 font-semibold">Sent</th><th class="text-center px-1 py-1.5 text-orange-600 font-semibold">Recv</th>'}
            </tr></thead>
            <tbody>`;

    allStapleLines.forEach(line => {
        const orderQty = parseFloat(line.order_qty) || 0;
        if (ordAdjustments[line.id] === undefined) ordAdjustments[line.id] = orderQty;
        const currentQty = ordAdjustments[line.id];
        const lineEditable = ['draft', 'processing', 'submitted'].includes(line.reqStatus);

        if (lineEditable) {
            html += `<tr class="border-b border-gray-50">
                <td class="px-2 py-2">
                    <p class="text-xs font-medium text-gray-800 truncate">${escHtml(line.item_name)}</p>
                    <p class="text-[9px] text-gray-400">${escHtml(line.uom || 'kg')}</p>
                </td>
                <td class="text-center px-1 py-2">
                    <input type="number" value="${currentQty}" step="0.5" min="0"
                        onchange="ordAdjustments[${line.id}] = parseFloat(this.value)||0"
                        class="w-16 text-center text-xs font-bold border border-green-300 rounded-lg py-1 bg-green-50 focus:outline-none focus:ring-1 focus:ring-green-300">
                </td>
                <td class="text-center px-1 py-2">
                    <button onclick="event.stopPropagation(); ordRemoveLine(${line.id}, ${line.reqId})" class="w-6 h-6 rounded-md bg-red-50 text-red-400 flex items-center justify-center hover:bg-red-100 transition" title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </td>
            </tr>`;
        } else {
            const fq = parseFloat(line.fulfilled_qty) || 0;
            const rq = parseFloat(line.received_qty) || 0;
            html += `<tr class="border-b border-gray-50">
                <td class="px-2 py-2 text-gray-700 text-xs">${escHtml(line.item_name)} <span class="text-gray-300 text-[9px]">${escHtml(line.uom || '')}</span></td>
                <td class="text-center px-1 py-2 text-blue-700 font-medium">${orderQty > 0 ? orderQty.toFixed(1) : '—'}</td>
                <td class="text-center px-1 py-2 text-green-700 font-medium">${fq > 0 ? fq.toFixed(1) : '—'}</td>
                <td class="text-center px-1 py-2 text-orange-700 font-medium">${rq > 0 ? rq.toFixed(1) : '—'}</td>
            </tr>`;
        }
    });

    html += `</tbody></table></div>`;

    if (editableReqIds.length > 0) {
        const idsJson = JSON.stringify(editableReqIds);
        const btnLabel = editableReqIds.length > 1
            ? `Submit to Store (${editableReqIds.length} orders)`
            : 'Submit to Store';
        html += `<div class="flex gap-2 mt-3 px-1">
            <button onclick='ordSubmitStapleOrders(${idsJson})' id="ord-submit-staple-bulk"
                class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                ${btnLabel}
            </button>
        </div>`;
    }

    html += `</div></div>`;

    if (anyEditableReq) {
        html += `<button onclick="ordShowAddItem(${anyEditableReq.id})"
            class="w-full border-2 border-dashed border-gray-200 hover:border-green-300 rounded-xl py-3 text-xs font-medium text-gray-400 hover:text-green-600 transition flex items-center justify-center gap-1.5 mt-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Add Staple Item
        </button>`;
    }

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
//  Delete/Cancel Order
// ══════════════════════════════════════════════
async function ordDeleteOrder(reqId) {
    const req = ordRequisitions.find(r => r.id == reqId);
    if (!req) return;
    const typeInfo = ordTypes.find(t => (t.code || '').toLowerCase() === (req.meals || '').toLowerCase());
    const mealLabel = typeInfo ? typeInfo.name : (typeof reqLabel === 'function' ? reqLabel(req) : (req.meals || 'Order'));

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
//  Add Item Modal
// ══════════════════════════════════════════════
let ordAddTargetReqId = null;

async function ordShowAddItem(reqId) {
    let targetReq = null;
    if (reqId) {
        targetReq = ordRequisitions.find(r => r.id == reqId);
    }
    if (!targetReq) {
        targetReq = ordRequisitions.find(r => ['draft', 'processing', 'submitted'].includes(r.status));
    }
    if (!targetReq) {
        try {
            showToast('Creating order...', 'info');
            let mealCode = 'breakfast';
            try {
                if (ordTypes.length > 0) mealCode = ordTypes[0].code;
            } catch(e) {}
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

    // Update modal title
    const titleEl = document.getElementById('ordAddModalTitle');
    const subEl = document.getElementById('ordAddModalSub');
    if (ordActiveTab === 'staple') {
        if (titleEl) titleEl.textContent = 'Add Staple Item';
        if (subEl) subEl.textContent = 'Search and tap to add';
    } else {
        const typeInfo = ordTypes.find(t => (t.code || '').toLowerCase() === (targetReq.meals || '').toLowerCase());
        const mealLabel = typeInfo ? typeInfo.name : (targetReq.meals || 'Order');
        if (titleEl) titleEl.textContent = 'Add to ' + mealLabel;
        if (subEl) subEl.textContent = 'Item will be added to this order';
    }

    if (!ordAllItems) {
        try { const res = await api('api/items.php?action=list&active=1'); ordAllItems = res.items || []; } catch (e) { ordAllItems = []; }
    }

    ordSelectedCat = '';
    document.getElementById('ordAddModal').classList.remove('hidden');
    document.getElementById('ordAddSearch').value = '';
    document.getElementById('ordAddResults').innerHTML = '<p class="text-xs text-gray-400 text-center py-3">Type to search or pick a category</p>';

    const foodCats = ['Dry', 'Dairy', 'Veg', 'Meat', 'Fruits', 'Juice', 'Bar'];
    const allCats = [...new Set(ordAllItems.map(i => i.category).filter(Boolean))].sort();
    const priorityCats = foodCats.filter(c => allCats.includes(c));
    const otherCats = allCats.filter(c => !foodCats.includes(c));
    const catContainer = document.getElementById('ordAddCatFilter');
    if (catContainer) {
        catContainer.innerHTML = [...priorityCats, ...otherCats].map(c =>
            `<button onclick="ordFilterByCat('${c}')" class="ord-cat-btn px-2.5 py-1 rounded-full text-[10px] font-medium whitespace-nowrap bg-gray-100 text-gray-600 hover:bg-orange-100 hover:text-orange-700 transition">${c}</button>`
        ).join('');
    }

    setTimeout(() => document.getElementById('ordAddSearch')?.focus(), 100);
}

let ordSelectedCat = '';

function ordFilterByCat(cat) {
    ordSelectedCat = (ordSelectedCat === cat) ? '' : cat;
    document.querySelectorAll('.ord-cat-btn').forEach(btn => {
        btn.className = btn.textContent === ordSelectedCat
            ? 'ord-cat-btn px-2.5 py-1 rounded-full text-[10px] font-medium whitespace-nowrap bg-orange-500 text-white'
            : 'ord-cat-btn px-2.5 py-1 rounded-full text-[10px] font-medium whitespace-nowrap bg-gray-100 text-gray-600 hover:bg-orange-100 hover:text-orange-700 transition';
    });
    ordFilterAddItems();
}

function ordCloseAddModal() {
    document.getElementById('ordAddModal').classList.add('hidden');
}

function ordFilterAddItems() {
    const q = (document.getElementById('ordAddSearch')?.value || '').toLowerCase().trim();
    const results = document.getElementById('ordAddResults');
    if (!results || !ordAllItems) return;

    let filtered = ordAllItems;
    if (ordSelectedCat) filtered = filtered.filter(item => item.category === ordSelectedCat);
    if (q.length >= 2) {
        filtered = filtered.filter(item =>
            item.name.toLowerCase().includes(q) || (item.code && item.code.toLowerCase().includes(q))
        );
    } else if (!ordSelectedCat) {
        results.innerHTML = '<p class="text-xs text-gray-400 text-center py-3">Type to search or pick a category</p>';
        return;
    }

    const matches = filtered.slice(0, 20);
    if (matches.length === 0) { results.innerHTML = '<p class="text-xs text-gray-400 text-center py-3">No items found</p>'; return; }

    results.innerHTML = matches.map(item => {
        const safeName = escHtml(item.name);
        const safeCode = escHtml(item.code || '');
        return `<button onclick="ordShowItemDetail(${item.id}, '${safeName.replace(/'/g, "\\'")}', '${escHtml(item.uom||'kg')}')"
            class="w-full flex items-center gap-3 px-3 py-3 hover:bg-orange-50 active:bg-orange-100 transition text-left border-b border-gray-100 last:border-0 rounded-lg">
            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">${safeCode ? '<span class="text-blue-500 text-[10px] mr-1">#'+safeCode+'</span>' : ''}${safeName}</p>
                <p class="text-[10px] text-gray-400">${escHtml(item.category || '')} · ${escHtml(item.uom||'kg')}</p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" class="shrink-0"><path d="m9 18 6-6-6-6"/></svg>
        </button>`;
    }).join('');
}

function ordShowItemDetail(itemId, itemName, itemUom) {
    ordCloseAddModal();
    const uomOptions = ORD_UOM_OPTIONS.map(u => `<option value="${u}" ${u === itemUom ? 'selected' : ''}>${u}</option>`).join('');

    document.getElementById('ordItemDetailContent').innerHTML = `
        <div class="flex items-center gap-3 mb-5">
            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">${itemName}</h3>
                <p class="text-xs text-gray-400">${ordActiveTab === 'staple' ? 'Add to staple order' : 'Add to menu order'}</p>
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
        const isStaple = ordActiveTab === 'staple' ? 1 : 0;
        await api('api/requisitions.php?action=add_line_to_order', {
            method: 'POST',
            body: { requisition_id: ordAddTargetReqId, item_id: itemId, item_name: itemName, order_qty: qty, uom: uom, is_staple: isStaple }
        });
        showToast(`${itemName} added!`, 'success');
        ordCloseItemDetail();
        ordLoad();
    } catch (err) {
        showToast(err.message, 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Add'; }
    }
}

// ══════════════════════════════════════════════
//  Submit Orders
// ══════════════════════════════════════════════
async function ordSubmitStapleOrders(reqIds) {
    if (!reqIds || reqIds.length === 0) return;
    const plural = reqIds.length > 1 ? 'orders' : 'order';
    if (!await customConfirm('Submit to Store', `Submit ${reqIds.length} ${plural} with staple items to the store?`)) return;

    const btn = document.getElementById('ord-submit-staple-bulk');
    if (btn) setLoading(btn, true, 'Submitting...');

    try {
        let submitted = 0;
        for (const reqId of reqIds) {
            const allLines = (ordLinesByReq[reqId] || []).filter(l => parseInt(l.is_staple) === 1);
            if (allLines.length === 0) continue;
            const lineData = allLines.map(line => ({
                id: parseInt(line.id),
                order_qty: ordAdjustments[line.id] !== undefined ? ordAdjustments[line.id] : (parseFloat(line.order_qty) || 0)
            }));
            const nonZero = lineData.filter(l => l.order_qty > 0);
            if (nonZero.length === 0) continue;
            await api('api/requisitions.php?action=submit_order', {
                method: 'POST',
                body: { requisition_id: reqId, lines: lineData }
            });
            submitted++;
        }
        showToast(`Submitted ${submitted} ${submitted === 1 ? 'order' : 'orders'} to store!`, 'success');
        ordLoad();
    } catch (err) {
        showToast(err.message || 'Submit failed', 'error');
        if (btn) setLoading(btn, false);
    }
}

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

// ══════════════════════════════════════════════
//  Print Order Report
// ══════════════════════════════════════════════
function ordPrintOrder() {
    if (ordRequisitions.length === 0) {
        showToast('No orders to print', 'error');
        return;
    }

    const kitchenName = <?= json_encode($user['kitchen_name'] ?? 'Kitchen') ?>;
    const printDate = formatDate(ordDate);
    const printView = document.getElementById('ordPrintView');

    let html = '';
    html += '<div class="print-header">';
    html += '<h1>' + escHtml(kitchenName) + '</h1>';
    html += '<p>Order Requisition Report</p>';
    html += '<p>Date: ' + escHtml(printDate) + '</p>';
    html += '</div>';

    let totalItems = 0;
    let totalKg = 0;

    // Group by meal type
    const mealGroups = {};
    ordRequisitions.forEach(req => {
        const mealCode = (req.meals || 'other').toLowerCase();
        const typeInfo = ordTypes.find(t => (t.code || '').toLowerCase() === mealCode);
        const mealLabel = typeInfo ? typeInfo.name : (req.meals || 'Order');
        const allLines = ordLinesByReq[req.id] || [];
        if (!mealGroups[mealCode]) {
            mealGroups[mealCode] = { label: mealLabel, status: req.status, lines: [] };
        }
        allLines.forEach(l => mealGroups[mealCode].lines.push(l));
    });

    Object.keys(mealGroups).forEach(code => {
        const group = mealGroups[code];
        if (group.lines.length === 0) return;

        html += '<div class="print-section">';
        html += '<h2>' + escHtml(group.label) + ' (' + escHtml(group.status || '') + ')</h2>';
        html += '<table><thead><tr>';
        html += '<th style="width:5%">#</th>';
        html += '<th style="width:40%">Item Name</th>';
        html += '<th style="width:12%">UOM</th>';
        html += '<th style="width:15%">Calc Qty</th>';
        html += '<th style="width:15%">Order Qty</th>';
        html += '</tr></thead><tbody>';

        group.lines.forEach((line, idx) => {
            const calcQty = parseFloat(line.calc_qty || line.required_qty || 0);
            const orderQty = ordAdjustments[line.id] !== undefined
                ? ordAdjustments[line.id]
                : (parseFloat(line.order_qty) || 0);
            const uom = (line.uom || 'kg').toLowerCase();

            totalItems++;
            if (uom === 'kg') totalKg += orderQty;

            html += '<tr>';
            html += '<td class="num">' + (idx + 1) + '</td>';
            html += '<td>' + escHtml(line.item_name) + '</td>';
            html += '<td class="num">' + escHtml(line.uom || 'kg') + '</td>';
            html += '<td class="num">' + (calcQty > 0 ? calcQty.toFixed(1) : '-') + '</td>';
            html += '<td class="num">' + (orderQty > 0 ? orderQty.toFixed(1) : '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
    });

    html += '<div class="print-summary"><strong>Summary:</strong> ';
    html += totalItems + ' item' + (totalItems !== 1 ? 's' : '') + ' total';
    if (totalKg > 0) html += ' | ' + totalKg.toFixed(1) + ' kg (weight items only)';
    html += '</div>';

    html += '<div class="print-signatures"><table class="sig-table">';
    html += '<tr><td><strong>Prepared by (Chef):</strong> <span class="sig-underline">&nbsp;</span></td><td>Date: <span class="sig-underline">&nbsp;</span></td><td>Signature: <span class="sig-underline">&nbsp;</span></td></tr>';
    html += '<tr><td><strong>Approved by (Manager):</strong> <span class="sig-underline">&nbsp;</span></td><td>Date: <span class="sig-underline">&nbsp;</span></td><td>Signature: <span class="sig-underline">&nbsp;</span></td></tr>';
    html += '<tr><td><strong>Received by (Store):</strong> <span class="sig-underline">&nbsp;</span></td><td>Date: <span class="sig-underline">&nbsp;</span></td><td>Signature: <span class="sig-underline">&nbsp;</span></td></tr>';
    html += '</table></div>';

    printView.innerHTML = html;
    printView.classList.remove('hidden');

    setTimeout(() => {
        window.print();
        setTimeout(() => { printView.classList.add('hidden'); }, 500);
    }, 200);
}
</script>
