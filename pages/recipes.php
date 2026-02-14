<!-- Recipes Page — Master recipe management -->
<div id="recipesApp">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-600"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                Recipes
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">Manage kitchen recipes</p>
        </div>
        <button onclick="rShowCreate()" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Add
        </button>
    </div>

    <!-- Search -->
    <div class="relative mb-3">
        <input type="text" id="rSearchInput" oninput="rSearch()" placeholder="Search recipes..."
            class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-orange-200">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    </div>

    <!-- Category Tabs -->
    <div class="flex gap-1.5 mb-3 overflow-x-auto pb-1">
        <button onclick="rFilterCat('')" id="rCatAll" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-orange-500 text-white compact-btn">All</button>
        <button onclick="rFilterCat('main_course')" id="rCat_main_course" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600 compact-btn">Main Course</button>
        <button onclick="rFilterCat('appetizer')" id="rCat_appetizer" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600 compact-btn">Appetizer</button>
        <button onclick="rFilterCat('soup')" id="rCat_soup" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600 compact-btn">Soup</button>
        <button onclick="rFilterCat('salad')" id="rCat_salad" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600 compact-btn">Salad</button>
        <button onclick="rFilterCat('dessert')" id="rCat_dessert" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600 compact-btn">Dessert</button>
        <button onclick="rFilterCat('side')" id="rCat_side" class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600 compact-btn">Side</button>
    </div>

    <!-- Loading -->
    <div id="rLoading" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-orange-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading recipes...</p>
    </div>

    <!-- Recipe List -->
    <div id="rList" class="space-y-2 hidden"></div>

    <!-- Empty -->
    <div id="rEmpty" class="hidden text-center py-12">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-300 mx-auto mb-2"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
        <p class="text-sm text-gray-500">No recipes found</p>
    </div>
</div>

<script>
let rRecipes = [];
let rCategory = '';
let rSearchTimer = null;

rLoadRecipes();

function rSearch() {
    clearTimeout(rSearchTimer);
    rSearchTimer = setTimeout(() => rLoadRecipes(), 300);
}

function rFilterCat(cat) {
    rCategory = cat;
    // Update tab styles
    document.querySelectorAll('[id^="rCat"]').forEach(btn => {
        btn.className = 'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-gray-100 text-gray-600 compact-btn';
    });
    const activeId = cat ? `rCat_${cat}` : 'rCatAll';
    const activeEl = document.getElementById(activeId);
    if (activeEl) activeEl.className = 'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap bg-orange-500 text-white compact-btn';
    rLoadRecipes();
}

async function rLoadRecipes() {
    document.getElementById('rLoading').classList.remove('hidden');
    document.getElementById('rList').classList.add('hidden');
    document.getElementById('rEmpty').classList.add('hidden');

    const q = document.getElementById('rSearchInput').value.trim();
    let url = `api/recipes.php?action=list`;
    if (q) url += `&q=${encodeURIComponent(q)}`;
    if (rCategory) url += `&category=${rCategory}`;

    try {
        const data = await api(url);
        rRecipes = data.recipes || [];
        rRenderList();
    } catch (err) { showToast(err.message, 'error'); }
    finally { document.getElementById('rLoading').classList.add('hidden'); }
}

function rRenderList() {
    if (rRecipes.length === 0) { document.getElementById('rEmpty').classList.remove('hidden'); return; }

    const list = document.getElementById('rList');
    list.classList.remove('hidden');
    const catLabels = { appetizer: 'Appetizer', soup: 'Soup', salad: 'Salad', main_course: 'Main', side: 'Side', dessert: 'Dessert', beverage: 'Beverage', breakfast: 'Breakfast', lunch: 'Lunch', dinner: 'Dinner', snack: 'Snack', sauce: 'Sauce', bread: 'Bread', other: 'Other' };
    const diffColors = { easy: 'bg-green-100 text-green-700', medium: 'bg-amber-100 text-amber-700', hard: 'bg-red-100 text-red-700' };

    list.innerHTML = rRecipes.map(r => `
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
            <div class="flex items-center gap-3 px-4 py-3 cursor-pointer" onclick="rViewRecipe(${r.id})">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-gray-900 truncate">${r.name}</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] text-gray-400">${catLabels[r.category] || r.category}</span>
                        ${r.cuisine ? `<span class="text-[10px] text-gray-400">· ${r.cuisine}</span>` : ''}
                        <span class="text-[10px] text-gray-400">· ${r.ingredient_count || 0} ingredients</span>
                    </div>
                </div>
                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full ${diffColors[r.difficulty] || 'bg-gray-100 text-gray-600'}">${r.difficulty}</span>
                ${r.servings ? `<span class="text-[10px] text-gray-400">${r.servings} srv</span>` : ''}
                <button onclick="event.stopPropagation(); rDeleteRecipe(${r.id}, '${r.name.replace(/'/g, "\\'")}')" class="text-red-400 p-1 compact-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </button>
            </div>
        </div>
    `).join('');
}

// View recipe detail
async function rViewRecipe(id) {
    try {
        const data = await api(`api/recipes.php?action=get&id=${id}`);
        const r = data.recipe;
        const ings = r.ingredients || [];

        let html = `
            <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">${r.name}</h3>
                    <p class="text-[10px] text-gray-400">${r.category} ${r.cuisine ? '· ' + r.cuisine : ''} · ${r.servings} servings</p>
                </div>
                <button onclick="closeSheet()" class="p-1 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            </div>
            <div class="flex-1 overflow-y-auto px-5 py-3 scroll-touch">
                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Ingredients (${ings.length})</h4>
                ${ings.length === 0 ? '<p class="text-xs text-gray-400">No ingredients added yet</p>' : ''}
                ${ings.map(ing => `
                    <div class="flex items-center justify-between py-2 border-b border-gray-50">
                        <div>
                            <p class="text-sm text-gray-800">${ing.item_name}</p>
                            <p class="text-[10px] text-gray-400">${ing.is_primary ? 'Daily' : 'Weekly'}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-medium text-gray-900">${ing.qty} ${ing.uom}</span>
                            <button onclick="rRemoveIngredient(${ing.id}, ${id})" class="ml-2 text-red-400 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
                        </div>
                    </div>
                `).join('')}

                <!-- Add ingredient form -->
                <div class="mt-3 p-3 bg-orange-50 rounded-xl">
                    <h4 class="text-xs font-semibold text-orange-700 mb-2">Add Ingredient</h4>
                    <div class="relative mb-2">
                        <input type="text" id="rIngSearch" oninput="rSearchIngItems()" placeholder="Search item..."
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-200 compact-btn">
                        <div id="rIngResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto z-50"></div>
                    </div>
                    <div id="rIngFields" class="hidden">
                        <div class="flex items-center gap-2">
                            <input type="number" id="rIngQty" placeholder="Qty" class="w-20 border border-gray-200 rounded-lg px-2 py-1.5 text-sm compact-btn">
                            <span id="rIngUom" class="text-xs text-gray-500">kg</span>
                            <select id="rIngPrimary" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 compact-btn">
                                <option value="1">Daily</option>
                                <option value="0">Weekly</option>
                            </select>
                            <button onclick="rAddIngredient(${id})" class="bg-orange-500 text-white px-3 py-1.5 rounded-lg text-sm font-medium compact-btn">Add</button>
                        </div>
                    </div>
                </div>

                ${r.instructions ? `<h4 class="text-xs font-semibold text-gray-500 uppercase mt-4 mb-2">Instructions</h4><p class="text-sm text-gray-700 whitespace-pre-line">${r.instructions}</p>` : ''}
            </div>`;
        openSheet(html);
    } catch (err) { showToast(err.message, 'error'); }
}

// Ingredient search within recipe detail sheet
let rIngTimer = null;
let rIngSelected = null;

function rSearchIngItems() {
    clearTimeout(rIngTimer);
    const q = document.getElementById('rIngSearch').value.trim();
    if (q.length < 2) { document.getElementById('rIngResults').classList.add('hidden'); return; }
    rIngTimer = setTimeout(async () => {
        try {
            const data = await api(`api/recipes.php?action=search_items&q=${encodeURIComponent(q)}`);
            const results = document.getElementById('rIngResults');
            if (data.items.length === 0) { results.classList.add('hidden'); return; }
            results.classList.remove('hidden');
            results.innerHTML = data.items.map(item => `
                <button onclick='rSelectIngItem(${JSON.stringify(item).replace(/'/g, "&#39;")})' class="w-full text-left px-3 py-2 text-sm hover:bg-orange-50 border-b border-gray-50 compact-btn">${item.name} <span class="text-xs text-gray-400">${item.uom}</span></button>
            `).join('');
        } catch {}
    }, 300);
}

function rSelectIngItem(item) {
    rIngSelected = item;
    document.getElementById('rIngSearch').value = item.name;
    document.getElementById('rIngResults').classList.add('hidden');
    document.getElementById('rIngFields').classList.remove('hidden');
    document.getElementById('rIngUom').textContent = item.uom;
}

async function rAddIngredient(recipeId) {
    if (!rIngSelected) return;
    const qty = parseFloat(document.getElementById('rIngQty').value);
    if (!qty || qty <= 0) return showToast('Enter quantity', 'warning');
    const isPrimary = parseInt(document.getElementById('rIngPrimary').value);

    try {
        await api('api/recipes.php', { method: 'POST', body: { action: 'add_ingredient', recipe_id: recipeId, item_id: rIngSelected.id, item_name: rIngSelected.name, qty, uom: rIngSelected.uom, is_primary: isPrimary } });
        showToast('Ingredient added');
        closeSheet();
        rViewRecipe(recipeId);
    } catch (err) { showToast(err.message, 'error'); }
}

async function rRemoveIngredient(ingId, recipeId) {
    if (!confirm('Remove this ingredient?')) return;
    try {
        await api('api/recipes.php', { method: 'POST', body: { action: 'remove_ingredient', id: ingId } });
        showToast('Removed');
        closeSheet();
        rViewRecipe(recipeId);
    } catch (err) { showToast(err.message, 'error'); }
}

// Create recipe
function rShowCreate() {
    const categories = ['appetizer','soup','salad','main_course','side','dessert','beverage','breakfast','lunch','dinner','snack','sauce','bread'];
    let html = `
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">New Recipe</h3>
            <button onclick="closeSheet()" class="p-1 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 scroll-touch space-y-3">
            <input type="text" id="rNewName" placeholder="Recipe name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            <div class="flex gap-2">
                <select id="rNewCategory" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm compact-btn">
                    ${categories.map(c => `<option value="${c}" ${c === 'main_course' ? 'selected' : ''}>${c.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</option>`).join('')}
                </select>
                <select id="rNewDifficulty" class="w-28 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm compact-btn">
                    <option value="easy">Easy</option>
                    <option value="medium" selected>Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </div>
            <div class="flex gap-2">
                <input type="text" id="rNewCuisine" placeholder="Cuisine (optional)" class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                <input type="number" id="rNewServings" value="4" placeholder="Servings" class="w-24 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 compact-btn">
            </div>
            <textarea id="rNewInstructions" placeholder="Instructions (optional)" rows="3" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
            <button onclick="rSaveRecipe()" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-lg text-sm font-semibold transition">Save Recipe</button>
        </div>`;
    openSheet(html);
}

async function rSaveRecipe() {
    const name = document.getElementById('rNewName').value.trim();
    if (!name) return showToast('Enter recipe name', 'warning');

    try {
        await api('api/recipes.php', { method: 'POST', body: {
            action: 'save',
            name,
            category: document.getElementById('rNewCategory').value,
            difficulty: document.getElementById('rNewDifficulty').value,
            cuisine: document.getElementById('rNewCuisine').value || null,
            servings: parseInt(document.getElementById('rNewServings').value) || 4,
            instructions: document.getElementById('rNewInstructions').value || null,
        }});
        closeSheet();
        showToast('Recipe saved!');
        rLoadRecipes();
    } catch (err) { showToast(err.message, 'error'); }
}

async function rDeleteRecipe(id, name) {
    if (!confirm(`Delete "${name}"?`)) return;
    try {
        await api('api/recipes.php', { method: 'POST', body: { action: 'delete', id } });
        showToast('Recipe deleted');
        rLoadRecipes();
    } catch (err) { showToast(err.message, 'error'); }
}
</script>
