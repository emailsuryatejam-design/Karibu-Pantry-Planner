<!-- Menu Plan Page — Chef daily meal planning -->
<div id="menuPlanApp">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-600"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
                Menu Plan
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">Plan daily meals with recipes</p>
        </div>
        <button onclick="loadAudit()" id="auditBtn" class="hidden items-center gap-1.5 text-xs text-gray-600 bg-gray-100 px-3 py-1.5 rounded-full compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
            Audit
        </button>
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
        <button onclick="setMeal('lunch')" id="mealLunch" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all">
            ☀️ Lunch
        </button>
        <button onclick="setMeal('dinner')" id="mealDinner" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all">
            🌙 Dinner
        </button>
    </div>

    <!-- Error -->
    <div id="errorBox" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-3">
        <p class="text-sm text-red-700" id="errorText"></p>
    </div>

    <!-- Plan Status Bar -->
    <div id="planBar" class="hidden bg-white rounded-xl border border-gray-100 px-4 py-2.5 mb-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span id="planPaxBtn" class="text-sm font-bold text-orange-700 bg-orange-50 px-3 py-1 rounded-lg cursor-pointer">20</span>
                <span class="text-[10px] text-gray-400">pax</span>
                <span id="planStatusBadge" class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"></span>
                <span id="planInfo" class="text-[10px] text-gray-400"></span>
            </div>
            <div id="planActions"></div>
        </div>
    </div>

    <!-- Loading -->
    <div id="loadingState" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-orange-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading menu plan...</p>
    </div>

    <!-- Dishes List -->
    <div id="dishList" class="space-y-2 hidden"></div>

    <!-- Empty State -->
    <div id="emptyState" class="hidden bg-white rounded-xl border border-gray-100 p-6 text-center mb-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-300 mx-auto mb-2"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
        <p class="text-sm text-gray-500 mb-1">No menu plan yet</p>
        <p class="text-xs text-gray-400">Add a dish below to start planning</p>
    </div>

    <!-- Add Dish Button -->
    <div id="addDishArea" class="hidden">
        <button onclick="showAddDish()" id="addDishBtn"
            class="w-full flex items-center justify-center gap-2 py-4 border-2 border-dashed border-gray-300 rounded-xl text-gray-500 hover:border-orange-400 hover:text-orange-600 hover:bg-orange-50/50 transition font-semibold text-sm active:bg-orange-50">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Add Dish
        </button>

        <!-- Inline Add Form (hidden by default) -->
        <div id="addDishForm" class="hidden bg-white rounded-xl border-2 border-orange-200 overflow-hidden mt-2">
            <div class="flex items-center justify-between px-4 py-3 bg-orange-50 border-b border-orange-100">
                <h3 class="text-sm font-bold text-orange-800">Add Dish</h3>
                <button onclick="hideAddDish()" class="text-orange-400 hover:text-orange-600 compact-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="px-4 py-3 space-y-3">
                <!-- Recipe picker -->
                <div>
                    <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Pick Recipe</label>
                    <select id="recipePicker" onchange="onRecipePick(this)" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <option value="">-- Select a recipe or type name below --</option>
                    </select>
                </div>
                <!-- Dish name -->
                <div>
                    <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Dish Name</label>
                    <input type="text" id="dishNameInput" placeholder="e.g. Grilled Chicken with Herbs"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <p id="recipeNote" class="hidden text-[10px] text-orange-600 mt-1">Recipe ingredients will be auto-loaded</p>
                </div>
                <!-- Course + Portions -->
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1 block">Course</label>
                        <select id="coursePicker" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-200">
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
                            <button onclick="adjustPortions(-5)" class="px-2 py-2.5 text-gray-600 hover:bg-gray-100 compact-btn">-</button>
                            <input type="number" id="portionsInput" value="20" min="1"
                                class="w-full text-center text-sm font-semibold bg-transparent border-0 focus:outline-none py-2.5 compact-btn">
                            <button onclick="adjustPortions(5)" class="px-2 py-2.5 text-gray-600 hover:bg-gray-100 compact-btn">+</button>
                        </div>
                    </div>
                </div>
                <!-- Submit -->
                <button onclick="submitDish()" id="submitDishBtn"
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-lg text-sm font-semibold disabled:opacity-40 flex items-center justify-center gap-2 transition active:bg-orange-700">
                    Add Dish
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── State ──
let currentDate = todayStr();
let currentMeal = 'lunch';
let planData = null;
let dishesData = [];
let recipesData = [];

// ── Init ──
loadPlan();

// ── Date Navigation ──
function navDate(days) {
    currentDate = changeDate(currentDate, days);
    renderDateDisplay();
    loadPlan();
}

function goToday() {
    currentDate = todayStr();
    renderDateDisplay();
    loadPlan();
}

function setMeal(meal) {
    currentMeal = meal;
    renderMealToggle();
    loadPlan();
}

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

// ── Load Plan ──
async function loadPlan() {
    renderDateDisplay();
    renderMealToggle();
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('dishList').classList.add('hidden');
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('planBar').classList.add('hidden');
    document.getElementById('addDishArea').classList.add('hidden');
    document.getElementById('errorBox').classList.add('hidden');

    try {
        const data = await api(`api/menu-plan.php?action=get&date=${currentDate}&meal=${currentMeal}`);
        planData = data.plan;
        dishesData = data.dishes || [];
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

// ── Render Plan ──
function renderPlan() {
    const isConfirmed = planData?.status === 'confirmed';

    // Plan bar
    if (planData) {
        document.getElementById('planBar').classList.remove('hidden');
        document.getElementById('auditBtn').classList.remove('hidden');
        document.getElementById('auditBtn').classList.add('flex');
        document.getElementById('planPaxBtn').textContent = planData.portions;
        document.getElementById('planPaxBtn').onclick = isConfirmed ? null : () => editPax();

        const badge = document.getElementById('planStatusBadge');
        badge.textContent = planData.status;
        badge.className = 'text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ' +
            (isConfirmed ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700');

        const totalIng = dishesData.reduce((sum, d) => sum + d.ingredients.filter(i => !i.is_removed).length, 0);
        document.getElementById('planInfo').textContent = `${dishesData.length} dish${dishesData.length !== 1 ? 'es' : ''}${totalIng > 0 ? ` · ${totalIng} ingredients` : ''}`;

        // Actions
        let actionsHtml = '';
        if (planData.status === 'draft') {
            actionsHtml = `<button onclick="confirmPlan()" class="text-xs text-green-700 font-semibold bg-green-50 px-2.5 py-1 rounded-lg compact-btn ${dishesData.length === 0 ? 'opacity-40 pointer-events-none' : ''}">✓ Confirm</button>`;
        } else {
            actionsHtml = `<button onclick="reopenPlan()" class="text-xs text-amber-700 font-medium bg-amber-50 px-2.5 py-1 rounded-lg compact-btn">↩ Reopen</button>`;
        }
        document.getElementById('planActions').innerHTML = actionsHtml;
    } else {
        document.getElementById('auditBtn').classList.add('hidden');
    }

    // Dishes
    const list = document.getElementById('dishList');
    if (dishesData.length > 0) {
        list.classList.remove('hidden');
        list.innerHTML = dishesData.map(dish => renderDishCard(dish, isConfirmed)).join('');
    } else {
        document.getElementById('emptyState').classList.remove('hidden');
    }

    // Add dish area (only if draft)
    if (!isConfirmed) {
        document.getElementById('addDishArea').classList.remove('hidden');
        populateRecipePicker();
    }
}

function renderDishCard(dish, isConfirmed) {
    const ingCount = dish.ingredients.filter(i => !i.is_removed).length;
    const primaryCount = dish.ingredients.filter(i => !i.is_removed && i.is_primary).length;
    const weeklyCount = ingCount - primaryCount;
    const courseLabels = { appetizer: 'Appetizer', soup: 'Soup', salad: 'Salad', main_course: 'Main', side: 'Side', dessert: 'Dessert', beverage: 'Beverage' };

    return `
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
        <div class="flex items-center gap-2 px-3 py-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-500 shrink-0"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
            <div class="flex-1 min-w-0">
                <span class="font-semibold text-sm text-gray-900 truncate block">${dish.dish_name}</span>
                <span class="text-[10px] text-gray-400">${courseLabels[dish.course] || dish.course}${ingCount > 0 ? ` · ${primaryCount} daily · ${weeklyCount} weekly` : ''}</span>
            </div>
            ${dish.recipe_id ? '<span class="text-[9px] bg-orange-50 text-orange-600 px-1.5 py-0.5 rounded-full shrink-0">📖</span>' : ''}
            ${dish.presentation_score > 0 ? `<span class="text-[10px] font-medium text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-full shrink-0">⭐ ${dish.presentation_score}</span>` : ''}
            <span class="text-[10px] font-medium text-orange-700 bg-orange-50 px-1.5 py-0.5 rounded-full shrink-0">👥 ${dish.portions}</span>
            ${!isConfirmed && !dish.is_default ? `<button onclick="removeDish(${dish.id})" class="text-red-400 p-0.5 shrink-0 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>` : ''}
        </div>
    </div>`;
}

// ── Recipe Picker ──
function populateRecipePicker() {
    const picker = document.getElementById('recipePicker');
    const grouped = {};
    recipesData.forEach(r => {
        const cat = r.category || 'other';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(r);
    });

    let html = '<option value="">-- Select a recipe or type name below --</option>';
    for (const [cat, items] of Object.entries(grouped)) {
        html += `<optgroup label="${cat.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}">`;
        items.forEach(r => {
            html += `<option value="${r.id}" data-name="${r.name}" data-cat="${r.category}">${r.name} (${r.ingredient_count || 0} ing)</option>`;
        });
        html += '</optgroup>';
    }
    picker.innerHTML = html;
}

const categoryToCourse = { breakfast: 'main_course', lunch: 'main_course', dinner: 'main_course', snack: 'side', dessert: 'dessert', sauce: 'side', soup: 'soup', salad: 'salad', bread: 'side', other: 'main_course' };

function onRecipePick(select) {
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        document.getElementById('dishNameInput').value = opt.dataset.name;
        document.getElementById('coursePicker').value = categoryToCourse[opt.dataset.cat] || 'main_course';
        document.getElementById('recipeNote').classList.remove('hidden');
    } else {
        document.getElementById('recipeNote').classList.add('hidden');
    }
}

// ── Add Dish ──
function showAddDish() {
    document.getElementById('addDishBtn').classList.add('hidden');
    document.getElementById('addDishForm').classList.remove('hidden');
}

function hideAddDish() {
    document.getElementById('addDishBtn').classList.remove('hidden');
    document.getElementById('addDishForm').classList.add('hidden');
    document.getElementById('dishNameInput').value = '';
    document.getElementById('recipePicker').value = '';
    document.getElementById('recipeNote').classList.add('hidden');
}

function adjustPortions(delta) {
    const input = document.getElementById('portionsInput');
    input.value = Math.max(1, parseInt(input.value || 20) + delta);
}

async function submitDish() {
    const name = document.getElementById('dishNameInput').value.trim();
    if (!name) return showToast('Enter a dish name', 'warning');

    const course = document.getElementById('coursePicker').value;
    const portions = parseInt(document.getElementById('portionsInput').value) || 20;
    const recipeId = document.getElementById('recipePicker').value || null;

    const btn = document.getElementById('submitDishBtn');
    btn.disabled = true;
    btn.textContent = 'Adding...';

    try {
        // Auto-create plan if needed
        let planId = planData?.id;
        if (!planId) {
            const createData = await api('api/menu-plan.php', { method: 'POST', body: { action: 'create_plan', date: currentDate, meal: currentMeal, portions } });
            planId = createData.plan_id;
        }

        // Add dish
        const data = await api('api/menu-plan.php', { method: 'POST', body: { action: 'add_dish', plan_id: planId, dish_name: name, course, portions, recipe_id: recipeId ? parseInt(recipeId) : null } });

        // Auto-load recipe if selected
        if (recipeId && data.dish_id) {
            try {
                await api('api/menu-plan.php', { method: 'POST', body: { action: 'load_recipe', dish_id: data.dish_id, recipe_id: parseInt(recipeId), portions } });
            } catch {}
        }

        hideAddDish();
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
    if (!confirm('Remove this dish and all its ingredients?')) return;
    try {
        await api('api/menu-plan.php', { method: 'POST', body: { action: 'remove_dish', dish_id: dishId } });
        showToast('Dish removed');
        loadPlan();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ── Confirm / Reopen ──
async function confirmPlan() {
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

// ── Edit Pax ──
function editPax() {
    const newPax = prompt('Total Pax (covers):', planData.portions);
    if (newPax && parseInt(newPax) > 0 && parseInt(newPax) !== planData.portions) {
        api('api/menu-plan.php', { method: 'POST', body: { action: 'update_plan_pax', plan_id: planData.id, pax: parseInt(newPax) } })
            .then(() => { showToast('Pax updated'); loadPlan(); })
            .catch(err => showToast(err.message, 'error'));
    }
}

// ── Audit Log ──
async function loadAudit() {
    if (!planData) return;
    try {
        const data = await api(`api/menu-plan.php?action=audit&plan_id=${planData.id}`);
        const logs = data.audit || [];
        const actionLabels = { create_plan: 'Created plan', update_portions: 'Updated portions', add_dish: 'Added dish', remove_dish: 'Removed dish', confirm_plan: 'Confirmed', reopen_plan: 'Reopened', load_recipe: 'Loaded recipe', update_plan_pax: 'Updated pax' };

        let html = `
            <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900">Audit Log</h3>
                <button onclick="closeSheet()" class="p-1 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            </div>
            <div class="flex-1 overflow-y-auto px-5 py-3 scroll-touch">`;

        if (logs.length === 0) {
            html += '<p class="text-center text-xs text-gray-400 py-8">No audit entries</p>';
        } else {
            logs.forEach(log => {
                const label = actionLabels[log.action] || log.action;
                const time = log.created_at ? new Date(log.created_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : '';
                html += `<div class="flex gap-3 mb-3">
                    <div class="w-2 h-2 rounded-full mt-1.5 bg-gray-400 shrink-0"></div>
                    <div class="flex-1 pb-2">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-50 text-gray-600">${label}</span>
                        <span class="text-[10px] text-gray-400 ml-1">${time}</span>
                        <p class="text-[11px] text-gray-600">${log.user_name || ''}</p>
                        ${log.dish_name ? `<p class="text-[10px] text-gray-400">${log.dish_name}</p>` : ''}
                    </div>
                </div>`;
            });
        }
        html += '</div>';
        openSheet(html);
    } catch (err) { showToast(err.message, 'error'); }
}
</script>
