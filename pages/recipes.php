<!-- Recipes Page — Master recipe management -->
<div id="recipesApp">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-600"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                Recipes
            </h1>
            <p class="text-xs text-gray-500 mt-0.5" id="rCount">Manage kitchen recipes</p>
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
let rExpandedId = null;

rLoadRecipes();

function rSearch() {
    clearTimeout(rSearchTimer);
    rSearchTimer = setTimeout(() => rLoadRecipes(), 300);
}

function rFilterCat(cat) {
    rCategory = cat;
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
        document.getElementById('rCount').textContent = `${rRecipes.length} recipe${rRecipes.length !== 1 ? 's' : ''}`;
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
            <div class="flex items-center gap-3 px-4 py-3 cursor-pointer" onclick="rToggleExpand(${r.id})">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-gray-900 truncate">${r.name}</p>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="text-[10px] text-orange-600 bg-orange-50 px-1.5 py-0.5 rounded-full">${catLabels[r.category] || r.category}</span>
                        ${r.cuisine ? `<span class="text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full">${r.cuisine}</span>` : ''}
                        <span class="text-[10px] text-gray-400">${r.ingredient_count || 0} ing</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full ${diffColors[r.difficulty] || 'bg-gray-100 text-gray-600'}">${r.difficulty || 'medium'}</span>
                    ${r.servings ? `<span class="text-[10px] text-gray-400">${r.servings} srv</span>` : ''}
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-300 transition-transform ${rExpandedId === r.id ? 'rotate-180' : ''}"><path d="m6 9 6 6 6-6"/></svg>
                </div>
            </div>
            ${rExpandedId === r.id ? `<div id="rDetail_${r.id}"><div class="border-t border-gray-100 px-4 py-3 text-center"><svg class="animate-spin text-orange-400 mx-auto" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></div></div>` : ''}
        </div>
    `).join('');

    // Load detail if expanded
    if (rExpandedId) rLoadDetail(rExpandedId);
}

async function rToggleExpand(id) {
    if (rExpandedId === id) {
        rExpandedId = null;
        rRenderList();
        return;
    }
    rExpandedId = id;
    rRenderList();
}

async function rLoadDetail(id) {
    try {
        const data = await api(`api/recipes.php?action=get&id=${id}`);
        const r = data.recipe;
        const ings = r.ingredients || [];
        const diffColors = { easy: 'text-green-700', medium: 'text-amber-700', hard: 'text-red-700' };

        let html = '<div class="border-t border-gray-100">';

        // Meta row (prep, cook, difficulty, servings)
        html += `<div class="flex items-center gap-3 px-4 py-2 bg-gray-50/50 text-[10px] text-gray-500 flex-wrap">`;
        if (r.servings) html += `<span class="flex items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> ${r.servings} servings</span>`;
        if (r.prep_time) html += `<span>Prep: ${r.prep_time}min</span>`;
        if (r.cook_time) html += `<span>Cook: ${r.cook_time}min</span>`;
        html += `<span class="${diffColors[r.difficulty] || ''} font-medium">${(r.difficulty || 'medium').charAt(0).toUpperCase() + (r.difficulty || 'medium').slice(1)}</span>`;
        html += `</div>`;

        // Description
        if (r.notes) {
            html += `<div class="px-4 py-2 border-t border-gray-50"><p class="text-xs text-gray-600 italic">${r.notes}</p></div>`;
        }

        // Ingredients
        html += `<div class="px-4 py-2 border-t border-gray-50">`;
        html += `<h4 class="text-[10px] font-semibold text-gray-500 uppercase mb-2">Ingredients (${ings.length})</h4>`;
        if (ings.length === 0) {
            html += `<p class="text-xs text-gray-400">No ingredients yet</p>`;
        } else {
            html += `<div class="grid grid-cols-2 gap-x-4 gap-y-1">`;
            ings.forEach(ing => {
                html += `<div class="flex items-center gap-2 py-1">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 ${ing.is_primary ? 'bg-orange-400' : 'bg-gray-300'}"></span>
                    <span class="text-xs text-gray-800 truncate flex-1">${ing.item_name}</span>
                    <span class="text-[10px] text-gray-500 shrink-0">${ing.qty} ${ing.uom}</span>
                </div>`;
            });
            html += `</div>`;
        }
        html += `</div>`;

        // Instructions
        if (r.instructions) {
            html += `<div class="px-4 py-2 border-t border-gray-50">`;
            html += `<h4 class="text-[10px] font-semibold text-gray-500 uppercase mb-2">Instructions</h4>`;
            const steps = r.instructions.split('\n').filter(s => s.trim());
            steps.forEach((step, i) => {
                html += `<div class="flex gap-2 mb-2">
                    <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-700 text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">${i + 1}</span>
                    <p class="text-xs text-gray-700 flex-1">${step.trim()}</p>
                </div>`;
            });
            html += `</div>`;
        }

        // Actions
        html += `<div class="flex items-center gap-2 px-4 py-3 border-t border-gray-100 bg-gray-50/50">
            <button onclick="rShowEdit(${id})" class="flex-1 text-xs text-orange-700 font-semibold bg-orange-50 py-2 rounded-lg compact-btn">Edit</button>
            <button onclick="rDeleteRecipe(${id}, '${r.name.replace(/'/g, "\\'")}')" class="text-xs text-red-600 font-medium bg-red-50 px-4 py-2 rounded-lg compact-btn">Delete</button>
        </div>`;

        html += '</div>';

        const detail = document.getElementById(`rDetail_${id}`);
        if (detail) detail.innerHTML = html;
    } catch (err) { showToast(err.message, 'error'); }
}

// ── Create / Edit Recipe (bottom sheet) ──
function rShowCreate() { rOpenForm(null); }

async function rShowEdit(id) {
    try {
        const data = await api(`api/recipes.php?action=get&id=${id}`);
        rOpenForm(data.recipe);
    } catch (err) { showToast(err.message, 'error'); }
}

function rOpenForm(recipe) {
    const isEdit = !!recipe;
    const categories = ['appetizer','soup','salad','main_course','side','dessert','beverage','breakfast','lunch','dinner','snack','sauce','bread'];

    let html = `
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">${isEdit ? 'Edit Recipe' : 'New Recipe'}</h3>
            <button onclick="closeSheet()" class="p-1 compact-btn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 scroll-touch space-y-3">
            <input type="text" id="rFormName" value="${isEdit ? recipe.name : ''}" placeholder="Recipe name" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            <div class="flex gap-2">
                <select id="rFormCategory" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm compact-btn">
                    ${categories.map(c => `<option value="${c}" ${(isEdit ? recipe.category : 'main_course') === c ? 'selected' : ''}>${c.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</option>`).join('')}
                </select>
                <select id="rFormDifficulty" class="w-28 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm compact-btn">
                    <option value="easy" ${(isEdit && recipe.difficulty === 'easy') ? 'selected' : ''}>Easy</option>
                    <option value="medium" ${(!isEdit || recipe.difficulty === 'medium') ? 'selected' : ''}>Medium</option>
                    <option value="hard" ${(isEdit && recipe.difficulty === 'hard') ? 'selected' : ''}>Hard</option>
                </select>
            </div>
            <div class="flex gap-2">
                <input type="text" id="rFormCuisine" value="${isEdit && recipe.cuisine ? recipe.cuisine : ''}" placeholder="Cuisine (optional)" class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                <input type="number" id="rFormServings" value="${isEdit ? recipe.servings || 4 : 4}" placeholder="Servings" class="w-24 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 compact-btn">
            </div>
            <div class="flex gap-2">
                <input type="number" id="rFormPrep" value="${isEdit && recipe.prep_time ? recipe.prep_time : ''}" placeholder="Prep min" class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 compact-btn">
                <input type="number" id="rFormCook" value="${isEdit && recipe.cook_time ? recipe.cook_time : ''}" placeholder="Cook min" class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 compact-btn">
            </div>
            <textarea id="rFormNotes" placeholder="Description / notes (optional)" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">${isEdit && recipe.notes ? recipe.notes : ''}</textarea>
            <textarea id="rFormInstructions" placeholder="Instructions (one step per line)" rows="4" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">${isEdit && recipe.instructions ? recipe.instructions : ''}</textarea>
            <button onclick="rSaveRecipe(${isEdit ? recipe.id : 'null'})" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-lg text-sm font-semibold transition">Save Recipe</button>
        </div>`;
    openSheet(html);
}

async function rSaveRecipe(editId) {
    const name = document.getElementById('rFormName').value.trim();
    if (!name) return showToast('Enter recipe name', 'warning');

    try {
        await api('api/recipes.php', { method: 'POST', body: {
            action: 'save',
            id: editId,
            name,
            category: document.getElementById('rFormCategory').value,
            difficulty: document.getElementById('rFormDifficulty').value,
            cuisine: document.getElementById('rFormCuisine').value || null,
            servings: parseInt(document.getElementById('rFormServings').value) || 4,
            prep_time: parseInt(document.getElementById('rFormPrep').value) || null,
            cook_time: parseInt(document.getElementById('rFormCook').value) || null,
            notes: document.getElementById('rFormNotes').value || null,
            instructions: document.getElementById('rFormInstructions').value || null,
        }});
        closeSheet();
        showToast('Recipe saved!');
        rExpandedId = null;
        rLoadRecipes();
    } catch (err) { showToast(err.message, 'error'); }
}

async function rDeleteRecipe(id, name) {
    if (!confirm(`Delete "${name}"? Existing menu plans won't be affected.`)) return;
    try {
        await api('api/recipes.php', { method: 'POST', body: { action: 'delete', id } });
        showToast('Recipe deleted');
        rExpandedId = null;
        rLoadRecipes();
    } catch (err) { showToast(err.message, 'error'); }
}
</script>
