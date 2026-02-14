<!-- Daily Groceries Page — Chef ingredient tracking + ordering -->
<div id="groceriesApp">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-blue-600"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                Daily Groceries
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">Order & track daily ingredients</p>
        </div>
    </div>

    <!-- Date Picker -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-100 px-4 py-3 mb-3">
        <button onclick="gNavDate(-1)" class="p-1.5 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="text-center">
            <p class="text-sm font-semibold text-gray-900" id="gDateDisplay"></p>
            <span id="gTodayBadge" class="text-[10px] text-green-600 font-medium hidden">Today</span>
            <button id="gGoTodayBtn" onclick="gGoToday()" class="text-[10px] text-blue-600 font-medium hidden">Go to Today</button>
        </div>
        <button onclick="gNavDate(1)" class="p-1.5 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>

    <!-- Meal Toggle -->
    <div class="flex gap-2 mb-3">
        <button onclick="gSetMeal('lunch')" id="gMealLunch" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-blue-500 text-white shadow-sm">☀️ Lunch</button>
        <button onclick="gSetMeal('dinner')" id="gMealDinner" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-white text-gray-600 border border-gray-200">🌙 Dinner</button>
    </div>

    <!-- Error -->
    <div id="gErrorBox" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-3">
        <p class="text-sm text-red-700" id="gErrorText"></p>
    </div>

    <!-- Order Status Banner -->
    <div id="gOrderBanner" class="hidden bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 mb-3">
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" id="gOrderStatus"></span>
            <span class="text-xs text-blue-700" id="gOrderInfo"></span>
        </div>
    </div>

    <!-- Loading -->
    <div id="gLoading" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-blue-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading groceries...</p>
    </div>

    <!-- Empty State -->
    <div id="gEmpty" class="hidden bg-white rounded-xl border border-gray-100 p-6 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-300 mx-auto mb-2"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
        <p class="text-sm text-gray-500 mb-1">No menu plan for this day</p>
        <p class="text-xs text-gray-400">Create a menu plan first to see daily groceries</p>
    </div>

    <!-- Ingredients Table -->
    <div id="gContent" class="hidden">
        <!-- Add Item Row -->
        <div class="bg-white rounded-xl border border-gray-100 mb-3">
            <button onclick="gShowAddItem()" id="gAddBtn" class="w-full flex items-center gap-2 px-3 py-3 text-sm text-gray-400 hover:text-blue-600 hover:bg-blue-50/50 transition border-b border-gray-100 compact-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                Add item...
            </button>
            <div id="gAddForm" class="hidden border-b border-gray-100 px-3 py-3 bg-blue-50/30">
                <div class="relative mb-2">
                    <input type="text" id="gSearchInput" oninput="gSearchItems()" placeholder="Search item..."
                        class="w-full pl-3 pr-8 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <button onclick="gHideAddItem()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 compact-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                    <div id="gSearchResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto z-50"></div>
                </div>
                <div id="gAddFields" class="hidden flex items-center gap-2">
                    <input type="number" id="gAddQty" placeholder="Qty" class="w-20 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 compact-btn">
                    <span id="gAddUom" class="text-xs text-gray-500">kg</span>
                    <button onclick="gAddItem()" class="bg-blue-500 text-white px-3 py-1.5 rounded-lg text-sm font-medium compact-btn">Add</button>
                </div>
            </div>

            <!-- Column Headers -->
            <div id="gHeaders" class="hidden grid grid-cols-[1fr_60px_60px_60px] gap-1 px-3 py-2 bg-gray-50 text-[10px] font-semibold text-gray-500 uppercase tracking-wider">
                <span>Item</span>
                <span class="text-center">Stock</span>
                <span class="text-center">Order</span>
                <span class="text-center">Recv</span>
            </div>

            <!-- Ingredient Rows -->
            <div id="gIngredients"></div>
        </div>

        <!-- Submit Order Button -->
        <button onclick="gSubmitOrder()" id="gSubmitBtn"
            class="hidden w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl text-sm font-semibold transition active:bg-blue-800 flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            Submit Order to Store
        </button>
    </div>
</div>

<script>
let gDate = todayStr();
let gMeal = 'lunch';
let gPlan = null;
let gDishes = [];
let gIngredients = [];
let gOrder = null;
let gSelectedItem = null;
let gSearchTimer = null;

gLoadData();

function gNavDate(days) { gDate = changeDate(gDate, days); gRenderDate(); gLoadData(); }
function gGoToday() { gDate = todayStr(); gRenderDate(); gLoadData(); }
function gSetMeal(m) { gMeal = m; gRenderMeal(); gLoadData(); }

function gRenderDate() {
    document.getElementById('gDateDisplay').textContent = formatDate(gDate);
    const isToday = gDate === todayStr();
    document.getElementById('gTodayBadge').classList.toggle('hidden', !isToday);
    document.getElementById('gGoTodayBtn').classList.toggle('hidden', isToday);
}

function gRenderMeal() {
    const l = document.getElementById('gMealLunch'), d = document.getElementById('gMealDinner');
    if (gMeal === 'lunch') {
        l.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-blue-500 text-white shadow-sm';
        d.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-white text-gray-600 border border-gray-200';
    } else {
        d.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-blue-500 text-white shadow-sm';
        l.className = 'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all bg-white text-gray-600 border border-gray-200';
    }
}

async function gLoadData() {
    gRenderDate(); gRenderMeal();
    document.getElementById('gLoading').classList.remove('hidden');
    document.getElementById('gContent').classList.add('hidden');
    document.getElementById('gEmpty').classList.add('hidden');
    document.getElementById('gErrorBox').classList.add('hidden');
    document.getElementById('gOrderBanner').classList.add('hidden');

    try {
        const data = await api(`api/daily-groceries.php?action=get&date=${gDate}&meal=${gMeal}`);
        gPlan = data.plan;
        gDishes = data.dishes || [];
        gIngredients = data.ingredients || [];
        gOrder = data.order;
        gRender();
    } catch (err) {
        document.getElementById('gErrorText').textContent = err.message;
        document.getElementById('gErrorBox').classList.remove('hidden');
    } finally {
        document.getElementById('gLoading').classList.add('hidden');
    }
}

function gRender() {
    if (!gPlan) {
        document.getElementById('gEmpty').classList.remove('hidden');
        return;
    }

    document.getElementById('gContent').classList.remove('hidden');

    // Order banner
    if (gOrder) {
        const banner = document.getElementById('gOrderBanner');
        banner.classList.remove('hidden');
        const statusEl = document.getElementById('gOrderStatus');
        statusEl.textContent = gOrder.status;
        statusEl.className = `text-[10px] font-bold uppercase px-2 py-0.5 rounded-full badge-${gOrder.status}`;
        document.getElementById('gOrderInfo').textContent = `Order submitted · ${gOrder.total_items} items`;
    }

    // Headers + ingredients
    if (gIngredients.length > 0) {
        document.getElementById('gHeaders').classList.remove('hidden');
        document.getElementById('gHeaders').classList.add('grid');
    }

    const container = document.getElementById('gIngredients');
    container.innerHTML = gIngredients.map(ing => `
        <div class="grid grid-cols-[1fr_60px_60px_60px] gap-1 px-3 py-2.5 border-b border-gray-50 items-center">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">${ing.item_name}</p>
                <p class="text-[10px] text-gray-400 truncate">${ing.dish_name} · ${ing.final_qty || ing.qty}${ing.uom}</p>
            </div>
            <input type="number" value="${ing.stock_qty ?? ''}" placeholder="—"
                onblur="gUpdateField(${ing.id}, 'stock_qty', this.value, ${ing.item_id || 0})"
                class="w-full text-center text-xs border border-gray-200 rounded px-1 py-1 focus:outline-none focus:ring-1 focus:ring-blue-300 compact-btn">
            <input type="number" value="${ing.ordered_qty ?? ''}" placeholder="—"
                onblur="gUpdateTracking(${ing.id}, 'ordered_qty', this.value)"
                class="w-full text-center text-xs border border-gray-200 rounded px-1 py-1 focus:outline-none focus:ring-1 focus:ring-blue-300 compact-btn">
            <input type="number" value="${ing.received_qty ?? ''}" placeholder="—"
                onblur="gUpdateTracking(${ing.id}, 'received_qty', this.value)"
                class="w-full text-center text-xs border border-gray-200 rounded px-1 py-1 focus:outline-none focus:ring-1 focus:ring-blue-300 compact-btn">
        </div>
    `).join('');

    // Submit button (only if no order exists yet)
    const submitBtn = document.getElementById('gSubmitBtn');
    if (!gOrder && gIngredients.length > 0) {
        submitBtn.classList.remove('hidden');
        submitBtn.classList.add('flex');
    } else {
        submitBtn.classList.add('hidden');
    }
}

async function gUpdateTracking(ingredientId, field, value) {
    try {
        await api('api/daily-groceries.php', { method: 'POST', body: { action: 'update_tracking', ingredient_id: ingredientId, field, value } });
    } catch (err) { showToast(err.message, 'error'); }
}

async function gUpdateField(ingredientId, field, value, itemId) {
    try {
        // Update stock in items table
        if (field === 'stock_qty' && itemId) {
            await api('api/daily-groceries.php', { method: 'POST', body: { action: 'update_stock', item_id: itemId, qty: value } });
        }
        await api('api/daily-groceries.php', { method: 'POST', body: { action: 'update_tracking', ingredient_id: ingredientId, field, value } });
    } catch (err) { showToast(err.message, 'error'); }
}

// Add item
function gShowAddItem() {
    document.getElementById('gAddBtn').classList.add('hidden');
    document.getElementById('gAddForm').classList.remove('hidden');
    document.getElementById('gSearchInput').focus();
}
function gHideAddItem() {
    document.getElementById('gAddBtn').classList.remove('hidden');
    document.getElementById('gAddForm').classList.add('hidden');
    document.getElementById('gSearchResults').classList.add('hidden');
    document.getElementById('gAddFields').classList.add('hidden');
    document.getElementById('gSearchInput').value = '';
    gSelectedItem = null;
}

function gSearchItems() {
    clearTimeout(gSearchTimer);
    const q = document.getElementById('gSearchInput').value.trim();
    if (q.length < 2) { document.getElementById('gSearchResults').classList.add('hidden'); return; }

    gSearchTimer = setTimeout(async () => {
        try {
            const data = await api(`api/daily-groceries.php?action=search_items&q=${encodeURIComponent(q)}`);
            const results = document.getElementById('gSearchResults');
            if (data.items.length === 0) { results.classList.add('hidden'); return; }
            results.classList.remove('hidden');
            results.innerHTML = data.items.map(item => `
                <button onclick='gSelectItem(${JSON.stringify(item).replace(/'/g, "&#39;")})' class="w-full text-left px-3 py-2.5 text-sm hover:bg-blue-50 border-b border-gray-50 last:border-0 compact-btn">
                    <span class="font-medium text-gray-900">${item.name}</span>
                    <span class="text-xs text-gray-400 ml-2">${item.uom}</span>
                </button>
            `).join('');
        } catch {}
    }, 300);
}

function gSelectItem(item) {
    gSelectedItem = item;
    document.getElementById('gSearchInput').value = item.name;
    document.getElementById('gSearchResults').classList.add('hidden');
    document.getElementById('gAddFields').classList.remove('hidden');
    document.getElementById('gAddUom').textContent = item.uom;
    document.getElementById('gAddQty').focus();
}

async function gAddItem() {
    if (!gSelectedItem) return;
    const qty = parseFloat(document.getElementById('gAddQty').value);
    if (!qty || qty <= 0) return showToast('Enter a quantity', 'warning');

    const dishId = gDishes[0]?.id;
    if (!dishId) return showToast('No dishes to add to', 'warning');

    try {
        await api('api/daily-groceries.php', { method: 'POST', body: { action: 'add_ingredient', dish_id: dishId, item_id: gSelectedItem.id, qty, uom: gSelectedItem.uom } });
        gHideAddItem();
        showToast('Item added');
        gLoadData();
    } catch (err) { showToast(err.message, 'error'); }
}

// Submit order
async function gSubmitOrder() {
    const items = gIngredients
        .filter(ing => ing.ordered_qty > 0)
        .map(ing => ({ item_id: ing.item_id, item_name: ing.item_name, qty: ing.ordered_qty, uom: ing.uom }));

    if (items.length === 0) {
        // If no ordered_qty set, include all ingredients with their final_qty
        gIngredients.forEach(ing => {
            items.push({ item_id: ing.item_id, item_name: ing.item_name, qty: ing.final_qty || ing.qty, uom: ing.uom });
        });
    }

    if (items.length === 0) return showToast('No items to order', 'warning');
    if (!confirm(`Submit order with ${items.length} items to the storekeeper?`)) return;

    const btn = document.getElementById('gSubmitBtn');
    btn.disabled = true;

    try {
        await api('api/daily-groceries.php', { method: 'POST', body: { action: 'submit_order', date: gDate, meal: gMeal, items } });
        showToast('Order submitted to storekeeper!');
        gLoadData();
    } catch (err) { showToast(err.message, 'error'); }
    finally { btn.disabled = false; }
}
</script>
