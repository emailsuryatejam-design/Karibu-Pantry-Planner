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
            <button onclick="printWholeDay(ordDate, ORD_KITCHEN_ID)" class="p-2 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 active:bg-gray-300 transition" title="Print whole day">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            </button>
            <button onclick="window.open('api/requisitions.php?action=day_pdf&date=' + ordDate + '&kitchen_id=' + ORD_KITCHEN_ID, '_blank')" class="p-2 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 active:bg-gray-300 transition" title="Download day PDF">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
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

    <!-- Past day = view only (back-dating is blocked) -->
    <div id="ordPastNote" class="hidden mb-3 flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2" class="shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <p class="text-[11px] text-amber-800 leading-snug">
            <span class="font-bold">This day has passed — view only.</span>
            Orders can only be made or changed for today onwards.
            <button onclick="ordGoToday()" class="underline font-semibold">Go to today</button>
        </p>
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

    <!-- New Custom Dish modal -->
    <div id="ordDishModal" class="hidden fixed inset-0 z-[215] bg-black/50 flex items-start justify-center pt-[8vh] p-4 animate-fade-in" onclick="if(event.target===this)ordCloseDishModal()">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full max-h-[84vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-bold text-gray-900">New dish</h3>
                    <p class="text-xs text-gray-400">Saved to your recipes &amp; added to this order</p>
                </div>
                <button onclick="ordCloseDishModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-5 py-3 space-y-3">
                <div>
                    <label class="text-xs font-semibold text-gray-500">Dish name</label>
                    <input id="ordDishName" type="text" placeholder="e.g. Grilled Kingfish"
                        class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 mt-1 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-300">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">For how many people</label>
                    <input id="ordDishServings" type="number" min="1" step="1"
                        class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 mt-1 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-300">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold text-gray-500">Ingredients</label>
                        <button type="button" onclick="ordAddIngRow()" class="text-xs text-orange-600 font-semibold hover:text-orange-700">+ Add ingredient</button>
                    </div>
                    <div id="ordDishIngs" class="space-y-2"></div>
                </div>
                <p class="text-[11px] text-gray-400 leading-relaxed">Type the total amount needed for the number of people above (e.g. 5 kg rice for 50). It scales automatically when you reuse the dish later.</p>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex gap-2">
                <button onclick="ordCloseDishModal()" class="flex-1 py-3 rounded-xl border-2 border-gray-200 text-gray-600 font-semibold text-sm hover:bg-gray-50">Cancel</button>
                <button onclick="ordSaveCustomDish()" id="ordDishSaveBtn" class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-semibold text-sm hover:bg-orange-600">Save &amp; add</button>
            </div>
        </div>
    </div>
    <datalist id="ordItemsDatalist"></datalist>

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
const ORD_KITCHEN_NAME = <?= json_encode($user['kitchen_name'] ?? '') ?>;
const ORD_UOM_OPTIONS = ['kg', 'g', 'ltr', 'ml', 'pcs', 'tins', 'box', 'pkt', 'bunch', 'bottle', 'unit'];

let ordDate = todayStr();
// Back-dating is blocked: a day that has passed is view-only for chefs (server enforces it too).
function ordIsPastDay() { return ordDate < todayStr(); }
let ordActiveTab = 'menu'; // 'menu' or 'staple'
let ordRequisitions = [];
let ordLinesByReq = {};
let ordAdjustments = {};
let ordAllItems = null; // cached for add-item
let ordCollapsed = {}; // reqId -> true/false
let ordDishBreakdown = {}; // reqId -> { itemId: [{dish_name, qty, uom}] }
let ordPackedDishes  = {}; // reqId -> [dish_name, ...]
let ordTypes = []; // all active requisition types (meal codes)

// Meal colors
const ordMealColors = {
    breakfast: { border: 'border-amber-300', bg: 'bg-amber-50', text: 'text-amber-700', header: 'bg-amber-50 border-amber-200' },
    lunch:     { border: 'border-blue-300', bg: 'bg-blue-50', text: 'text-blue-700', header: 'bg-blue-50 border-blue-200' },
    dinner:    { border: 'border-purple-300', bg: 'bg-purple-50', text: 'text-purple-700', header: 'bg-purple-50 border-purple-200' },
};
const ordDefaultColor = { border: 'border-gray-300', bg: 'bg-gray-50', text: 'text-gray-700', header: 'bg-gray-50 border-gray-200' };
function ordGetColor(meals) { return ordMealColors[(meals||'').toLowerCase()] || ordDefaultColor; }

// ══════════════════════════════════════════════
//  New custom dish (saves as recipe + adds to order)
// ══════════════════════════════════════════════
let ordDishReqId = null;
let ordItemByName = {};   // lowercase name -> { id, uom }

async function ordEnsureItems() {
    if (!ordAllItems) {
        try { const res = await api('api/items.php?action=list&active=1'); ordAllItems = res.items || []; }
        catch (e) { ordAllItems = []; }
    }
    ordItemByName = {};
    const dl = document.getElementById('ordItemsDatalist');
    if (dl) dl.innerHTML = ordAllItems.map(i => `<option value="${escHtml(i.name)}"></option>`).join('');
    ordAllItems.forEach(i => { ordItemByName[(i.name || '').toLowerCase()] = { id: i.id, uom: (i.uom || 'kg') }; });
}

function ordUomOptions(sel) {
    return ORD_UOM_OPTIONS.map(u => `<option ${u === sel ? 'selected' : ''}>${u}</option>`).join('');
}

function ordAddIngRow(name = '', qty = '', uom = 'kg') {
    const wrap = document.getElementById('ordDishIngs');
    const div = document.createElement('div');
    div.className = 'ord-ing-row flex gap-1.5 items-center';
    div.innerHTML = `
        <input list="ordItemsDatalist" value="${escHtml(name)}" oninput="ordIngNameChange(this)" placeholder="Item"
            class="ord-ing-name flex-1 min-w-0 text-sm border border-gray-200 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-orange-200">
        <input type="number" value="${qty}" min="0" step="0.1" placeholder="Qty"
            class="ord-ing-qty w-16 text-sm border border-gray-200 rounded-lg px-2 py-2 text-center focus:outline-none focus:ring-2 focus:ring-orange-200">
        <select class="ord-ing-uom w-20 text-sm border border-gray-200 rounded-lg px-1 py-2 bg-white">${ordUomOptions(uom)}</select>
        <button type="button" onclick="this.closest('.ord-ing-row').remove()" class="text-gray-300 hover:text-red-500 px-1 shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>`;
    wrap.appendChild(div);
}

function ordIngNameChange(input) {
    const m = ordItemByName[(input.value || '').trim().toLowerCase()];
    if (!m) return;
    const sel = input.closest('.ord-ing-row').querySelector('.ord-ing-uom');
    if (sel && m.uom) { for (const o of sel.options) if (o.value === m.uom) { sel.value = m.uom; break; } }
}

async function ordNewDish(reqId, guests) {
    ordDishReqId = reqId;
    await ordEnsureItems();
    document.getElementById('ordDishName').value = '';
    document.getElementById('ordDishServings').value = guests || 20;
    document.getElementById('ordDishIngs').innerHTML = '';
    ordAddIngRow(); ordAddIngRow(); ordAddIngRow();
    document.getElementById('ordDishModal').classList.remove('hidden');
}

function ordCloseDishModal() { document.getElementById('ordDishModal').classList.add('hidden'); }

async function ordSaveCustomDish() {
    const name = document.getElementById('ordDishName').value.trim();
    const servings = parseInt(document.getElementById('ordDishServings').value) || 0;
    if (!name) { showToast('Enter a dish name', 'warning'); return; }
    const ings = [];
    document.querySelectorAll('#ordDishIngs .ord-ing-row').forEach(row => {
        const nm = row.querySelector('.ord-ing-name').value.trim();
        const qty = parseFloat(row.querySelector('.ord-ing-qty').value) || 0;
        const uom = row.querySelector('.ord-ing-uom').value;
        if (nm && qty > 0) {
            const m = ordItemByName[nm.toLowerCase()];
            ings.push({ item_id: m ? m.id : null, item_name: nm, qty, uom, is_primary: 1 });
        }
    });
    if (!ings.length) { showToast('Add at least one ingredient with a quantity', 'warning'); return; }
    const btn = document.getElementById('ordDishSaveBtn');
    setLoading(btn, true);
    try {
        const res = await api('api/requisitions.php?action=add_custom_dish', {
            method: 'POST',
            body: JSON.stringify({ requisition_id: ordDishReqId, dish_name: name, servings, ingredients: ings })
        });
        showToast(res.reused ? `Added existing dish "${name}"` : `"${name}" saved & added (${res.lines_added} item${res.lines_added === 1 ? '' : 's'})`, 'success');
        ordCloseDishModal();
        await ordLoad();
    } catch (e) {
        showToast(e.message || 'Could not add dish', 'error');
    } finally {
        setLoading(btn, false);
    }
}

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
// One place keeps the date label, the "Back to Today" link and the past-day note in step.
function ordSyncDateUI() {
    document.getElementById('ordDateDisplay').textContent = formatDate(ordDate);
    document.getElementById('ordTodayBtn').classList.toggle('hidden', ordDate === todayStr());
    document.getElementById('ordPastNote').classList.toggle('hidden', !ordIsPastDay());
}

ordSyncDateUI();
ordLoad();

function ordChangeDate(days) {
    ordDate = changeDate(ordDate, days);
    ordSyncDateUI();
    ordLoad();
}

function ordGoToday() {
    ordDate = todayStr();
    ordSyncDateUI();
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
            api(`api/requisitions.php?action=get&id=${r.id}&include_off=1`).then(data => {
                ordLinesByReq[r.id] = data.lines || [];
            }).catch(() => { ordLinesByReq[r.id] = []; })
        ));

        // Fetch dish breakdown for each requisition (includes packed dishes)
        ordDishBreakdown  = {};
        ordPackedDishes   = {}; // reqId -> [{recipe_name}]
        await Promise.all(ordRequisitions.map(r =>
            api(`api/requisitions.php?action=get_dishes_with_ingredients&requisition_id=${r.id}`).then(data => {
                const dishes = data.dishes || [];
                const ingsByRecipe = data.ingredients_by_recipe || {};
                const breakdown = {}; // itemId -> [{dish_name, qty, uom}]
                const seenRecipes = new Set();
                for (const dish of dishes) {
                    if (dish.is_packed) continue; // packed dishes have no ingredients
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
                ordPackedDishes[r.id]  = (data.packed_dishes || []).map(d => d.recipe_name);
            }).catch(() => { ordDishBreakdown[r.id] = {}; ordPackedDishes[r.id] = []; })
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

async function ordRefreshCard(reqId) {
    const req = ordRequisitions.find(r => r.id == reqId);
    if (!req) { ordLoad(); return; }
    try {
        const [linesData, dishData] = await Promise.all([
            api(`api/requisitions.php?action=get&id=${reqId}&include_off=1`),
            api(`api/requisitions.php?action=get_dishes_with_ingredients&requisition_id=${reqId}`)
        ]);
        ordLinesByReq[reqId] = linesData.lines || [];
        const dishes = dishData.dishes || [];
        const ingsByRecipe = dishData.ingredients_by_recipe || {};
        const breakdown = {};
        const seenRecipes = new Set();
        for (const dish of dishes) {
            if (dish.is_packed) continue;
            if (seenRecipes.has(dish.recipe_id)) continue;
            seenRecipes.add(dish.recipe_id);
            const ings = ingsByRecipe[dish.recipe_id] || [];
            const guestCount = parseInt(dish.guest_count) || 20;
            const servings = parseInt(dish.recipe_servings) || 4;
            const scale = guestCount / servings;
            for (const ing of ings) {
                if (!breakdown[ing.item_id]) breakdown[ing.item_id] = [];
                breakdown[ing.item_id].push({ dish_name: dish.recipe_name, qty: parseFloat(ing.qty) * scale, uom: ing.uom || 'kg' });
            }
        }
        ordDishBreakdown[reqId] = breakdown;
        ordPackedDishes[reqId]  = (dishData.packed_dishes || []).map(d => d.recipe_name);
        const cardEl = document.getElementById(`ord-card-${reqId}`);
        if (cardEl) {
            cardEl.outerHTML = ordRenderCard(req);
        } else {
            ordRender();
        }
    } catch(err) { showToast(err.message, 'error'); }
}

// ── Orange on/off toggle for an order item (mirrors the Recipes toggle) ──
// on=1 → start ordering it again; on=0 → stop ordering it. Takes effect on THIS
// order right now AND on future orders (flips the recipe's orange dot camp-wide).
async function ordToggleLine(lineId, reqId, on) {
    try {
        const res = await api('api/requisitions.php?action=toggle_line', {
            method: 'POST',
            body: { line_id: lineId, on: on }
        });
        if (on) {
            showToast('Added back — the store will get it again', 'success');
        } else {
            showToast(res.recipe_synced > 0
                ? 'Removed — and it won’t come in future orders'
                : 'Removed from this order', 'success');
        }
        await ordRefreshCard(reqId);
    } catch (err) {
        showToast(err.message || 'Could not change that item', 'error');
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

    // Also show any requisitions whose meal type is NOT in ordTypes (edge case).
    // Never render the standalone staples order as a meal card — it belongs to the Staple tab.
    ordRequisitions.forEach(req => {
        const code = (req.meals || '').toLowerCase();
        if (!shownMeals.has(code) && code !== 'staples') {
            html += ordRenderCard(req);
        }
    });

    // Fallback: if ordTypes is empty but we have requisitions, show them all (except staples)
    if (ordTypes.length === 0) {
        ordRequisitions.forEach(req => { if ((req.meals || '').toLowerCase() !== 'staples') html += ordRenderCard(req); });
    }

    list.innerHTML = html;
}

// ── Empty meal card (no requisition yet for this meal type) ──
function ordRenderEmptyMealCard(mealCode, mealName) {
    const color = ordGetColor(mealCode);
    return `<div class="bg-white rounded-xl border ${color.border} overflow-hidden shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 ${color.header} border-b">
            <span class="text-sm font-bold ${color.text}">${escHtml(mealName)} Order</span>
            <a href="app.php?page=dashboard"
                class="px-3 py-1.5 rounded-lg bg-orange-500 text-white text-xs font-semibold hover:bg-orange-600 active:bg-orange-700 transition flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                Plan on Home
            </a>
        </div>
        <div class="px-4 py-4 text-center">
            <p class="text-sm text-gray-400">No menu yet — plan the dishes on Home. Bulk staples go in the Staple tab.</p>
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

// A "meal extra" (one-off dietary item) can only be added once the meal is generated (has a menu).
// Bulk staples never come through here — they go to the Staple tab / staples order.
function ordCanAddMealExtra(req) {
    return ['processing', 'submitted'].includes(req.status) && !ordIsPastDay();
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

    const canAddItems = ['draft', 'processing', 'submitted'].includes(req.status) && !ordIsPastDay();

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
                ${ordCanAddMealExtra(req) ? `<button onclick="event.stopPropagation();ordShowAddItem(${req.id}, 'meal_extra')" class="w-8 h-8 rounded-lg bg-white/80 border border-green-300 text-green-600 flex items-center justify-center hover:bg-green-50 active:bg-green-100 transition" title="Add extra item to this meal">
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
                ${ordCanAddMealExtra(req) ? `<button onclick="ordShowAddItem(${req.id}, 'meal_extra')"
                    class="px-3 py-1.5 rounded-lg bg-orange-500 text-white text-xs font-semibold hover:bg-orange-600 active:bg-orange-700 transition flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    Add extra
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

    const ordActiveLineCount = lines.filter(l => l.status !== 'rejected').length;
    const isCollapsed = !!ordCollapsed[req.id];
    const chevronSvg = isCollapsed
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>';

    let html = `<div class="bg-white rounded-xl border ${color.border} overflow-hidden shadow-sm" id="ord-card-${req.id}">`;
    html += `<div class="flex items-center justify-between px-4 py-3 ${color.header} border-b cursor-pointer select-none" onclick="ordToggleCollapse(${req.id})">
        <div class="flex items-center gap-2">
            <span class="text-sm font-bold ${color.text}">${escHtml(mealLabel)} Order</span>
            <span class="text-[10px] text-gray-400">#${req.id}</span>
            <span class="text-[10px] text-gray-500 font-medium">${ordActiveLineCount} item${ordActiveLineCount !== 1 ? 's' : ''}</span>
        </div>
        <div class="flex items-center gap-2">
            ${ordStatusBadge(req.status)}
            <button onclick="event.stopPropagation(); ordShowChangeLog(${req.id})" class="w-6 h-6 rounded-md text-gray-400 hover:text-orange-500 hover:bg-orange-50 flex items-center justify-center transition" title="Change History">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </button>
            <span class="text-gray-400">${chevronSvg}</span>
        </div>
    </div>`;

    if (!isCollapsed) {
        const isEditable = ['draft', 'processing', 'submitted'].includes(req.status) && !ordIsPastDay();
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

        // ── Packed Dishes (breakfast / no-recipe items) ──
        const packedList = ordPackedDishes[req.id] || [];
        const isBreakfast = (req.meals || '').toLowerCase() === 'breakfast';
        if (packedList.length > 0 || (isBreakfast && isEditable)) {
            const packedChips = packedList.map(name =>
                `<span class="inline-flex items-center gap-1 bg-amber-50 border border-amber-200 text-amber-800 text-[10px] font-medium rounded-full px-2.5 py-0.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
                    ${escHtml(name)}
                    ${isEditable ? `<button onclick="ordRemovePackedDish(${req.id}, '${name.replace(/'/g, "\\'")}')" class="ml-0.5 text-amber-400 hover:text-red-500 transition leading-none">×</button>` : ''}
                </span>`
            ).join('');
            html += `<div class="px-4 py-2.5 border-b border-amber-100 bg-amber-50/40">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] font-semibold text-amber-700 uppercase tracking-wider flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="16" height="13" x="4" y="5" rx="2"/><path d="M16 2v4M8 2v4M4 9h16"/></svg>
                        Packed Dishes
                    </span>
                    ${isEditable ? `<button onclick="ordShowAddPackedDish(${req.id})"
                        class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-700 text-[10px] font-semibold transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                        Add
                    </button>` : ''}
                </div>
                <div class="flex flex-wrap gap-1.5">
                    ${packedChips || `<span class="text-[10px] text-amber-500 italic">No packed dishes yet — tap Add to list breakfast items</span>`}
                </div>
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
                    <th class="px-1 py-1.5 w-10" title="Tap the dot to stop / start ordering an item"></th>
                    <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">Item</th>
                    <th class="text-center px-1 py-1.5 text-blue-600 font-semibold w-14" title="Recipe requirement">Req</th>
                    <th class="text-center px-1 py-1.5 text-amber-600 font-semibold w-14" title="Already in kitchen">Stock</th>
                    <th class="text-center px-1 py-1.5 text-green-600 font-semibold w-20" title="What the store will send — you can change this">Order</th>
                    <th class="text-center px-1 py-1.5 w-8"></th>
                </tr></thead>
                <tbody>`;

    lines.forEach(line => {
        const calcQty = parseFloat(line.required_kg || line.calc_qty || line.required_qty || 0);
        const stockQty = parseFloat(line.stock_qty) || 0;
        const orderQty = parseFloat(line.order_qty) || 0;
        if (ordAdjustments[line.id] === undefined) ordAdjustments[line.id] = orderQty;
        const currentQty = ordAdjustments[line.id];
        const isOff = line.status === 'rejected'; // toggled off = not ordered (this & future orders)

        // Dish breakdown for this item
        const dishSources = (ordDishBreakdown[req.id] || {})[line.item_id] || [];
        const breakdownHtml = dishSources.length > 0
            ? dishSources.map(s => `<span class="inline-flex items-center gap-0.5"><span class="text-gray-500">${escHtml(s.dish_name)}</span> <span class="text-blue-500 font-medium">${s.qty.toFixed(1)}</span></span>`).join('<span class="text-gray-300 mx-0.5">&middot;</span>')
            : '';

        html += `<tr class="border-b border-gray-50 ${isOff ? 'bg-gray-50/70' : ''}">
            <td class="px-1 py-2 text-center align-middle">
                <button onclick="event.stopPropagation(); ordToggleLine(${line.id}, ${req.id}, ${isOff ? 1 : 0})"
                    title="${isOff ? 'Not ordered — tap to start ordering this item' : 'Ordered from store — tap to stop ordering it (now and next time)'}"
                    class="w-5 h-5 rounded-full shrink-0 inline-flex items-center justify-center transition-colors compact-btn ${isOff ? 'bg-gray-200 hover:bg-gray-300' : 'bg-orange-400 hover:bg-orange-300'}">
                    ${isOff ? '' : '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>'}
                </button>
            </td>
            <td class="px-2 py-2">
                <p class="text-xs font-medium truncate ${isOff ? 'text-gray-400 line-through' : 'text-gray-800'}">${escHtml(line.item_name)}</p>
                <p class="text-[9px] text-gray-400">${escHtml(line.uom || 'kg')}</p>
                ${breakdownHtml ? `<div class="text-[9px] text-gray-400 mt-0.5 leading-tight">${breakdownHtml}</div>` : ''}
            </td>
            <td class="text-center px-1 py-2 font-medium text-xs ${isOff ? 'text-gray-300' : 'text-blue-700'}">${calcQty > 0 ? calcQty.toFixed(1) : '—'}</td>
            <td class="text-center px-1 py-2 font-medium text-xs ${isOff ? 'text-gray-300' : 'text-amber-600'}">${stockQty > 0 ? stockQty.toFixed(1) : '—'}</td>
            <td class="text-center px-1 py-2">
                ${isOff
                    ? `<span class="inline-block w-16 text-center text-[10px] font-semibold text-gray-400">Not<br>ordered</span>`
                    : `<input type="number" value="${currentQty}" step="0.5" min="0"
                        onchange="ordAdjustments[${line.id}] = parseFloat(this.value)||0"
                        class="w-16 text-center text-xs font-bold border border-green-300 rounded-lg py-1 bg-green-50 focus:outline-none focus:ring-1 focus:ring-green-300">`}
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
        <button onclick="ordShowAddItem(${req.id}, 'meal_extra')" title="Add an extra item to this meal (not a bulk staple)"
            class="px-4 py-3 rounded-xl border-2 border-orange-200 text-orange-500 font-semibold text-sm hover:bg-orange-50 flex items-center justify-center gap-1.5 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        </button>
        <button onclick="printOrder(${req.id}, ORD_KITCHEN_NAME, true)" title="Print this meal"
            class="px-4 py-3 rounded-xl border-2 border-gray-200 text-gray-500 font-semibold text-sm hover:bg-gray-50 flex items-center justify-center gap-1.5 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
        </button>
        <button onclick="ordSubmitToStore(${req.id})" id="ord-submit-${req.id}"
            class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            Submit to Store
        </button>
    </div>`;

    // Add a brand-new dish that isn't in the recipe book — saves it as a recipe AND adds it here
    html += `<button onclick="ordNewDish(${req.id}, ${parseInt(req.guest_count) || 20})"
        class="w-full mt-2 py-2.5 rounded-xl border-2 border-dashed border-purple-200 text-purple-600 font-semibold text-xs hover:bg-purple-50 flex items-center justify-center gap-1.5 transition">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 11h.01"/><path d="M11 15h.01"/><path d="M16 16h.01"/><path d="m2 16 20 6-6-20A20 20 0 0 0 2 16"/><path d="M5.71 17.11a17.04 17.04 0 0 1 11.4-11.4"/></svg>
        Add a new dish (not in recipes) — saved for next time
    </button>`;

    html += '</div>';
    return html;
}

// ── Read-only lines — table with dish breakdown ──
function ordRenderReadOnlyLines(req, lines) {
    lines = lines.filter(l => l.status !== 'rejected'); // hide toggled-off / store-removed items
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

    html += `</tbody></table></div>
        <div class="mt-3">
            <button onclick="printOrder(${req.id}, ORD_KITCHEN_NAME, true)"
                class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 text-gray-600 font-semibold text-sm hover:bg-gray-50 flex items-center justify-center gap-2 transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                Print this meal
            </button>
        </div>
    </div>`;
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

    const isEditable = allStapleLines.some(l => ['draft', 'processing', 'submitted'].includes(l.reqStatus)) && !ordIsPastDay();

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

    const uomOptions = ['kg','grams','pcs','ltr','ml','bunch','pkt'];
    document.getElementById('ordItemDetailContent').innerHTML = `
        <h3 class="text-base font-bold text-gray-900 mb-1">${escHtml(line.item_name)}</h3>
        <p class="text-xs text-gray-400 mb-4">Edit quantity or remove</p>
        <div class="space-y-3">
            <div>
                <label class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider mb-1 block">Unit of Measure</label>
                <select id="ordEditUom" class="w-full border-2 border-gray-200 rounded-xl py-2.5 px-3 text-sm font-semibold text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-200 bg-white">
                    ${uomOptions.map(u => `<option value="${u}"${u === uom ? ' selected' : ''}>${u}</option>`).join('')}
                </select>
            </div>
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
        // Update local line data so card re-renders with new UOM immediately
        const lines = ordLinesByReq[reqId] || [];
        const line = lines.find(l => parseInt(l.id) === lineId);
        if (line) { line.order_qty = qty; line.uom = uom; }
        ordCloseItemDetail();
        showToast('Item updated');
        ordRefreshCard(reqId);
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
        ordRefreshCard(reqId);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ══════════════════════════════════════════════
//  Change History Log
// ══════════════════════════════════════════════
async function ordShowChangeLog(reqId) {
    const actionLabels = {
        update_line:        'Line updated',
        add_line_to_order:  'Line added',
        chef_remove_line:   'Line removed',
        requisition_submit: 'Order submitted',
        requisition_create: 'Order created',
        recalculate_order:  'Guest count changed',
        requisition_fulfill:'Order fulfilled by store',
        admin_close:        'Order closed by admin',
        confirm_receipt:    'Receipt confirmed',
    };

    function fmtDiff(oldRaw, newRaw) {
        try {
            const o = oldRaw ? JSON.parse(oldRaw) : null;
            const n = newRaw ? JSON.parse(newRaw) : null;
            if (!o && !n) return '';
            const parts = [];
            const keys = new Set([...Object.keys(o || {}), ...Object.keys(n || {})]);
            keys.forEach(k => {
                const ov = o?.[k], nv = n?.[k];
                if (ov !== undefined && nv !== undefined && String(ov) !== String(nv)) {
                    parts.push(`<span class="text-gray-500">${k}:</span> <span class="line-through text-red-400">${escHtml(String(ov))}</span> → <span class="text-green-600 font-semibold">${escHtml(String(nv))}</span>`);
                } else if (nv !== undefined && ov === undefined) {
                    parts.push(`<span class="text-gray-500">${k}:</span> <span class="text-green-600">${escHtml(String(nv))}</span>`);
                } else if (ov !== undefined && nv === undefined) {
                    parts.push(`<span class="text-gray-500">${k}:</span> <span class="text-red-400">${escHtml(String(ov))}</span>`);
                }
            });
            return parts.length ? `<div class="text-xs mt-1 space-y-0.5">${parts.join('<br>')}</div>` : '';
        } catch { return ''; }
    }

    function relTime(ts) {
        if (!ts) return '';
        const d = new Date(ts.replace(' ', 'T') + 'Z');
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return d.toLocaleDateString('en-GB', {day:'numeric', month:'short'});
    }

    document.getElementById('ordItemDetailContent').innerHTML = `<p class="text-sm text-gray-400 text-center py-4">Loading history…</p>`;
    document.getElementById('ordItemDetailModal').classList.remove('hidden');

    try {
        const data = await api(`api/requisitions.php?action=change_log&requisition_id=${reqId}`);
        const entries = data.log || [];
        document.getElementById('ordItemDetailContent').innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-bold text-gray-900">Change History</h3>
                <span class="text-[10px] text-gray-400">#${reqId}</span>
            </div>
            ${entries.length === 0
                ? `<p class="text-sm text-gray-400 text-center py-4">No changes recorded yet</p>`
                : entries.map(e => `
                    <div class="border-b border-gray-100 py-2.5 last:border-0">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-700">${escHtml(actionLabels[e.action] || e.action)}</span>
                            <span class="text-[10px] text-gray-400">${relTime(e.created_at)}</span>
                        </div>
                        <div class="text-[11px] text-gray-500 mt-0.5">${escHtml(e.user_name || 'System')}</div>
                        ${fmtDiff(e.old_value, e.new_value)}
                    </div>`).join('')
            }`;
    } catch (err) {
        document.getElementById('ordItemDetailContent').innerHTML = `<p class="text-sm text-red-400 text-center py-4">${escHtml(err.message)}</p>`;
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
// ══════════════════════════════════════════════
//  Packed Dish (no recipe) — breakfast
// ══════════════════════════════════════════════

let ordPackedCatalogue = null; // cached list of is_packed=1 recipes

async function ordShowAddPackedDish(reqId) {
    document.getElementById('ordPackedDishInput')?.remove();

    // Load packed dish catalogue (cached)
    if (!ordPackedCatalogue) {
        try {
            const res = await api('api/recipes.php?action=list&is_packed=1');
            ordPackedCatalogue = res.recipes || [];
        } catch(e) { ordPackedCatalogue = []; }
    }

    const already = ordPackedDishes[reqId] || [];

    const listHtml = ordPackedCatalogue.length > 0
        ? ordPackedCatalogue.map(r => {
            const taken = already.includes(r.name);
            return `<button ${taken ? 'disabled' : `onclick="ordPickPackedDish(${reqId}, '${r.name.replace(/'/g,"\\'")}')"` }
                class="w-full flex items-center gap-3 px-3 py-3 ${taken ? 'opacity-40 cursor-default' : 'hover:bg-amber-50 active:bg-amber-100'} border-b border-gray-100 last:border-0 text-left transition">
                <span class="text-lg">📦</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${escHtml(r.name)}</p>
                    <p class="text-[10px] text-gray-400">${escHtml(r.category || 'Ready-made')}${taken ? ' · <span class="text-green-500">Already added</span>' : ''}</p>
                </div>
                ${taken ? '' : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>'}
            </button>`;
          }).join('')
        : `<p class="text-center text-xs text-gray-400 py-4">No packed dishes in catalogue yet.<br>
           Admin can mark recipes as "Out of Box" to add them here.</p>`;

    const div = document.createElement('div');
    div.id = 'ordPackedDishInput';
    div.className = 'fixed inset-0 z-[220] bg-black/50 flex items-end justify-center';
    div.innerHTML = `
        <div class="bg-white w-full max-w-md rounded-t-2xl shadow-xl" style="max-height:80vh;display:flex;flex-direction:column">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
                <h3 class="text-sm font-bold text-gray-900">📦 Add Out-of-Box Dish</h3>
                <button onclick="document.getElementById('ordPackedDishInput').remove()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div class="overflow-y-auto flex-1 px-2 py-2">
                ${listHtml}
            </div>
            <div class="px-5 py-3 border-t border-gray-100 shrink-0">
                <p class="text-[10px] text-gray-400 text-center">Can't find it? Type a custom name:</p>
                <div class="flex gap-2 mt-1.5">
                    <input type="text" id="ordPackedDishCustom" placeholder="Custom dish name…" maxlength="150"
                        class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-200">
                    <button onclick="ordConfirmAddPackedDish(${reqId})"
                        class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition">Add</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(div);
    document.getElementById('ordPackedDishCustom').addEventListener('keydown', e => {
        if (e.key === 'Enter') ordConfirmAddPackedDish(reqId);
    });
}

async function ordPickPackedDish(reqId, dishName) {
    document.getElementById('ordPackedDishInput')?.remove();
    try {
        await api('api/requisitions.php?action=add_packed_dish', {
            method: 'POST', body: { requisition_id: reqId, dish_name: dishName }
        });
        showToast(`"${dishName}" added`, 'success');
        ordRefreshCard(reqId);
    } catch (err) { showToast(err.message, 'error'); }
}

async function ordConfirmAddPackedDish(reqId) {
    const name = (document.getElementById('ordPackedDishCustom')?.value || '').trim();
    if (!name) { showToast('Enter a dish name', 'error'); return; }
    document.getElementById('ordPackedDishInput')?.remove();
    try {
        await api('api/requisitions.php?action=add_packed_dish', {
            method: 'POST', body: { requisition_id: reqId, dish_name: name }
        });
        showToast(`"${name}" added`, 'success');
        ordRefreshCard(reqId);
    } catch (err) { showToast(err.message, 'error'); }
}

async function ordRemovePackedDish(reqId, dishName) {
    try {
        await api('api/requisitions.php?action=remove_packed_dish', {
            method: 'POST',
            body: { requisition_id: reqId, dish_name: dishName }
        });
        showToast('Dish removed', 'success');
        ordRefreshCard(reqId);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

//  Add Item Modal
// ══════════════════════════════════════════════
let ordAddTargetReqId = null;
let ordAddIntent = 'staple'; // 'staple' = bulk → the day's staples order; 'meal_extra' = stays on the meal

async function ordShowAddItem(reqId, intent) {
    ordAddIntent = intent === 'meal_extra' ? 'meal_extra' : 'staple';
    let targetReq = null;
    if (reqId) {
        targetReq = ordRequisitions.find(r => r.id == reqId);
    }
    // For a bulk staple we just need any order for context (the server routes it to the staples
    // order); prefer a real meal, never the staples order itself.
    if (!targetReq && ordAddIntent === 'staple') {
        targetReq = ordRequisitions.find(r => ['draft', 'processing', 'submitted'].includes(r.status) && (r.meals || '').toLowerCase() !== 'staples')
                 || ordRequisitions.find(r => ['draft', 'processing', 'submitted'].includes(r.status));
    }
    if (!targetReq) {
        try {
            showToast('Opening…', 'info');
            const initData = await api('api/requisitions.php?action=page_init', {
                method: 'POST',
                body: { req_date: ordDate, kitchen_id: ORD_KITCHEN_ID, guest_count: 20 }
            });
            const newReqs = initData.requisitions || [];
            if (newReqs.length > 0) targetReq = newReqs.find(r => (r.meals||'').toLowerCase() !== 'staples') || newReqs[0];
        } catch (e) {}
    }
    if (!targetReq) { showToast('Could not open the add screen. Try again.', 'error'); return; }
    ordAddTargetReqId = targetReq.id;

    const titleEl = document.getElementById('ordAddModalTitle');
    const subEl = document.getElementById('ordAddModalSub');
    if (ordAddIntent === 'meal_extra') {
        // A one-off item for THIS meal — stays with the meal.
        if (titleEl) titleEl.textContent = 'Add extra item to this meal';
        if (subEl) subEl.textContent = 'A one-off item for this meal — stays with it and is sent with it.';
    } else {
        // Bulk staple → the day's separate staples order. Show that tab so the chef sees where it lands.
        if (ordActiveTab !== 'staple') ordSwitchTab('staple');
        if (titleEl) titleEl.textContent = 'Add bulk staple';
        if (subEl) subEl.textContent = 'Salt, sugar, oil… goes to the day’s staples order, not a meal.';
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
        // Bulk staple → the server routes it to the day's staples order; meal_extra → stays on the meal.
        await api('api/requisitions.php?action=add_line_to_order', {
            method: 'POST',
            body: { requisition_id: ordAddTargetReqId, req_date: ordDate, item_id: itemId, item_name: itemName, order_qty: qty, uom: uom, intent: ordAddIntent }
        });
        showToast(ordAddIntent === 'meal_extra' ? `${itemName} added to this meal` : `${itemName} added to staples`, 'success');
        ordCloseItemDetail();
        ordLoad(); // reload — a bulk staple lands on the separate staples order, not this card
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
            const allLines = (ordLinesByReq[reqId] || []).filter(l => parseInt(l.is_staple) === 1 && l.status !== 'rejected');
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

const _ordSubmitting = new Set(); // guard against double-tap

async function ordSubmitToStore(reqId) {
    // Fix #6: block double-submit
    if (_ordSubmitting.has(reqId)) { showToast('Already submitting…', 'error'); return; }

    const allLines = (ordLinesByReq[reqId] || []).filter(l => l.status !== 'rejected');
    if (allLines.length === 0) { showToast('No items to submit', 'error'); return; }

    const lineData = allLines.map(line => ({
        id: parseInt(line.id),
        order_qty: ordAdjustments[line.id] !== undefined ? ordAdjustments[line.id] : (parseFloat(line.order_qty) || 0)
    }));

    const nonZero = lineData.filter(l => l.order_qty > 0);
    if (nonZero.length === 0) { showToast('All quantities are zero', 'error'); return; }

    // Fix #7: warn on very low guest count
    const req = ordRequisitions.find(r => parseInt(r.id) === parseInt(reqId));
    const gc = req ? parseInt(req.guest_count) : 0;
    if (gc > 0 && gc < 3) {
        if (!await customConfirm('Low Guest Count', `This order is for only ${gc} guest${gc !== 1 ? 's' : ''}. Is that correct?`)) return;
    }

    const zeroCount = lineData.length - nonZero.length;
    const msg = zeroCount > 0
        ? `${zeroCount} item${zeroCount > 1 ? 's have' : ' has'} zero qty and will be skipped. Submit ${nonZero.length} item${nonZero.length > 1 ? 's' : ''}?`
        : `Send ${nonZero.length} item${nonZero.length > 1 ? 's' : ''} to the store?`;

    if (!await customConfirm('Submit to Store', msg)) return;

    _ordSubmitting.add(reqId);
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
        _ordSubmitting.delete(reqId);
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
        html += '<th style="width:34%">Item Name</th>';
        html += '<th style="width:11%">UOM</th>';
        html += '<th style="width:15%">Req Qty</th>';
        html += '<th style="width:15%">Stock</th>';
        html += '<th style="width:15%">Order Qty</th>';
        html += '</tr></thead><tbody>';

        group.lines.forEach((line, idx) => {
            const calcQty = parseFloat(line.required_kg || line.calc_qty || line.required_qty || 0);
            const stockQty = parseFloat(line.stock_qty) || 0;
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
            html += '<td class="num">' + (stockQty > 0 ? stockQty.toFixed(1) : '-') + '</td>';
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
