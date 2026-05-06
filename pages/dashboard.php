<?php
/**
 * Karibu Pantry Planner — Chef Dashboard
 * Today's Menu Planning + Quick Links
 */
$user = currentUser();
$kitchenName = $user['kitchen_name'] ?? 'No Kitchen';
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<!-- Greeting -->
<div class="mb-3">
    <h2 class="text-lg font-bold text-gray-800">Hello, <?= htmlspecialchars($user['name']) ?></h2>
    <p class="text-xs text-gray-500"><?= htmlspecialchars($kitchenName) ?></p>
</div>

<!-- Stats Cards -->
<div class="flex gap-3 overflow-x-auto pb-2 mb-3" id="dbStats">
    <div class="min-w-[120px] bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="dbStatSessions">&mdash;</div>
        <div class="text-[10px] opacity-80 font-medium">Active Requisitions</div>
    </div>
    <div class="min-w-[120px] bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="dbStatAwaiting">&mdash;</div>
        <div class="text-[10px] opacity-80 font-medium">Awaiting Supply</div>
    </div>
    <div class="min-w-[120px] bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="dbStatReceive">&mdash;</div>
        <div class="text-[10px] opacity-80 font-medium">Ready to Close</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!--  DATE SWITCHER                                  -->
<!-- ══════════════════════════════════════════════ -->
<div class="flex items-center justify-between bg-white rounded-xl border border-gray-200 px-3 py-2.5 mb-3">
    <button onclick="dbChangeDate(-1)" class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 active:bg-gray-300 flex items-center justify-center transition">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
    </button>
    <div class="text-center flex-1">
        <div class="text-sm font-bold text-gray-800" id="dbDateDisplay"></div>
        <button onclick="dbGoToday()" id="dbTodayBtn" class="text-[10px] text-orange-500 font-semibold hidden">Back to Today</button>
    </div>
    <button onclick="dbChangeDate(1)" class="w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 active:bg-gray-300 flex items-center justify-center transition">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
    </button>
</div>

<!-- ══════════════════════════════════════════════ -->
<!--  MEAL TABS (horizontal)                         -->
<!-- ══════════════════════════════════════════════ -->
<div id="dbMealTabs" class="flex gap-1.5 overflow-x-auto pb-2 mb-3 -mx-1 px-1 scroll-touch"></div>

<!-- Active Meal Content -->
<div id="dbMealContent" class="mb-3">
    <div class="text-center py-8 text-xs text-gray-400">Loading menu...</div>
</div>

<!-- Quick Links -->
<div class="mb-3">
    <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Quick Links</div>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <a href="app.php?page=requisition" class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-medium text-gray-700 hover:border-orange-200 hover:bg-orange-50 transition whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
            Requisitions
        </a>
        <a href="app.php?page=day-close" class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-medium text-gray-700 hover:border-blue-200 hover:bg-blue-50 transition whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            Day Close
        </a>
        <a href="app.php?page=reports" class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-medium text-gray-700 hover:border-purple-200 hover:bg-purple-50 transition whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
            Reports
        </a>
        <a href="app.php?page=store-history" class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-medium text-gray-700 hover:border-green-200 hover:bg-green-50 transition whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
            Order History
        </a>
    </div>
</div>

<!-- Dish Portions Modal -->
<div id="dbPortionsModal" class="hidden fixed inset-0 z-[200] bg-black/50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
        <input type="hidden" id="dbPmMealCode">
        <input type="hidden" id="dbPmRecipeId">
        <h3 class="text-lg font-bold text-gray-900 mb-0.5" id="dbPmDishName">&mdash;</h3>
        <p class="text-xs text-gray-400 mb-5" id="dbPmStd">&mdash;</p>
        <label class="text-xs text-gray-500 font-medium mb-3 block">How many portions?</label>
        <div class="flex items-center justify-center gap-3 mb-5">
            <button onclick="dbPmStep(-5)" class="stepper-btn bg-gray-100 text-gray-600 text-base">-5</button>
            <button onclick="dbPmStep(-1)" class="stepper-btn bg-red-100 text-red-600 text-xl">&minus;</button>
            <input type="number" id="dbPmInput" min="1" class="w-24 text-center text-3xl font-bold border-2 border-gray-200 rounded-xl py-3 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-300">
            <button onclick="dbPmStep(1)" class="stepper-btn bg-green-100 text-green-600 text-xl">+</button>
            <button onclick="dbPmStep(5)" class="stepper-btn bg-gray-100 text-gray-600 text-base">+5</button>
        </div>
        <div class="flex gap-3">
            <button onclick="dbPmClose()" class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-semibold text-sm">Cancel</button>
            <button onclick="dbPmSave()" class="flex-1 py-3 rounded-xl bg-orange-600 text-white font-semibold text-sm">Save</button>
        </div>
    </div>
</div>

<!-- Add Dish Modal -->
<div id="dbAddDishModal" class="hidden fixed inset-0 z-[200] bg-black/50 flex items-start justify-center pt-[15vh] p-4 animate-fade-in">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-5 max-h-[70vh] flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-gray-900">Add Dish</h3>
            <button onclick="dbCloseAddDish()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <input type="hidden" id="dbAddDishMealCode">
        <div class="relative mb-3">
            <input type="text" id="dbDishSearch" placeholder="Search dishes by name..." oninput="dbSearchDishesDebounced()"
                class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
            <svg class="absolute left-3 top-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <div id="dbDishResults" class="flex-1 overflow-y-auto"></div>
        <p id="dbDishResultsHint" class="text-[10px] text-gray-400 text-center mt-2">Type at least 2 characters to search</p>
    </div>
</div>

<script>
// ══════════════════════════════════════════════
//  Dashboard State
// ══════════════════════════════════════════════
const DB_KITCHEN_ID = <?= (int)$kitchenId ?>;

let dbDate = todayStr();
let dbGuestCount = 20;
let dbGuestCounts = {};    // mealCode -> per-meal guest count
let dbTypes = [];
let dbSessions = [];
let dbSessionMap = {};     // mealCode -> session object
let dbMealDishes = {};     // mealCode -> { recipeId: dishObj }
let dbMealAgg = {};        // mealCode -> { itemId: aggObj }
let dbSetMenuLoaded = {};  // mealCode -> true
let dbActiveMeal = null;   // currently visible meal tab
let dbSettings = { default_guest_count: 20, rounding_mode: 'half', min_order_qty: 0.5 };
let dbDishSearchResults = [];
let dbAddDishTargetMeal = null;

// Rounding helper
function dbRound(qty) {
    if (dbSettings.rounding_mode === 'none') return qty;
    if (dbSettings.rounding_mode === 'whole') return Math.ceil(qty);
    return Math.ceil(qty * 2) / 2;
}

const dbSearchDishesDebounced = debounce(() => dbSearchDishes(), 350);

// ══════════════════════════════════════════════
//  Init
// ══════════════════════════════════════════════
document.getElementById('dbDateDisplay').textContent = formatDate(dbDate);
dbLoadStats();
dbInit();


// ── Date Switcher ──
function dbChangeDate(days) {
    dbDate = changeDate(dbDate, days);
    document.getElementById('dbDateDisplay').textContent = formatDate(dbDate);
    document.getElementById('dbTodayBtn').classList.toggle('hidden', dbDate === todayStr());
    // Reset state
    dbSessionMap = {};
    dbMealDishes = {};
    dbMealAgg = {};
    dbSetMenuLoaded = {};
    dbActiveMeal = null;
    dbInit();
}

function dbGoToday() {
    dbDate = todayStr();
    document.getElementById('dbDateDisplay').textContent = formatDate(dbDate);
    document.getElementById('dbTodayBtn').classList.add('hidden');
    dbSessionMap = {};
    dbMealDishes = {};
    dbMealAgg = {};
    dbSetMenuLoaded = {};
    dbActiveMeal = null;
    dbInit();
}

async function dbLoadStats() {
    try {
        const data = await api(`api/requisitions.php?action=dashboard_stats&kitchen_id=${DB_KITCHEN_ID}`);
        const s = data.stats || {};
        document.getElementById('dbStatSessions').textContent = s.active_sessions || 0;
        document.getElementById('dbStatAwaiting').textContent = s.awaiting_supply || 0;
        document.getElementById('dbStatReceive').textContent = (s.ready_close || 0);
    } catch(e) {}
}

async function dbInit() {
    try {
        const initData = await api('api/requisitions.php?action=page_init', {
            method: 'POST',
            body: JSON.stringify({
                req_date: dbDate,
                kitchen_id: DB_KITCHEN_ID,
                guest_count: dbGuestCount
            })
        });

        if (initData.settings) {
            dbSettings = initData.settings;
            dbGuestCount = dbSettings.default_guest_count || 20;
        }

        dbTypes = initData.types || [];
        dbSessions = initData.requisitions || [];
        dbSessionMap = {};
        dbSessions.forEach(s => {
            if (!dbSessionMap[s.meals] || s.status === 'draft') {
                dbSessionMap[s.meals] = s;
            }
        });

        // Init per-meal guest counts from sessions
        for (const mc of Object.keys(dbSessionMap)) {
            if (!dbGuestCounts[mc]) {
                dbGuestCounts[mc] = parseInt(dbSessionMap[mc].guest_count) || dbGuestCount;
            }
        }

        await dbLoadAllSetMenus();

        // Set active meal if not set
        const mealCodes = dbTypes.map(t => t.code).filter(c => dbSessionMap[c]);
        if (!dbActiveMeal || !mealCodes.includes(dbActiveMeal)) {
            dbActiveMeal = mealCodes[0] || null;
        }

        dbRenderMealTabs();
        dbRenderActiveMeal();

    } catch(e) {
        console.warn('Dashboard init failed:', e);
        document.getElementById('dbMealContent').innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load menu</p>';
    }
}

// ══════════════════════════════════════════════
//  Set Menu Loading
// ══════════════════════════════════════════════
async function dbLoadAllSetMenus() {
    const dateObj = new Date(dbDate + 'T00:00:00');
    let dayOfWeek = dateObj.getDay();
    dayOfWeek = dayOfWeek === 0 ? 7 : dayOfWeek;
    const mealCodes = Object.keys(dbSessionMap);
    await Promise.allSettled(mealCodes.map(code => dbLoadSetMenuForMeal(code, dayOfWeek)));
}

async function dbLoadSetMenuForMeal(mealCode, dayOfWeek) {
    if (dbSetMenuLoaded[mealCode]) return;
    const session = dbSessionMap[mealCode];
    if (!session || session.status !== 'draft') return;
    if (!dbMealDishes[mealCode]) dbMealDishes[mealCode] = {};

    try {
        const batchData = await api(`api/requisitions.php?action=get_dishes_with_ingredients&requisition_id=${session.id}`).catch(() => ({ dishes: [], ingredients_by_recipe: {} }));
        const savedDishes = batchData.dishes || [];
        const savedIngredients = batchData.ingredients_by_recipe || {};

        for (const d of savedDishes) {
            dbMealDishes[mealCode][d.recipe_id] = {
                recipe_id: d.recipe_id, recipe_name: d.recipe_name,
                recipe_servings: d.recipe_servings || 4,
                dish_portions: parseInt(d.guest_count) || dbGuestCounts[mealCode] || dbGuestCount,
                ingredients: savedIngredients[d.recipe_id] || []
            };
        }

        if (savedDishes.length === 0) {
            const data = await api(`api/set-menus.php?action=get_day_with_ingredients&day=${dayOfWeek}&type=${encodeURIComponent(mealCode)}`);
            for (const md of (data.dishes || [])) {
                if (dbMealDishes[mealCode][md.recipe_id]) continue;
                const ings = (data.ingredients_by_recipe || {})[md.recipe_id] || [];
                if (ings.length === 0) continue;
                dbMealDishes[mealCode][md.recipe_id] = {
                    recipe_id: md.recipe_id, recipe_name: md.recipe_name,
                    recipe_servings: parseInt(md.recipe_servings) || 4,
                    dish_portions: dbGuestCounts[mealCode] || dbGuestCount,
                    ingredients: ings
                };
            }
        }

        dbSetMenuLoaded[mealCode] = true;
        dbRecalcMealAgg(mealCode);
    } catch(e) {
        if (e && e.status !== 404) console.warn('Set menu load error for', mealCode, e);
        dbSetMenuLoaded[mealCode] = true;
    }
}

// ══════════════════════════════════════════════
//  Per-Meal Guest Count
// ══════════════════════════════════════════════
function dbSetMealGuests(mealCode, val) {
    const count = Math.max(1, parseInt(val) || 1);
    dbGuestCounts[mealCode] = count;
    const dishes = dbMealDishes[mealCode] || {};
    for (const recipeId of Object.keys(dishes)) {
        dishes[recipeId].dish_portions = count;
    }
    dbRecalcMealAgg(mealCode);
    dbRenderActiveMeal();
}

function dbStepMealGuests(mealCode, delta) {
    const current = dbGuestCounts[mealCode] || dbGuestCount;
    dbSetMealGuests(mealCode, current + delta);
}

// ══════════════════════════════════════════════
//  Aggregation per meal type
// ══════════════════════════════════════════════
function dbRecalcMealAgg(mealCode) {
    const dishes = dbMealDishes[mealCode] || {};
    const newAgg = {};
    for (const [recipeId, dish] of Object.entries(dishes)) {
        const portions = dish.dish_portions || dbGuestCounts[mealCode] || dbGuestCount;
        const scaleFactor = portions / (dish.recipe_servings || 4);
        (dish.ingredients || []).forEach(ing => {
            const itemId = ing.item_id;
            const scaledQty = parseFloat(ing.qty) * scaleFactor;
            if (newAgg[itemId]) {
                newAgg[itemId].total_qty += scaledQty;
                newAgg[itemId].sources.push(dish.recipe_name);
            } else {
                newAgg[itemId] = {
                    item_name: ing.item_name, total_qty: scaledQty,
                    uom: ing.uom || 'kg', stock_qty: parseFloat(ing.stock_qty) || 0,
                    category: ing.category || '', sources: [dish.recipe_name]
                };
            }
        });
    }
    dbMealAgg[mealCode] = newAgg;
}

// ══════════════════════════════════════════════
//  Render Meal Tabs (horizontal)
// ══════════════════════════════════════════════
function dbRenderMealTabs() {
    const container = document.getElementById('dbMealTabs');
    const mealCodes = dbTypes.map(t => t.code).filter(c => dbSessionMap[c]);

    container.innerHTML = mealCodes.map(mc => {
        const isActive = mc === dbActiveMeal;
        const typeName = dbTypeName(mc);
        const session = dbSessionMap[mc];
        const dishCount = Object.keys(dbMealDishes[mc] || {}).length;
        const isLocked = session && session.status !== 'draft';

        return `<button onclick="dbSelectMeal('${mc}')"
            class="px-4 py-2.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5
            ${isActive ? 'bg-orange-500 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}">
            ${typeName}
            ${dishCount > 0 ? `<span class="text-[10px] ${isActive ? 'bg-white/30' : 'bg-gray-200'} px-1.5 py-0.5 rounded-full">${dishCount}</span>` : ''}
            ${isLocked ? '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>' : ''}
        </button>`;
    }).join('');
}

function dbSelectMeal(mealCode) {
    dbActiveMeal = mealCode;
    dbRenderMealTabs();
    dbRenderActiveMeal();
}

// ══════════════════════════════════════════════
//  Render Active Meal Content
// ══════════════════════════════════════════════
function dbRenderActiveMeal() {
    const container = document.getElementById('dbMealContent');
    if (!dbActiveMeal) {
        container.innerHTML = '<div class="text-center py-8 text-xs text-gray-400">No meal types configured</div>';
        return;
    }

    const mealCode = dbActiveMeal;
    const session = dbSessionMap[mealCode];
    const typeName = dbTypeName(mealCode);
    const dishes = dbMealDishes[mealCode] || {};
    const dishList = Object.values(dishes);
    const agg = dbMealAgg[mealCode] || {};
    const aggItems = Object.values(agg);
    const isDraft = session && session.status === 'draft';
    const isLocked = session && session.status !== 'draft';
    const gc = dbGuestCounts[mealCode] || dbGuestCount;

    let totalItems = 0, totalKg = 0;
    for (const a of aggItems) {
        const rKg = dbRound(Math.max(0, a.total_qty));
        if (rKg > 0) { totalItems++; totalKg += rKg; }
    }

    const accents = {
        'breakfast': { bg: 'bg-amber-50', border: 'border-amber-200', icon: '&#9749;' },
        'lunch':     { bg: 'bg-orange-50', border: 'border-orange-200', icon: '&#127869;' },
        'dinner':    { bg: 'bg-indigo-50', border: 'border-indigo-200', icon: '&#127769;' },
    };
    const accent = accents[mealCode] || { bg: 'bg-gray-50', border: 'border-gray-200', icon: '&#127860;' };

    const statusColors = {
        submitted: 'bg-blue-100 text-blue-700',
        processing: 'bg-amber-100 text-amber-700',
        fulfilled: 'bg-green-100 text-green-700',
        received: 'bg-green-100 text-green-700',
        closed: 'bg-gray-200 text-gray-500'
    };

    let html = `<div class="bg-white rounded-xl border ${isLocked ? accent.border : 'border-gray-200'} overflow-hidden">`;

    // Card Header with status
    html += `<div class="${accent.bg} px-3 py-2.5 flex items-center justify-between border-b ${accent.border}">
        <div class="flex items-center gap-2">
            <span class="text-lg">${accent.icon}</span>
            <span class="text-sm font-bold text-gray-800">${escHtml(typeName)}</span>
        </div>
        <div class="flex items-center gap-2">`;
    if (isLocked) {
        const sc = statusColors[session.status] || '';
        html += `<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${sc} capitalize">${session.status}</span>`;
    }
    html += `</div></div>`;

    // Per-meal Guest Count (only for drafts)
    if (isDraft) {
        html += `<div class="px-3 py-2.5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Guest Count</div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="dbStepMealGuests('${mealCode}', -5)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-500 font-bold flex items-center justify-center text-sm active:bg-gray-200">-5</button>
                <input type="number" value="${gc}" min="1" onchange="dbSetMealGuests('${mealCode}', this.value)"
                    class="w-16 text-center text-lg font-bold text-gray-800 border border-gray-200 rounded-lg py-1 focus:outline-none focus:ring-2 focus:ring-orange-200">
                <button onclick="dbStepMealGuests('${mealCode}', 5)" class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 font-bold flex items-center justify-center text-sm active:bg-orange-200">+5</button>
            </div>
        </div>`;
    }

    // Dish List
    html += '<div class="px-3 py-2">';
    if (dishList.length === 0) {
        html += `<div class="text-center py-4"><p class="text-[11px] text-gray-400">No dishes yet</p></div>`;
    } else {
        html += '<div class="space-y-1.5">';
        dishList.forEach(d => {
            const portions = d.dish_portions || gc;
            html += `<div class="flex items-center justify-between py-1.5 ${isDraft ? 'cursor-pointer active:bg-orange-50 rounded-lg px-1 -mx-1' : ''}" ${isDraft ? `onclick="dbShowPortionsModal('${escHtml(mealCode)}', ${d.recipe_id})"` : ''}>
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" x2="18" y1="17" y2="17"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-medium text-gray-800 truncate">${escHtml(d.recipe_name)}</div>
                        <div class="text-[9px] text-gray-400">${portions} pax</div>
                        ${d.ingredients ? `<div class="text-[9px] text-gray-400 mt-0.5 leading-relaxed">${d.ingredients.map(i => i.item_name + ' ' + (Math.round(i.qty * (portions / (d.recipe_servings || 4)) * 10) / 10) + i.uom).join(', ')}</div>` : ''}
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    ${isDraft ? `<span class="text-xs font-bold text-orange-600">${portions}</span>` : `<span class="text-[10px] text-gray-500">${portions} pax</span>`}
                    ${isDraft ? `<button onclick="event.stopPropagation();dbRemoveDish('${escHtml(mealCode)}', ${d.recipe_id})" class="text-gray-300 hover:text-red-500 transition p-1 compact-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>` : ''}
                </div>
            </div>`;
        });
        html += '</div>';
    }

    // Add Dish button (drafts only)
    if (isDraft) {
        html += `<button onclick="dbOpenAddDish('${escHtml(mealCode)}')" class="w-full mt-2 border border-dashed border-orange-200 rounded-lg px-3 py-2 text-xs font-semibold text-orange-500 flex items-center justify-center gap-1.5 hover:bg-orange-50 active:bg-orange-100 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
            Add Dish
        </button>`;
    }

    html += '</div>';

    // Summary Footer
    if (dishList.length > 0) {
        html += `<div class="px-3 py-2 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <span class="text-[10px] text-gray-500">${dishList.length} dish${dishList.length !== 1 ? 'es' : ''} &bull; ${totalItems} items &bull; ${totalKg.toFixed(1)} kg</span>
            ${isLocked ? `<a href="app.php?page=orders" class="text-[10px] text-orange-500 font-semibold">View Order</a>` : ''}
        </div>`;
    }

    // Per-meal Lock Button (drafts with dishes only)
    if (isDraft && dishList.length > 0) {
        html += `<div class="px-3 py-3 border-t border-gray-100">
            <button onclick="dbLockMeal('${mealCode}')" id="dbLockBtn_${mealCode}"
                class="w-full bg-orange-500 text-white py-3 rounded-xl text-sm font-bold hover:bg-orange-600 active:bg-orange-700 transition flex items-center justify-center gap-2 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Lock ${escHtml(typeName)} &amp; Generate Order
            </button>
        </div>`;
    }

    html += '</div>';
    container.innerHTML = html;
}

function dbTypeName(code) {
    const t = dbTypes.find(t => t.code === code);
    return t ? t.name : code;
}

// ══════════════════════════════════════════════
//  Swipe Support
// ══════════════════════════════════════════════
(function() {
    let startX = 0, startY = 0;
    const el = document.getElementById('dbMealContent');
    el.addEventListener('touchstart', e => {
        startX = e.changedTouches[0].screenX;
        startY = e.changedTouches[0].screenY;
    }, { passive: true });
    el.addEventListener('touchend', e => {
        const dx = startX - e.changedTouches[0].screenX;
        const dy = Math.abs(startY - e.changedTouches[0].screenY);
        if (Math.abs(dx) < 50 || dy > Math.abs(dx)) return;
        const mealCodes = dbTypes.map(t => t.code).filter(c => dbSessionMap[c]);
        const idx = mealCodes.indexOf(dbActiveMeal);
        if (dx > 0 && idx < mealCodes.length - 1) dbSelectMeal(mealCodes[idx + 1]);
        else if (dx < 0 && idx > 0) dbSelectMeal(mealCodes[idx - 1]);
    }, { passive: true });
})();

// ══════════════════════════════════════════════
//  Dish Portions Modal
// ══════════════════════════════════════════════
function dbShowPortionsModal(mealCode, recipeId) {
    const dishes = dbMealDishes[mealCode] || {};
    const dish = dishes[recipeId];
    if (!dish) return;
    const session = dbSessionMap[mealCode];
    if (!session || session.status !== 'draft') return;
    document.getElementById('dbPmMealCode').value = mealCode;
    document.getElementById('dbPmRecipeId').value = recipeId;
    document.getElementById('dbPmDishName').textContent = dish.recipe_name;
    document.getElementById('dbPmStd').textContent = `Standard recipe serves ${dish.recipe_servings}`;
    document.getElementById('dbPmInput').value = dish.dish_portions || dbGuestCounts[mealCode] || dbGuestCount;
    document.getElementById('dbPortionsModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('dbPmInput').select(), 100);
}

function dbPmStep(dir) {
    const inp = document.getElementById('dbPmInput');
    inp.value = Math.max(1, (parseInt(inp.value) || 0) + dir);
}

function dbPmSave() {
    const mealCode = document.getElementById('dbPmMealCode').value;
    const recipeId = parseInt(document.getElementById('dbPmRecipeId').value);
    const val = Math.max(1, parseInt(document.getElementById('dbPmInput').value) || 1);
    const dishes = dbMealDishes[mealCode] || {};
    if (dishes[recipeId]) {
        dishes[recipeId].dish_portions = val;
        dbRecalcMealAgg(mealCode);
        dbRenderActiveMeal();
    }
    dbPmClose();
}

function dbPmClose() {
    document.getElementById('dbPortionsModal').classList.add('hidden');
}

// ══════════════════════════════════════════════
//  Add Dish Modal
// ══════════════════════════════════════════════
async function dbOpenAddDish(mealCode) {
    dbAddDishTargetMeal = mealCode;
    document.getElementById('dbAddDishMealCode').value = mealCode;
    document.getElementById('dbAddDishModal').classList.remove('hidden');
    document.getElementById('dbDishSearch').value = '';
    document.getElementById('dbDishResultsHint').classList.add('hidden');
    setTimeout(() => document.getElementById('dbDishSearch').focus(), 100);
    try {
        const data = await api('api/requisitions.php?action=search_recipes&q=');
        dbDishSearchResults = data.recipes || [];
        dbRenderDishResults();
    } catch {
        document.getElementById('dbDishResults').innerHTML = '';
        document.getElementById('dbDishResultsHint').classList.remove('hidden');
    }
}

function dbCloseAddDish() {
    document.getElementById('dbAddDishModal').classList.add('hidden');
    document.getElementById('dbDishSearch').value = '';
    document.getElementById('dbDishResults').innerHTML = '';
    dbAddDishTargetMeal = null;
}

async function dbSearchDishes() {
    const q = document.getElementById('dbDishSearch').value.trim();
    if (q.length > 0 && q.length < 2) return;
    try {
        document.getElementById('dbDishResultsHint').classList.add('hidden');
        const data = await api(`api/requisitions.php?action=search_recipes&q=${encodeURIComponent(q)}`);
        dbDishSearchResults = data.recipes || [];
        dbRenderDishResults();
    } catch(e) {
        document.getElementById('dbDishResults').innerHTML = '<p class="text-xs text-red-500 p-2">Search failed</p>';
    }
}

function dbRenderDishResults() {
    const container = document.getElementById('dbDishResults');
    const mealCode = dbAddDishTargetMeal;
    const dishes = mealCode ? (dbMealDishes[mealCode] || {}) : {};
    if (!dbDishSearchResults.length) {
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No dishes found</p>';
        return;
    }
    let html = '';
    dbDishSearchResults.forEach(r => {
        const alreadyAdded = !!dishes[r.id];
        html += `<button onclick="dbAddDish(${r.id})" class="w-full flex items-center gap-3 px-3 py-3 hover:bg-orange-50 active:bg-orange-100 transition text-left border-b border-gray-100 last:border-0 ${alreadyAdded ? 'opacity-50' : ''}" ${alreadyAdded ? 'disabled' : ''}>
            <div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" x2="18" y1="17" y2="17"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">${escHtml(r.name)}</div>
                <div class="text-[10px] text-gray-400">${escHtml(r.cuisine || '')} ${r.ingredient_count} items &bull; serves ${r.servings}</div>
            </div>
            ${alreadyAdded ? '<span class="text-[10px] text-green-600 font-semibold bg-green-50 px-2 py-1 rounded-lg shrink-0">Added</span>' : '<span class="text-orange-500 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg></span>'}
        </button>`;
    });
    container.innerHTML = html;
}

async function dbAddDish(recipeId) {
    const mealCode = dbAddDishTargetMeal;
    if (!mealCode || !dbMealDishes[mealCode]) dbMealDishes[mealCode] = {};
    if (dbMealDishes[mealCode][recipeId]) return;
    try {
        const data = await api(`api/requisitions.php?action=get_recipe_ingredients&recipe_id=${recipeId}`);
        const recipe = data.recipe;
        const ingredients = data.ingredients || [];
        if (ingredients.length === 0) {
            showToast('This dish has no ingredients. Add ingredients in Recipes first.', 'warning');
            return;
        }
        dbMealDishes[mealCode][recipeId] = {
            recipe_id: recipe.id, recipe_name: recipe.name,
            recipe_servings: parseInt(recipe.servings) || 4,
            dish_portions: dbGuestCounts[mealCode] || dbGuestCount,
            ingredients: ingredients
        };
        showToast(`${recipe.name} added to ${dbTypeName(mealCode)}`, 'success');
        dbRecalcMealAgg(mealCode);
        dbRenderMealTabs();
        dbRenderActiveMeal();
        dbRenderDishResults();
    } catch(e) {
        showToast(e.message || 'Failed to load dish', 'error');
    }
}

function dbRemoveDish(mealCode, recipeId) {
    const dishes = dbMealDishes[mealCode];
    if (!dishes || !dishes[recipeId]) return;
    const name = dishes[recipeId].recipe_name;
    delete dishes[recipeId];
    dbRecalcMealAgg(mealCode);
    dbRenderMealTabs();
    dbRenderActiveMeal();
    showToast(`${name} removed`, 'info');
}

// ══════════════════════════════════════════════
//  Per-Meal Lock
// ══════════════════════════════════════════════
async function dbLockMeal(mealCode) {
    const session = dbSessionMap[mealCode];
    if (!session || session.status !== 'draft') return;

    const dishes = Object.values(dbMealDishes[mealCode] || {});
    if (dishes.length === 0) { showToast('No dishes to submit', 'warning'); return; }

    const typeName = dbTypeName(mealCode);
    const confirmed = await customConfirm(
        `Lock ${typeName}`,
        `Submit ${typeName} (${dishes.length} dish${dishes.length > 1 ? 'es' : ''}) to generate the order?`,
        'Lock & Submit', 'Cancel'
    );
    if (!confirmed) return;

    const btn = document.getElementById('dbLockBtn_' + mealCode);
    if (btn) setLoading(btn, true);

    try {
        await api('api/requisitions.php?action=lock_menu', {
            method: 'POST',
            body: JSON.stringify({
                requisition_id: session.id,
                dishes: dishes.map(d => ({
                    recipe_id: d.recipe_id, recipe_name: d.recipe_name,
                    recipe_servings: d.recipe_servings,
                    dish_portions: d.dish_portions || dbGuestCounts[mealCode] || dbGuestCount
                })),
                guest_count: dbGuestCounts[mealCode] || dbGuestCount,
                adjustments: {}
            })
        });
        showToast(`${typeName} submitted!`, 'success');
        dbSetMenuLoaded[mealCode] = false;
        await dbInit();
        dbLoadStats();
    } catch(e) {
        showToast(e.message || 'Failed to submit', 'error');
    } finally {
        if (btn) setLoading(btn, false);
    }
}
</script>
