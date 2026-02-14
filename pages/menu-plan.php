<!-- Menu Plan Page — Chef daily meal planning with per-dish portions & real-time grocery calc -->
<div id="menuPlanApp">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-600"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
                Menu Plan
            </h1>
            <p class="text-xs text-gray-500 mt-0.5" id="subtitle">Today's fixed menu</p>
        </div>
    </div>

    <!-- Date Picker -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-100 px-4 py-3 mb-3">
        <button onclick="navDate(-1)" class="p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="text-center">
            <p class="text-sm font-semibold text-gray-900" id="dateDisplay"></p>
            <span id="todayBadge" class="text-[10px] text-green-600 font-medium hidden">Today</span>
            <button id="goTodayBtn" onclick="goToday()" class="text-[10px] text-orange-600 font-medium hidden">Go to Today</button>
        </div>
        <button onclick="navDate(1)" class="p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>

    <!-- Meal Toggle -->
    <div class="flex gap-2 mb-3">
        <button onclick="setMeal('lunch')" id="mealLunch" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all">Lunch</button>
        <button onclick="setMeal('dinner')" id="mealDinner" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all">Dinner</button>
    </div>

    <!-- Error -->
    <div id="errorBox" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-3">
        <p class="text-sm text-red-700" id="errorText"></p>
    </div>

    <!-- Loading -->
    <div id="loadingState" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-orange-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading menu plan...</p>
    </div>

    <!-- PAX Setup (when no plan exists yet but fixed menu available) -->
    <div id="paxSetup" class="hidden">
        <div class="bg-white rounded-xl border border-orange-200 p-5 mb-3 text-center">
            <p class="text-sm font-semibold text-gray-800 mb-1">Fixed menu available</p>
            <p class="text-xs text-gray-500 mb-4" id="fixedMenuCount">0 dishes for today</p>
            <label class="text-xs text-gray-500 mb-2 block">How many guests today?</label>
            <div class="flex items-center justify-center gap-2 mb-4">
                <button onclick="adjSetupPax(-5)" class="w-10 h-10 rounded-lg bg-gray-100 text-gray-700 font-bold text-lg compact-btn">-</button>
                <input type="number" id="setupPaxInput" value="20" min="1" class="w-20 h-10 text-center text-lg font-bold border border-gray-200 rounded-lg compact-btn">
                <button onclick="adjSetupPax(5)" class="w-10 h-10 rounded-lg bg-gray-100 text-gray-700 font-bold text-lg compact-btn">+</button>
            </div>
            <button onclick="createFromFixed()" id="createFixedBtn" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-xl text-sm font-semibold transition">
                Load Fixed Menu
            </button>
        </div>
        <!-- Preview of fixed menu -->
        <div id="fixedPreview" class="space-y-1.5"></div>
    </div>

    <!-- No Fixed Menu State -->
    <div id="noMenuState" class="hidden bg-white rounded-xl border border-gray-100 p-6 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-300 mx-auto mb-2"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
        <p class="text-sm text-gray-500 mb-1">No fixed menu set for this day</p>
        <p class="text-xs text-gray-400 mb-3">Ask admin to configure the weekly rotation</p>
    </div>

    <!-- Plan Status Bar -->
    <div id="planBar" class="hidden bg-white rounded-xl border border-gray-100 px-4 py-2.5 mb-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span id="planStatusBadge" class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"></span>
                <span id="planInfo" class="text-[10px] text-gray-400"></span>
            </div>
            <div id="planActions"></div>
        </div>
    </div>

    <!-- Dishes List -->
    <div id="dishList" class="space-y-2 hidden"></div>

    <!-- Grocery Summary (shows total ingredients needed across all dishes) -->
    <div id="grocerySummary" class="hidden mt-3">
        <div class="bg-orange-50 border border-orange-200 rounded-xl overflow-hidden">
            <button onclick="toggleGrocerySummary()" class="w-full flex items-center justify-between px-4 py-2.5">
                <span class="text-xs font-semibold text-orange-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline -mt-0.5 mr-1"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
                    Total Groceries Needed
                </span>
                <svg id="groceryChevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-600 transition-transform"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div id="groceryList" class="hidden border-t border-orange-200 px-4 py-2 max-h-64 overflow-y-auto"></div>
        </div>
    </div>

    <!-- Add Dish Button (only when plan exists and is draft) -->
    <div id="addDishArea" class="hidden mt-3">
        <button onclick="showAddDish()" id="addDishBtn"
            class="w-full flex items-center justify-center gap-2 py-3.5 border-2 border-dashed border-gray-300 rounded-xl text-gray-500 hover:border-orange-400 hover:text-orange-600 hover:bg-orange-50/50 transition font-semibold text-sm active:bg-orange-50">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Add Dish
        </button>
    </div>
</div>

<script>
let currentDate = todayStr();
let currentMeal = 'lunch';
let planData = null;
let dishesData = [];
let fixedMenuData = [];
let recipesData = [];
let grocerySummaryOpen = false;
// Track local portion overrides before saving
let localPortions = {}; // dishId → portions

loadPlan();

function navDate(days) { currentDate = changeDate(currentDate, days); loadPlan(); }
function goToday() { currentDate = todayStr(); loadPlan(); }
function setMeal(meal) { currentMeal = meal; loadPlan(); }

function renderDateDisplay() {
    document.getElementById('dateDisplay').textContent = formatDate(currentDate);
    const isToday = currentDate === todayStr();
    document.getElementById('todayBadge').classList.toggle('hidden', !isToday);
    document.getElementById('goTodayBtn').classList.toggle('hidden', isToday);
}

function renderMealToggle() {
    const lunch = document.getElementById('mealLunch');
    const dinner = document.getElementById('mealDinner');
    if (currentMeal === 'lunch') {
        lunch.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-orange-500 text-white shadow-sm';
        dinner.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-white text-gray-600 border border-gray-200';
    } else {
        dinner.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-orange-500 text-white shadow-sm';
        lunch.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-white text-gray-600 border border-gray-200';
    }
}

async function loadPlan() {
    renderDateDisplay();
    renderMealToggle();
    localPortions = {};
    document.getElementById('loadingState').classList.remove('hidden');
    ['dishList', 'planBar', 'addDishArea', 'paxSetup', 'noMenuState', 'errorBox', 'grocerySummary'].forEach(id =>
        document.getElementById(id).classList.add('hidden'));

    try {
        const data = await api(`api/menu-plan.php?action=get&date=${currentDate}&meal=${currentMeal}`);
        planData = data.plan;
        dishesData = data.dishes || [];
        fixedMenuData = data.fixed_menu || [];
        recipesData = data.recipes || [];
        renderPlan();
    } catch (err) {
        showError(err.message);
    } finally {
        document.getElementById('loadingState').classList.add('hidden');
    }
}

function showError(msg) {
    document.getElementById('errorText').textContent = msg;
    document.getElementById('errorBox').classList.remove('hidden');
}

function renderPlan() {
    const isConfirmed = planData?.status === 'confirmed';

    if (!planData) {
        if (fixedMenuData.length > 0) {
            document.getElementById('paxSetup').classList.remove('hidden');
            document.getElementById('fixedMenuCount').textContent = `${fixedMenuData.length} dishes for today`;
            document.getElementById('subtitle').textContent = 'Set guest count to start';

            const catLabels = { appetizer: 'Appetizer', soup: 'Soup', salad: 'Salad', main_course: 'Main', side: 'Side', dessert: 'Dessert', beverage: 'Beverage' };
            document.getElementById('fixedPreview').innerHTML = fixedMenuData.map(r => `
                <div class="bg-white rounded-lg border border-gray-100 px-3 py-2.5 flex items-center gap-2">
                    <span class="text-[10px] text-orange-600 bg-orange-50 px-1.5 py-0.5 rounded-full shrink-0">${catLabels[r.category] || r.category}</span>
                    <span class="text-sm text-gray-800 truncate">${r.recipe_name}</span>
                </div>
            `).join('');
        } else {
            document.getElementById('noMenuState').classList.remove('hidden');
            document.getElementById('subtitle').textContent = 'No menu configured';
        }
        return;
    }

    // Plan exists
    document.getElementById('subtitle').textContent = isConfirmed ? 'Confirmed menu' : 'Draft \u2014 adjust portions & confirm';
    document.getElementById('planBar').classList.remove('hidden');

    const badge = document.getElementById('planStatusBadge');
    badge.textContent = isConfirmed ? 'Confirmed' : 'Draft';
    badge.className = 'text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ' +
        (isConfirmed ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700');

    document.getElementById('planInfo').textContent = `${dishesData.length} dish${dishesData.length !== 1 ? 'es' : ''}`;

    // Actions
    let actionsHtml = '';
    if (planData.status === 'draft') {
        actionsHtml = `<button onclick="confirmPlan()" class="text-xs text-green-700 font-semibold bg-green-50 px-2.5 py-1 rounded-lg compact-btn ${dishesData.length === 0 ? 'opacity-40 pointer-events-none' : ''}">Confirm</button>`;
    } else {
        actionsHtml = `<button onclick="reopenPlan()" class="text-xs text-amber-700 font-medium bg-amber-50 px-2.5 py-1 rounded-lg compact-btn">Reopen</button>`;
    }
    document.getElementById('planActions').innerHTML = actionsHtml;

    // Dishes
    const list = document.getElementById('dishList');
    if (dishesData.length > 0) {
        list.classList.remove('hidden');
        list.innerHTML = dishesData.map(dish => renderDishCard(dish, isConfirmed)).join('');
    }

    // Grocery summary
    if (dishesData.length > 0) {
        document.getElementById('grocerySummary').classList.remove('hidden');
        renderGrocerySummary();
    }

    // Add dish button (only if draft)
    if (!isConfirmed) {
        document.getElementById('addDishArea').classList.remove('hidden');
    }
}

function getDishPortions(dish) {
    return localPortions[dish.id] !== undefined ? localPortions[dish.id] : dish.portions;
}

function calcIngQty(ing, dishPortions, recipeServings) {
    const baseQty = parseFloat(ing.qty) || 0;
    const servings = recipeServings || 20;
    return Math.round((baseQty * dishPortions / servings) * 100) / 100;
}

function renderDishCard(dish, isConfirmed) {
    const ings = (dish.ingredients || []).filter(i => !i.is_removed);
    const courseLabels = { appetizer: 'Appetizer', soup: 'Soup', salad: 'Salad', main_course: 'Main', side: 'Side', dessert: 'Dessert', beverage: 'Beverage' };
    const portions = getDishPortions(dish);
    const recipeServings = dish.recipe_servings || 20;
    const hasChanges = localPortions[dish.id] !== undefined && localPortions[dish.id] !== dish.portions;

    // Portions stepper
    let portionControls = '';
    if (!isConfirmed) {
        portionControls = `
            <div class="flex items-center gap-1">
                <button onclick="event.stopPropagation(); adjPortions(${dish.id}, -1)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-700 font-bold text-base flex items-center justify-center compact-btn active:bg-gray-200">-</button>
                <span class="w-10 text-center text-sm font-bold text-orange-700" id="pax_${dish.id}">${portions}</span>
                <button onclick="event.stopPropagation(); adjPortions(${dish.id}, 1)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-700 font-bold text-base flex items-center justify-center compact-btn active:bg-gray-200">+</button>
                <span class="text-[10px] text-gray-400 ml-0.5">pax</span>
                ${hasChanges ? `<button onclick="event.stopPropagation(); savePortions(${dish.id})" class="ml-1 text-[10px] text-white bg-orange-500 px-2 py-1 rounded-lg font-semibold compact-btn">Save</button>` : ''}
            </div>`;
    } else {
        portionControls = `<span class="text-xs font-medium text-orange-700 bg-orange-50 px-2 py-1 rounded-lg">${portions} pax</span>`;
    }

    // Ingredients list (always visible — key to the UX)
    let ingredientsHtml = '';
    if (ings.length > 0) {
        ingredientsHtml = `
        <div class="border-t border-gray-100 px-3 py-2 bg-gray-50/50">
            <div class="space-y-0.5">
                ${ings.map(ing => {
                    const calcQty = calcIngQty(ing, portions, recipeServings);
                    return `
                    <div class="flex items-center justify-between py-0.5">
                        <span class="text-xs text-gray-700 truncate flex-1">${ing.item_name}</span>
                        <span class="text-xs font-semibold text-gray-900 shrink-0 ml-2" id="ing_${dish.id}_${ing.id}">${calcQty} ${ing.uom}</span>
                    </div>`;
                }).join('')}
            </div>
        </div>`;
    }

    return `
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
        <div class="flex items-center gap-2 px-3 py-2.5">
            <div class="flex-1 min-w-0">
                <span class="font-semibold text-sm text-gray-900 truncate block">${dish.dish_name}</span>
                <span class="text-[10px] text-gray-400">${courseLabels[dish.course] || dish.course}</span>
            </div>
            ${!isConfirmed ? `<button onclick="removeDish(${dish.id})" class="text-red-400 p-1 shrink-0 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>` : ''}
        </div>
        <!-- Portions stepper row -->
        <div class="border-t border-gray-100 px-3 py-2 flex items-center justify-between bg-orange-50/30">
            <span class="text-[10px] font-semibold text-gray-500 uppercase">Portions</span>
            ${portionControls}
        </div>
        ${ingredientsHtml}
    </div>`;
}

// ── Portions adjustment (real-time, no API call yet) ──
function adjPortions(dishId, delta) {
    const dish = dishesData.find(d => d.id === dishId);
    if (!dish) return;

    const current = getDishPortions(dish);
    const newVal = Math.max(1, current + delta);
    localPortions[dishId] = newVal;

    // Update pax display
    const paxEl = document.getElementById(`pax_${dishId}`);
    if (paxEl) paxEl.textContent = newVal;

    // Update all ingredient quantities for this dish in real-time
    const ings = (dish.ingredients || []).filter(i => !i.is_removed);
    const recipeServings = dish.recipe_servings || 20;
    ings.forEach(ing => {
        const el = document.getElementById(`ing_${dishId}_${ing.id}`);
        if (el) {
            const calcQty = calcIngQty(ing, newVal, recipeServings);
            el.textContent = `${calcQty} ${ing.uom}`;
        }
    });

    // Update grocery summary in real-time
    renderGrocerySummary();

    // Re-render just this card to show/hide Save button
    reRenderDishes();
}

async function savePortions(dishId) {
    const portions = localPortions[dishId];
    if (portions === undefined) return;

    try {
        await api('api/menu-plan.php', { method: 'POST', body: { action: 'update_portions', dish_id: dishId, portions } });
        showToast('Portions saved');
        delete localPortions[dishId];
        loadPlan();
    } catch (err) { showToast(err.message, 'error'); }
}

function reRenderDishes() {
    const list = document.getElementById('dishList');
    const isConfirmed = planData?.status === 'confirmed';
    list.innerHTML = dishesData.map(d => renderDishCard(d, isConfirmed)).join('');
}

// ── Grocery Summary (aggregated across all dishes) ──
function toggleGrocerySummary() {
    grocerySummaryOpen = !grocerySummaryOpen;
    document.getElementById('groceryList').classList.toggle('hidden', !grocerySummaryOpen);
    document.getElementById('groceryChevron').classList.toggle('rotate-180', grocerySummaryOpen);
}

function renderGrocerySummary() {
    const agg = {}; // itemName → { qty, uom }
    dishesData.forEach(dish => {
        const portions = getDishPortions(dish);
        const recipeServings = dish.recipe_servings || 20;
        (dish.ingredients || []).filter(i => !i.is_removed).forEach(ing => {
            const key = ing.item_name;
            const calcQty = calcIngQty(ing, portions, recipeServings);
            if (!agg[key]) agg[key] = { qty: 0, uom: ing.uom };
            agg[key].qty += calcQty;
        });
    });

    // Sort by name
    const sorted = Object.entries(agg).sort((a, b) => a[0].localeCompare(b[0]));

    const el = document.getElementById('groceryList');
    if (sorted.length === 0) {
        el.innerHTML = '<p class="text-xs text-gray-400 text-center py-2">No ingredients</p>';
        return;
    }

    el.innerHTML = sorted.map(([name, data]) => `
        <div class="flex items-center justify-between py-1 border-b border-orange-100 last:border-0">
            <span class="text-xs text-gray-800 truncate flex-1">${name}</span>
            <span class="text-xs font-bold text-orange-800 shrink-0 ml-2">${Math.round(data.qty * 100) / 100} ${data.uom}</span>
        </div>
    `).join('');
}

// ── Create plan from fixed menu ──
function adjSetupPax(delta) {
    const input = document.getElementById('setupPaxInput');
    input.value = Math.max(1, parseInt(input.value || 20) + delta);
}

async function createFromFixed() {
    const pax = parseInt(document.getElementById('setupPaxInput').value) || 20;
    const btn = document.getElementById('createFixedBtn');
    btn.disabled = true;
    btn.textContent = 'Loading...';
    try {
        await api('api/menu-plan.php', { method: 'POST', body: { action: 'create_from_fixed', date: currentDate, meal: currentMeal, pax } });
        showToast('Menu loaded!');
        loadPlan();
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Load Fixed Menu';
    }
}

// ── Add Dish (with ingredient form) ──
function showAddDish() {
    const catLabels = { appetizer: 'Appetizer', soup: 'Soup', salad: 'Salad', main_course: 'Main Course', side: 'Side', dessert: 'Dessert', beverage: 'Beverage' };
    const grouped = {};
    recipesData.forEach(r => {
        const cat = r.category || 'other';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(r);
    });

    let recipeOptions = '<option value="">-- No recipe (custom dish) --</option>';
    for (const [cat, items] of Object.entries(grouped)) {
        recipeOptions += `<optgroup label="${cat.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}">`;
        items.forEach(r => { recipeOptions += `<option value="${r.id}" data-name="${r.name}" data-cat="${r.category}">${r.name}</option>`; });
        recipeOptions += '</optgroup>';
    }

    let html = `
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Add Dish</h3>
            <button onclick="closeSheet()" class="p-1 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 scroll-touch space-y-3">
            <div>
                <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Pick Recipe (optional)</label>
                <select id="addRecipePicker" onchange="onAddRecipePick()" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm">${recipeOptions}</select>
            </div>
            <div>
                <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Dish Name</label>
                <input type="text" id="addDishName" placeholder="e.g. Grilled Chicken Special" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                <p id="addRecipeNote" class="hidden text-[10px] text-orange-600 mt-1">Ingredients will be auto-loaded from recipe</p>
            </div>
            <div class="flex gap-3">
                <div class="flex-1">
                    <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Course</label>
                    <select id="addCoursePicker" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
                        <option value="appetizer">Appetizer</option>
                        <option value="soup">Soup</option>
                        <option value="salad">Salad</option>
                        <option value="main_course" selected>Main Course</option>
                        <option value="side">Side</option>
                        <option value="dessert">Dessert</option>
                        <option value="beverage">Beverage</option>
                    </select>
                </div>
                <div class="w-28">
                    <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Portions</label>
                    <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
                        <button onclick="addPortionsAdj(-5)" class="px-2 py-2.5 text-gray-600 hover:bg-gray-100 compact-btn">-</button>
                        <input type="number" id="addPortionsInput" value="${planData?.portions || 20}" min="1" class="w-full text-center text-sm font-semibold bg-transparent border-0 focus:outline-none py-2.5 compact-btn">
                        <button onclick="addPortionsAdj(5)" class="px-2 py-2.5 text-gray-600 hover:bg-gray-100 compact-btn">+</button>
                    </div>
                </div>
            </div>

            <!-- Custom ingredients area (shown when no recipe selected) -->
            <div id="addIngredientsArea" class="hidden">
                <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Ingredients needed</label>
                <div id="addIngredientsList" class="space-y-2 mb-2"></div>
                <div class="relative">
                    <input type="text" id="addIngSearch" oninput="searchAddIngredient()" placeholder="Search item to add..."
                        class="w-full pl-3 pr-8 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <div id="addIngResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto z-50"></div>
                </div>
            </div>

            <button onclick="submitAddDish()" id="submitAddDishBtn" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-lg text-sm font-semibold transition">Add Dish</button>
        </div>`;
    openSheet(html);
}

const categoryToCourse = { appetizer: 'appetizer', soup: 'soup', salad: 'salad', main_course: 'main_course', side: 'side', dessert: 'dessert', beverage: 'beverage' };

function onAddRecipePick() {
    const select = document.getElementById('addRecipePicker');
    const opt = select.options[select.selectedIndex];
    const ingArea = document.getElementById('addIngredientsArea');
    if (opt.value) {
        document.getElementById('addDishName').value = opt.dataset.name;
        document.getElementById('addCoursePicker').value = categoryToCourse[opt.dataset.cat] || 'main_course';
        document.getElementById('addRecipeNote').classList.remove('hidden');
        ingArea.classList.add('hidden');
    } else {
        document.getElementById('addRecipeNote').classList.add('hidden');
        ingArea.classList.remove('hidden');
    }
}

function addPortionsAdj(delta) {
    const input = document.getElementById('addPortionsInput');
    input.value = Math.max(1, parseInt(input.value || 20) + delta);
}

// Custom ingredient management
let addIngredients = [];
let addSearchTimer = null;

function searchAddIngredient() {
    clearTimeout(addSearchTimer);
    const q = document.getElementById('addIngSearch').value.trim();
    if (q.length < 2) { document.getElementById('addIngResults').classList.add('hidden'); return; }

    addSearchTimer = setTimeout(async () => {
        try {
            const data = await api(`api/menu-plan.php?action=search_items&q=${encodeURIComponent(q)}`);
            const results = document.getElementById('addIngResults');
            if (data.items.length === 0) { results.classList.add('hidden'); return; }
            results.classList.remove('hidden');
            results.innerHTML = data.items.map(item => `
                <button onclick='selectAddIngredient(${JSON.stringify(item).replace(/'/g, "&#39;")})' class="w-full text-left px-3 py-2 text-sm hover:bg-orange-50 border-b border-gray-50 last:border-0 compact-btn">
                    <span class="font-medium text-gray-900">${item.name}</span>
                    <span class="text-xs text-gray-400 ml-1">${item.uom}</span>
                </button>
            `).join('');
        } catch {}
    }, 300);
}

function selectAddIngredient(item) {
    document.getElementById('addIngResults').classList.add('hidden');
    document.getElementById('addIngSearch').value = '';
    if (addIngredients.find(i => i.item_id === item.id)) {
        showToast('Already added', 'warning');
        return;
    }
    addIngredients.push({ item_id: item.id, item_name: item.name, uom: item.uom, qty: 1 });
    renderAddIngredients();
}

function renderAddIngredients() {
    const list = document.getElementById('addIngredientsList');
    list.innerHTML = addIngredients.map((ing, i) => `
        <div class="flex items-center gap-2 bg-gray-50 rounded-lg px-2 py-1.5">
            <span class="text-xs text-gray-800 flex-1 truncate">${ing.item_name}</span>
            <input type="number" value="${ing.qty}" step="0.1" min="0.1" onchange="addIngredients[${i}].qty=parseFloat(this.value)||1"
                class="w-16 text-center text-xs border border-gray-200 rounded px-1 py-1 compact-btn">
            <span class="text-[10px] text-gray-400">${ing.uom}</span>
            <button onclick="addIngredients.splice(${i},1); renderAddIngredients()" class="text-red-400 compact-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    `).join('');
}

async function submitAddDish() {
    const name = document.getElementById('addDishName').value.trim();
    if (!name) return showToast('Enter a dish name', 'warning');

    const course = document.getElementById('addCoursePicker').value;
    const portions = parseInt(document.getElementById('addPortionsInput').value) || 20;
    const recipeId = document.getElementById('addRecipePicker').value || null;

    if (!recipeId && addIngredients.length === 0) {
        return showToast('Add at least one ingredient', 'warning');
    }

    const btn = document.getElementById('submitAddDishBtn');
    btn.disabled = true;
    btn.textContent = 'Adding...';

    try {
        await api('api/menu-plan.php', { method: 'POST', body: {
            action: 'add_dish',
            plan_id: planData.id,
            dish_name: name,
            course,
            portions,
            recipe_id: recipeId ? parseInt(recipeId) : null,
            ingredients: !recipeId ? addIngredients : [],
        }});
        closeSheet();
        addIngredients = [];
        showToast('Dish added!');
        loadPlan();
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Add Dish';
    }
}

// ── Remove Dish ──
async function removeDish(dishId) {
    if (!confirm('Remove this dish?')) return;
    try {
        await api('api/menu-plan.php', { method: 'POST', body: { action: 'remove_dish', dish_id: dishId } });
        showToast('Dish removed');
        loadPlan();
    } catch (err) { showToast(err.message, 'error'); }
}

// ── Confirm / Reopen ──
async function confirmPlan() {
    // Auto-save any unsaved portion changes first
    const unsaved = Object.keys(localPortions);
    if (unsaved.length > 0) {
        for (const dishId of unsaved) {
            await api('api/menu-plan.php', { method: 'POST', body: { action: 'update_portions', dish_id: parseInt(dishId), portions: localPortions[dishId] } });
        }
        localPortions = {};
    }

    if (!confirm('Confirm this menu plan?')) return;
    try {
        await api('api/menu-plan.php', { method: 'POST', body: { action: 'confirm_plan', plan_id: planData.id } });
        showToast('Plan confirmed!');
        loadPlan();
    } catch (err) { showToast(err.message, 'error'); }
}

async function reopenPlan() {
    try {
        await api('api/menu-plan.php', { method: 'POST', body: { action: 'reopen_plan', plan_id: planData.id } });
        showToast('Plan reopened');
        loadPlan();
    } catch (err) { showToast(err.message, 'error'); }
}
</script>
