<?php
/**
 * Karibu Pantry Planner — Requisition Page
 * Auto-creates one requisition per type (Breakfast, Lunch, Dinner, etc.)
 * Chef picks dishes (recipes) and ingredients auto-populate
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
$kitchenName = $user['kitchen_name'] ?? 'No Kitchen';
?>

<!-- Date Strip -->
<div class="flex items-center gap-2 mb-3">
    <button onclick="rqNavDate(-1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button>
    <div class="flex-1 text-center">
        <button onclick="rqShowDatePicker()" class="text-sm font-semibold text-gray-800" id="rqDateDisplay"></button>
        <div class="text-[10px] text-gray-400" id="rqDateRelative"></div>
    </div>
    <button onclick="rqNavDate(1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50" id="rqNextBtn">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>
</div>

<!-- Requisition Type Tabs (auto-created per type) -->
<div class="flex gap-2 mb-3 overflow-x-auto pb-1" id="rqSessionTabs">
    <span class="text-[10px] text-gray-400 py-2">Loading...</span>
</div>

<!-- Active Requisition Card -->
<div id="rqSessionCard" class="hidden">
    <!-- Type Header -->
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 mb-3 flex items-center justify-between">
        <div>
            <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider" id="rqTypeLabel">Requisition</div>
            <div class="text-sm font-bold text-gray-800" id="rqTypeName"></div>
        </div>
        <div id="rqStatusPill"></div>
    </div>

    <!-- Guest Count -->
    <div class="bg-white rounded-xl border border-gray-200 p-3 mb-3 flex items-center justify-between">
        <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Guest Count</div>
        <input type="number" id="rqGuestCount" value="20" min="1" onchange="rqSetGuests(this.value)"
            class="w-20 text-center text-lg font-bold text-gray-800 border border-gray-200 rounded-lg py-1 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
    </div>

    <!-- Dish Search -->
    <div class="relative mb-3" id="rqDishSearchWrap">
        <input type="text" id="rqDishSearch" placeholder="Search dishes by name..." oninput="rqSearchDishesDebounced()"
            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
        <svg class="absolute left-3 top-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    </div>
    <!-- Dish Search Results -->
    <div id="rqDishResults" class="mb-3 hidden"></div>

    <!-- Selected Dishes -->
    <div id="rqSelectedDishes" class="mb-3"></div>

    <!-- Aggregated Ingredients -->
    <div id="rqAggregatedItems"></div>

    <!-- Status Banner (for submitted/fulfilled requisitions) -->
    <div id="rqStatusBanner" class="hidden"></div>
</div>

<!-- Sticky Bottom Bar -->
<div id="rqBottomBar" class="fixed bottom-16 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2.5 z-40 hidden">
    <div class="max-w-2xl mx-auto flex items-center justify-between">
        <div>
            <span class="text-xs text-gray-500" id="rqSummaryLabel">Dishes: <strong class="text-gray-800">0</strong></span>
            <span class="mx-2 text-gray-300">|</span>
            <span class="text-xs text-gray-500">Order: <strong id="rqSummaryKg" class="text-orange-600">0 kg</strong></span>
        </div>
        <button onclick="rqSaveAndSubmit()" id="rqSubmitBtn" class="bg-orange-500 text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-orange-600 transition">
            Save & Submit
        </button>
    </div>
</div>

<script>
// ── State ──
let rqDate = todayStr();
let rqSessions = [];
let rqActiveSession = null;
let rqGuestCount = 20;
let rqTypes = [];

// Dish state
let rqDishes = {};
let rqAggregatedItems = {};
let rqDishSearchResults = [];

const RQ_MAX_DAYS_AHEAD = 7;
const RQ_KITCHEN_ID = <?= (int)$kitchenId ?>;

// ── Init ──
rqRenderDate();
rqInit();

const rqSearchDishesDebounced = debounce(() => rqSearchDishes(), 350);

async function rqInit() {
    // Load types first, then sessions (which auto-creates if needed)
    await rqLoadTypes();
    await rqLoadSessions();
}

// ── Date Navigation ──
function rqNavDate(days) {
    const next = changeDate(rqDate, days);
    const today = todayStr();
    const maxDate = changeDate(today, RQ_MAX_DAYS_AHEAD);
    if (next < today || next > maxDate) return;
    rqDate = next;
    rqRenderDate();
    rqLoadSessions();
}

function rqRenderDate() {
    document.getElementById('rqDateDisplay').textContent = formatDate(rqDate);
    const today = todayStr();
    if (rqDate === today) {
        document.getElementById('rqDateRelative').textContent = 'Today';
    } else {
        const diff = Math.round((new Date(rqDate) - new Date(today)) / 86400000);
        document.getElementById('rqDateRelative').textContent = diff > 0 ? `+${diff} day${diff > 1 ? 's' : ''}` : `${diff} day${diff < -1 ? 's' : ''}`;
    }
    const maxDate = changeDate(today, RQ_MAX_DAYS_AHEAD);
    document.getElementById('rqNextBtn').disabled = rqDate >= maxDate;
    document.getElementById('rqNextBtn').style.opacity = rqDate >= maxDate ? '0.3' : '1';
}

function rqShowDatePicker() {
    const today = todayStr();
    let html = '<div class="p-4"><h3 class="text-sm font-semibold text-gray-800 mb-3">Select Date</h3><div class="grid grid-cols-4 gap-2">';
    for (let i = 0; i <= RQ_MAX_DAYS_AHEAD; i++) {
        const d = changeDate(today, i);
        const isActive = d === rqDate;
        const dayName = i === 0 ? 'Today' : (i === 1 ? 'Tomorrow' : new Date(d + 'T00:00:00').toLocaleDateString('en', {weekday: 'short'}));
        const dayNum = new Date(d + 'T00:00:00').getDate();
        html += `<button onclick="rqDate='${d}';rqRenderDate();rqLoadSessions();closeSheet()"
            class="flex flex-col items-center p-2 rounded-xl border ${isActive ? 'bg-orange-500 text-white border-orange-500' : 'bg-white border-gray-200 text-gray-700 hover:bg-orange-50'}">
            <span class="text-[10px] font-medium">${dayName}</span>
            <span class="text-lg font-bold">${dayNum}</span>
        </button>`;
    }
    html += '</div></div>';
    openSheet(html);
}

// ── Types ──
async function rqLoadTypes() {
    try {
        const data = await cachedApi('api/requisition-types.php?action=list', 600000);
        rqTypes = data.types || [];
    } catch(e) {
        rqTypes = [{code:'lunch',name:'Lunch'},{code:'dinner',name:'Dinner'},{code:'breakfast',name:'Breakfast'}];
    }
}

// Get type name from code
function rqTypeName(code) {
    const t = rqTypes.find(t => t.code === code);
    return t ? t.name : code;
}

// ── Sessions ──
async function rqLoadSessions() {
    try {
        // First try listing existing
        let data = await api(`api/requisitions.php?action=list&date=${rqDate}&kitchen_id=${RQ_KITCHEN_ID}`);
        rqSessions = data.requisitions || [];

        // Auto-create if none exist for this date
        if (rqSessions.length === 0) {
            try {
                data = await api('api/requisitions.php?action=auto_create_for_date', {
                    method: 'POST',
                    body: JSON.stringify({
                        req_date: rqDate,
                        kitchen_id: RQ_KITCHEN_ID,
                        guest_count: rqGuestCount
                    })
                });
                rqSessions = data.requisitions || [];
            } catch (e) {
                showToast(e.message || 'Failed to create requisitions', 'error');
            }
        }

        rqRenderSessionTabs();

        if (rqSessions.length > 0) {
            // Load first session by default
            rqLoadSession(rqSessions[0].id);
        } else {
            rqActiveSession = null;
            document.getElementById('rqSessionCard').classList.add('hidden');
            document.getElementById('rqBottomBar').classList.add('hidden');
        }
    } catch (e) {
        showToast('Failed to load requisitions', 'error');
    }
}

function rqRenderSessionTabs() {
    const container = document.getElementById('rqSessionTabs');
    if (!rqSessions.length) {
        container.innerHTML = '<span class="text-[10px] text-gray-400 py-2">No types configured</span>';
        return;
    }

    let html = '';
    rqSessions.forEach(s => {
        const isActive = rqActiveSession && rqActiveSession.id === s.id;
        const typeName = rqTypeName(s.meals);
        const hasLines = parseInt(s.line_count) > 0;

        const statusColors = {
            draft: hasLines ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-gray-100 text-gray-700 border-gray-200',
            submitted: 'bg-blue-100 text-blue-700 border-blue-200',
            processing: 'bg-amber-100 text-amber-700 border-amber-200',
            fulfilled: 'bg-green-100 text-green-700 border-green-200',
            received: 'bg-green-100 text-green-700 border-green-200',
            closed: 'bg-gray-200 text-gray-500 border-gray-300'
        };
        const color = isActive ? 'bg-orange-500 text-white border-orange-500' : (statusColors[s.status] || 'bg-gray-100 text-gray-700 border-gray-200');

        html += `<button onclick="rqLoadSession(${s.id})" class="text-xs font-semibold px-3 py-1.5 rounded-full border ${color} whitespace-nowrap transition">
            ${typeName}
            ${s.status !== 'draft' ? `<span class="text-[9px] opacity-75 ml-0.5">&#10003;</span>` : ''}
        </button>`;
    });

    container.innerHTML = html;
}

async function rqLoadSession(sessionId) {
    try {
        const data = await api(`api/requisitions.php?action=get&id=${sessionId}`);
        rqActiveSession = data.requisition;
        const lines = data.lines || [];

        // Restore state
        rqGuestCount = rqActiveSession.guest_count || 20;
        document.getElementById('rqGuestCount').value = rqGuestCount;

        // Show type name
        const typeName = rqTypeName(rqActiveSession.meals);
        document.getElementById('rqTypeName').textContent = typeName;

        // Status pill
        const statusPill = document.getElementById('rqStatusPill');
        if (rqActiveSession.status === 'draft') {
            statusPill.innerHTML = '<span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium">Draft</span>';
        } else {
            const sc = {submitted:'bg-blue-100 text-blue-700',processing:'bg-amber-100 text-amber-700',fulfilled:'bg-green-100 text-green-700',received:'bg-green-100 text-green-700',closed:'bg-gray-200 text-gray-500'};
            statusPill.innerHTML = `<span class="text-[10px] ${sc[rqActiveSession.status] || ''} px-2 py-0.5 rounded-full font-medium capitalize">${rqActiveSession.status}</span>`;
        }

        // Reset dish state
        rqDishes = {};
        rqAggregatedItems = {};

        // Load saved dishes
        try {
            const dishData = await api(`api/requisitions.php?action=get_dishes&requisition_id=${sessionId}`);
            const savedDishes = dishData.dishes || [];

            for (const d of savedDishes) {
                try {
                    const ingData = await api(`api/requisitions.php?action=get_recipe_ingredients&recipe_id=${d.recipe_id}`);
                    rqDishes[d.recipe_id] = {
                        recipe_id: d.recipe_id,
                        recipe_name: d.recipe_name,
                        recipe_servings: d.recipe_servings || 4,
                        ingredients: ingData.ingredients || []
                    };
                } catch {
                    rqDishes[d.recipe_id] = {
                        recipe_id: d.recipe_id,
                        recipe_name: d.recipe_name,
                        recipe_servings: d.recipe_servings || 4,
                        ingredients: []
                    };
                }
            }

            rqRecalcAggregated();

            // Restore adjustments
            lines.forEach(l => {
                const agg = rqAggregatedItems[l.item_id];
                if (agg) {
                    const diff = parseFloat(l.required_kg) - agg.total_qty_raw;
                    if (Math.abs(diff) > 0.01) {
                        agg.adjustment = diff;
                        agg.total_qty = agg.total_qty_raw + diff;
                    }
                }
            });
        } catch {
            // No dishes saved yet
        }

        document.getElementById('rqSessionCard').classList.remove('hidden');
        rqRenderSessionTabs();
        rqRenderStatusBanner();
        rqRenderDishView();
        rqUpdateSummary();

        // Show/hide elements based on status
        const isDraft = rqActiveSession.status === 'draft';
        document.getElementById('rqBottomBar').classList.toggle('hidden', !isDraft);
        document.getElementById('rqDishSearchWrap').classList.toggle('hidden', !isDraft);

    } catch (e) {
        showToast('Failed to load requisition', 'error');
    }
}

// ── Guests ──
function rqSetGuests(val) {
    if (!rqActiveSession || rqActiveSession.status !== 'draft') return;
    rqGuestCount = Math.max(1, parseInt(val) || 1);
    document.getElementById('rqGuestCount').value = rqGuestCount;
    rqRecalcAggregated();
    rqRenderDishView();
    rqUpdateSummary();
}

// ═══════════════════════════════════════
//  DISH — Search, Add, Remove, Aggregate
// ═══════════════════════════════════════

async function rqSearchDishes() {
    const q = document.getElementById('rqDishSearch').value.trim();
    const container = document.getElementById('rqDishResults');
    if (q.length < 2) {
        container.classList.add('hidden');
        rqDishSearchResults = [];
        return;
    }

    try {
        const data = await api(`api/requisitions.php?action=search_recipes&q=${encodeURIComponent(q)}`);
        rqDishSearchResults = data.recipes || [];
        rqRenderDishResults();
    } catch (e) {
        container.innerHTML = '<p class="text-xs text-red-500 p-2">Search failed</p>';
        container.classList.remove('hidden');
    }
}

function rqRenderDishResults() {
    const container = document.getElementById('rqDishResults');
    if (!rqDishSearchResults.length) {
        container.innerHTML = '<p class="text-xs text-gray-400 bg-white rounded-xl border border-gray-200 p-3">No dishes found. Add recipes first in the Recipes page.</p>';
        container.classList.remove('hidden');
        return;
    }

    let html = '<div class="bg-white rounded-xl border border-gray-200 overflow-hidden max-h-64 overflow-y-auto">';
    rqDishSearchResults.forEach(r => {
        const alreadyAdded = rqDishes[r.id];
        html += `<button onclick="rqAddDish(${r.id})" class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-orange-50 transition text-left border-b border-gray-50 last:border-0 ${alreadyAdded ? 'opacity-50' : ''}" ${alreadyAdded ? 'disabled' : ''}>
            <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" x2="18" y1="17" y2="17"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">${r.name}</div>
                <div class="text-[10px] text-gray-400">${r.cuisine || ''} ${r.ingredient_count} ingredients &bull; serves ${r.servings}</div>
            </div>
            ${alreadyAdded ? '<span class="text-[10px] text-orange-500 font-semibold shrink-0">Added</span>' : '<span class="text-orange-500 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg></span>'}
        </button>`;
    });
    html += '</div>';
    container.innerHTML = html;
    container.classList.remove('hidden');
}

async function rqAddDish(recipeId) {
    if (rqDishes[recipeId]) return;

    try {
        const data = await api(`api/requisitions.php?action=get_recipe_ingredients&recipe_id=${recipeId}`);
        const recipe = data.recipe;
        const ingredients = data.ingredients || [];

        if (ingredients.length === 0) {
            showToast('This dish has no ingredients. Add ingredients in Recipes first.', 'warning');
            return;
        }

        rqDishes[recipeId] = {
            recipe_id: recipe.id,
            recipe_name: recipe.name,
            recipe_servings: parseInt(recipe.servings) || 4,
            ingredients: ingredients
        };

        showToast(`${recipe.name} added`, 'success');
        rqRecalcAggregated();
        rqRenderDishView();
        rqRenderDishResults();
        rqUpdateSummary();

        document.getElementById('rqDishSearch').value = '';
        document.getElementById('rqDishResults').classList.add('hidden');

    } catch (e) {
        showToast(e.message || 'Failed to load dish', 'error');
    }
}

function rqRemoveDish(recipeId) {
    const name = rqDishes[recipeId]?.recipe_name || 'Dish';
    delete rqDishes[recipeId];
    rqRecalcAggregated();
    rqRenderDishView();
    rqUpdateSummary();
    showToast(`${name} removed`, 'info');
}

function rqRecalcAggregated() {
    const newAgg = {};

    for (const [recipeId, dish] of Object.entries(rqDishes)) {
        const scaleFactor = rqGuestCount / (dish.recipe_servings || 4);

        dish.ingredients.forEach(ing => {
            const itemId = ing.item_id;
            const scaledQty = parseFloat(ing.qty) * scaleFactor;

            if (newAgg[itemId]) {
                newAgg[itemId].total_qty += scaledQty;
                newAgg[itemId].sources.push(dish.recipe_name);
            } else {
                const oldAdj = rqAggregatedItems[itemId]?.adjustment || 0;
                newAgg[itemId] = {
                    item_name: ing.item_name,
                    total_qty: scaledQty,
                    uom: ing.uom || 'kg',
                    stock_qty: parseFloat(ing.stock_qty) || 0,
                    order_mode: ing.order_mode || 'direct_kg',
                    category: ing.category || '',
                    adjustment: oldAdj,
                    sources: [dish.recipe_name]
                };
            }
        });
    }

    for (const itemId of Object.keys(newAgg)) {
        newAgg[itemId].total_qty_raw = newAgg[itemId].total_qty;
        newAgg[itemId].total_qty += newAgg[itemId].adjustment;
    }

    rqAggregatedItems = newAgg;
}

function rqRenderDishView() {
    const isDraft = rqActiveSession && rqActiveSession.status === 'draft';
    rqRenderSelectedDishes(isDraft);
    rqRenderAggregatedItemsList(isDraft);
}

function rqRenderSelectedDishes(isDraft) {
    const container = document.getElementById('rqSelectedDishes');
    const dishList = Object.values(rqDishes);

    if (dishList.length === 0) {
        if (isDraft) {
            container.innerHTML = `<div class="bg-white rounded-xl border border-dashed border-gray-300 p-6 text-center">
                <div class="text-2xl mb-2">&#127858;</div>
                <p class="text-sm text-gray-500 mb-1">No dishes selected yet</p>
                <p class="text-[10px] text-gray-400">Search and add dishes above to auto-fill ingredients</p>
            </div>`;
        } else {
            container.innerHTML = '<p class="text-xs text-gray-400 text-center py-3">No dishes were added</p>';
        }
        return;
    }

    let html = `<div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Selected Dishes (${dishList.length})</div>`;
    html += '<div class="space-y-2">';

    dishList.forEach(d => {
        const scaleFactor = (rqGuestCount / (d.recipe_servings || 4)).toFixed(1);
        html += `<div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" x2="18" y1="17" y2="17"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-800 truncate">${d.recipe_name}</div>
                        <div class="text-[10px] text-gray-400">${d.ingredients.length} ingredients &bull; serves ${d.recipe_servings} &bull; &times;${scaleFactor} scale</div>
                    </div>
                </div>
                ${isDraft ? `<button onclick="rqRemoveDish(${d.recipe_id})" class="text-gray-300 hover:text-red-500 transition p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                </button>` : ''}
            </div>
        </div>`;
    });

    html += '</div>';
    container.innerHTML = html;
}

function rqRenderAggregatedItemsList(isDraft) {
    const container = document.getElementById('rqAggregatedItems');
    const items = Object.entries(rqAggregatedItems);

    if (items.length === 0) {
        container.innerHTML = '';
        return;
    }

    const grouped = {};
    items.forEach(([itemId, agg]) => {
        const cat = agg.category || 'Other';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push({ itemId, ...agg });
    });

    let html = `<div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Aggregated Ingredients (${items.length})</div>`;

    for (const [cat, catItems] of Object.entries(grouped)) {
        html += `<div class="mb-2">
            <div class="text-[10px] font-semibold text-gray-400 uppercase px-1 mb-1">${cat}</div>`;

        catItems.forEach(agg => {
            const totalQty = Math.max(0, agg.total_qty);
            const requiredKg = Math.ceil(totalQty * 2) / 2;
            const orderQty = Math.max(0, Math.ceil((requiredKg - agg.stock_qty) * 2) / 2);

            let stockBadge = '';
            if (requiredKg > 0) {
                if (agg.stock_qty >= requiredKg) {
                    stockBadge = '<span class="text-[9px] bg-green-100 text-green-700 px-1 py-0.5 rounded">In Stock</span>';
                } else if (agg.stock_qty > 0) {
                    stockBadge = `<span class="text-[9px] bg-amber-100 text-amber-700 px-1 py-0.5 rounded">Partial ${agg.stock_qty}${agg.uom}</span>`;
                } else {
                    stockBadge = '<span class="text-[9px] bg-red-100 text-red-700 px-1 py-0.5 rounded">No Stock</span>';
                }
            }

            html += `<div class="bg-white border border-gray-100 rounded-lg px-3 py-2 mb-1">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium text-gray-800 truncate block">${agg.item_name}</span>
                        <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                            <span class="text-[9px] text-gray-400">From: ${agg.sources.join(', ')}</span>
                            ${stockBadge}
                        </div>
                    </div>
                    ${requiredKg > 0 && orderQty > 0 ? `<div class="text-right ml-2">
                        <div class="text-xs font-bold text-orange-600">${orderQty} ${agg.uom}</div>
                        <div class="text-[9px] text-gray-400">to order</div>
                    </div>` : (requiredKg > 0 ? `<div class="text-right ml-2">
                        <div class="text-xs font-bold text-green-600">0 ${agg.uom}</div>
                        <div class="text-[9px] text-gray-400">covered</div>
                    </div>` : '')}
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] text-gray-500">Need: ${requiredKg} ${agg.uom}</span>
                    ${isDraft ? `<div class="flex items-center gap-1">
                        <button onclick="rqAdjAggItem(${agg.itemId}, -0.5)" class="w-7 h-7 rounded-lg bg-gray-100 text-gray-500 font-bold flex items-center justify-center hover:bg-gray-200 text-xs">-</button>
                        <span class="text-xs font-semibold text-gray-700 w-12 text-center">${requiredKg}</span>
                        <button onclick="rqAdjAggItem(${agg.itemId}, 0.5)" class="w-7 h-7 rounded-lg bg-orange-100 text-orange-600 font-bold flex items-center justify-center hover:bg-orange-200 text-xs">+</button>
                    </div>` : ''}
                </div>
            </div>`;
        });

        html += '</div>';
    }

    container.innerHTML = html;
}

function rqAdjAggItem(itemId, delta) {
    if (!rqAggregatedItems[itemId]) return;
    rqAggregatedItems[itemId].adjustment = (rqAggregatedItems[itemId].adjustment || 0) + delta;
    rqAggregatedItems[itemId].total_qty = rqAggregatedItems[itemId].total_qty_raw + rqAggregatedItems[itemId].adjustment;
    rqRenderAggregatedItemsList(true);
    rqUpdateSummary();
}

// ═══════════════════════════════════════
//  Summary, Status, Save/Submit, Receipt
// ═══════════════════════════════════════

function rqUpdateSummary() {
    let totalItems = 0;
    let totalKg = 0;

    for (const [itemId, agg] of Object.entries(rqAggregatedItems)) {
        const totalQty = Math.max(0, agg.total_qty);
        const requiredKg = Math.ceil(totalQty * 2) / 2;
        if (requiredKg <= 0) continue;
        const orderQty = Math.max(0, Math.ceil((requiredKg - agg.stock_qty) * 2) / 2);
        if (orderQty > 0) {
            totalItems++;
            totalKg += orderQty;
        }
    }

    const dishCount = Object.keys(rqDishes).length;
    document.getElementById('rqSummaryLabel').innerHTML = `Dishes: <strong class="text-gray-800">${dishCount}</strong> &bull; Items: <strong class="text-gray-800">${totalItems}</strong>`;
    document.getElementById('rqSummaryKg').textContent = totalKg.toFixed(1) + ' kg';
}

function rqRenderStatusBanner() {
    const banner = document.getElementById('rqStatusBanner');
    if (!rqActiveSession || rqActiveSession.status === 'draft') {
        banner.classList.add('hidden');
        return;
    }

    const statusConfig = {
        submitted: { bg: 'bg-blue-50 border-blue-200', text: 'text-blue-700', label: 'Submitted — Waiting for store', icon: '&#9203;' },
        processing: { bg: 'bg-amber-50 border-amber-200', text: 'text-amber-700', label: 'Being Processed', icon: '&#9881;' },
        fulfilled: { bg: 'bg-green-50 border-green-200', text: 'text-green-700', label: 'Fulfilled — Ready to confirm receipt', icon: '&#9989;', action: true },
        received: { bg: 'bg-green-50 border-green-200', text: 'text-green-700', label: 'Received', icon: '&#10004;' },
        closed: { bg: 'bg-gray-50 border-gray-200', text: 'text-gray-500', label: 'Closed', icon: '&#128274;' }
    };

    const cfg = statusConfig[rqActiveSession.status];
    if (!cfg) { banner.classList.add('hidden'); return; }

    let html = `<div class="${cfg.bg} border rounded-xl px-4 py-3 mb-3">
        <div class="flex items-center gap-2">
            <span class="text-lg">${cfg.icon}</span>
            <span class="text-sm font-semibold ${cfg.text}">${cfg.label}</span>
        </div>`;

    if (cfg.action && rqActiveSession.status === 'fulfilled') {
        html += `<button onclick="rqShowReceiptSheet()" class="mt-2 w-full bg-green-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-green-600 transition">Confirm Receipt</button>`;
    }

    html += '</div>';
    banner.innerHTML = html;
    banner.classList.remove('hidden');
}

async function rqShowReceiptSheet() {
    if (!rqActiveSession) return;
    const data = await api(`api/requisitions.php?action=get&id=${rqActiveSession.id}`);
    const lines = data.lines || [];
    const typeName = rqTypeName(rqActiveSession.meals);

    let html = `<div class="p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Confirm Receipt — ${typeName}</h3>
        <div class="space-y-2 max-h-[55vh] overflow-y-auto">`;

    lines.forEach(l => {
        const fulfilledQty = parseFloat(l.fulfilled_qty) || 0;
        html += `<div class="bg-gray-50 rounded-lg px-3 py-2">
            <div class="text-sm font-medium text-gray-800">${l.item_name}</div>
            <div class="flex items-center justify-between mt-1">
                <span class="text-xs text-gray-500">Sent: ${fulfilledQty} ${l.uom}</span>
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-500">Got:</span>
                    <input type="number" value="${fulfilledQty}" min="0" step="0.5" data-line-id="${l.id}"
                        class="recv-qty w-16 text-center border border-gray-200 rounded py-1 text-sm font-semibold focus:outline-none focus:ring-1 focus:ring-green-300">
                    <span class="text-xs text-gray-400">${l.uom}</span>
                </div>
            </div>
        </div>`;
    });

    html += `</div>
        <button onclick="rqConfirmReceipt()" class="mt-3 w-full bg-green-500 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-600 transition">Confirm Receipt</button>
    </div>`;
    openSheet(html);
}

async function rqConfirmReceipt() {
    const inputs = document.querySelectorAll('.recv-qty');
    const lines = [];
    inputs.forEach(inp => {
        lines.push({ id: parseInt(inp.dataset.lineId), received_qty: parseFloat(inp.value) || 0 });
    });

    try {
        const data = await api('api/requisitions.php?action=confirm_receipt', {
            method: 'POST',
            body: JSON.stringify({ requisition_id: rqActiveSession.id, lines })
        });
        closeSheet();
        showToast(data.has_dispute ? 'Receipt confirmed with disputes' : 'Receipt confirmed', data.has_dispute ? 'warning' : 'success');
        voice.orderReceived(rqActiveSession.session_number);
        if (data.has_dispute) voice.say('Note: There are quantity disputes on this order.');
        rqLoadSession(rqActiveSession.id);
    } catch (e) {
        showToast(e.message || 'Failed to confirm', 'error');
    }
}

async function rqSaveAndSubmit() {
    if (!rqActiveSession) return;
    const btn = document.getElementById('rqSubmitBtn');
    setLoading(btn, true);

    try {
        const dishList = Object.values(rqDishes);
        if (dishList.length === 0) {
            showToast('Add at least one dish before submitting', 'warning');
            setLoading(btn, false);
            return;
        }

        const adjustments = {};
        for (const [itemId, agg] of Object.entries(rqAggregatedItems)) {
            if (agg.adjustment && Math.abs(agg.adjustment) > 0.01) {
                adjustments[itemId] = agg.adjustment;
            }
        }

        await api('api/requisitions.php?action=save_dish_lines', {
            method: 'POST',
            body: JSON.stringify({
                requisition_id: rqActiveSession.id,
                dishes: dishList.map(d => ({
                    recipe_id: d.recipe_id,
                    recipe_name: d.recipe_name,
                    recipe_servings: d.recipe_servings
                })),
                guest_count: rqGuestCount,
                adjustments: adjustments
            })
        });

        await api('api/requisitions.php?action=submit', {
            method: 'POST',
            body: JSON.stringify({ requisition_id: rqActiveSession.id })
        });

        const typeName = rqTypeName(rqActiveSession.meals);
        showToast(`${typeName} requisition submitted!`, 'success');
        voice.orderSubmitted(rqActiveSession.session_number, '<?= addslashes($kitchenName) ?>');
        rqLoadSessions();

    } catch (e) {
        showToast(e.message || 'Failed to submit', 'error');
        voice.error('Failed to submit order');
    } finally {
        setLoading(btn, false);
    }
}
</script>
