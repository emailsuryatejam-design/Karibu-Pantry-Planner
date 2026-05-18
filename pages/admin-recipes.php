<?php if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; } ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Recipe Library</h1>
        <p class="text-xs text-gray-400 mt-0.5" id="arCount">Loading...</p>
    </div>
    <div class="relative">
        <input type="text" id="arSearchInput" placeholder="Search recipes…" oninput="arSearch=this.value;arRender()"
               class="border border-gray-200 rounded-xl px-3 py-2 pl-8 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-slate-300 w-44">
        <svg class="absolute left-2.5 top-2.5 text-gray-300" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    </div>
</div>

<!-- Filter row -->
<div class="flex flex-wrap gap-2 mb-3">
    <!-- Chef dropdown -->
    <select id="arChefSelect" onchange="arChefId=parseInt(this.value)||0;arRender()"
            class="bg-gray-100 text-gray-600 text-xs font-semibold rounded-full px-3 py-1.5 border-0 focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer">
        <option value="0">All Chefs</option>
    </select>

    <!-- Category pills -->
    <div class="flex gap-1.5 flex-wrap">
        <button onclick="arSetCategory('')"            id="arCatAll"         class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 text-white transition">All</button>
        <button onclick="arSetCategory('main_course')" id="arCatMain_course" class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Main Course</button>
        <button onclick="arSetCategory('breakfast')"   id="arCatBreakfast"   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Breakfast</button>
        <button onclick="arSetCategory('lunch')"       id="arCatLunch"       class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Lunch</button>
        <button onclick="arSetCategory('dinner')"      id="arCatDinner"      class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Dinner</button>
        <button onclick="arSetCategory('dessert')"     id="arCatDessert"     class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Dessert</button>
        <button onclick="arSetCategory('other')"       id="arCatOther"       class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Other</button>
    </div>

    <!-- Defaults toggle -->
    <button onclick="arDefaultOnly=!arDefaultOnly;arUpdateDefaultBtn();arRender()" id="arDefaultBtn"
            class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">
        ⭐ Defaults only
    </button>
</div>

<div id="arList" class="space-y-2">
    <div class="text-center py-10 text-gray-300 text-sm">Loading…</div>
</div>

<script>
let arRecipes    = [];
let arChefs      = [];
let arSearch     = '';
let arCategory   = '';
let arChefId     = 0;
let arDefaultOnly = false;
let arExpanded   = {};   // id -> true when expanded
let arIngredients = {};  // id -> ingredients array (cached)

const arCategoryColors = {
    breakfast:   'bg-blue-100 text-blue-700',
    lunch:       'bg-blue-100 text-blue-700',
    dinner:      'bg-blue-100 text-blue-700',
    main_course: 'bg-orange-100 text-orange-700',
    dessert:     'bg-pink-100 text-pink-700',
    appetizer:   'bg-green-100 text-green-700',
    soup:        'bg-green-100 text-green-700',
    salad:       'bg-green-100 text-green-700',
};
const arCategoryLabel = {
    breakfast:   'Breakfast',
    lunch:       'Lunch',
    dinner:      'Dinner',
    main_course: 'Main Course',
    dessert:     'Dessert',
    appetizer:   'Appetizer',
    soup:        'Soup',
    salad:       'Salad',
    other:       'Other',
};

// ── Load ──────────────────────────────────────────────────────────────────────
async function arLoad() {
    try {
        const data = await api('api/recipes.php?action=admin_list');
        arRecipes = data.recipes || [];

        // Build unique chefs list
        const seen = new Set();
        arChefs = [];
        for (const r of arRecipes) {
            if (r.chef_id && !seen.has(r.chef_id)) {
                seen.add(r.chef_id);
                arChefs.push({ id: r.chef_id, name: r.chef_name || 'Unknown' });
            }
        }
        arChefs.sort((a, b) => a.name.localeCompare(b.name));

        // Populate chef dropdown
        const sel = document.getElementById('arChefSelect');
        sel.innerHTML = '<option value="0">All Chefs</option>' +
            arChefs.map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('');

        arRender();
    } catch (e) {
        showToast('Failed to load recipes', 'error');
        document.getElementById('arList').innerHTML = '<div class="text-center py-10 text-red-400 text-sm">Failed to load</div>';
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
function arRender() {
    const q = arSearch.toLowerCase();
    let filtered = arRecipes.filter(r => {
        if (arChefId && r.chef_id != arChefId) return false;
        if (arCategory && r.category !== arCategory) return false;
        if (arDefaultOnly && !r.is_default) return false;
        if (q && !r.name.toLowerCase().includes(q) && !(r.cuisine || '').toLowerCase().includes(q) && !(r.chef_name || '').toLowerCase().includes(q)) return false;
        return true;
    });

    const total    = arRecipes.length;
    const defaults = arRecipes.filter(r => r.is_default).length;
    document.getElementById('arCount').textContent =
        `${filtered.length} recipe${filtered.length !== 1 ? 's' : ''} shown · ${total} total · ${defaults} default${defaults !== 1 ? 's' : ''}`;

    if (!filtered.length) {
        document.getElementById('arList').innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No recipes match</div>';
        return;
    }

    document.getElementById('arList').innerHTML = filtered.map(r => {
        const catColor = arCategoryColors[r.category] || 'bg-gray-100 text-gray-600';
        const catLabel = arCategoryLabel[r.category] || (r.category || 'Other');
        const initial  = (r.chef_name || '?').charAt(0).toUpperCase();
        const isExp    = arExpanded[r.id];
        const cached   = arIngredients[r.id];

        let ingHtml = '';
        if (isExp) {
            if (cached) {
                ingHtml = arBuildIngredientList(cached);
            } else {
                ingHtml = `<div id="arIng${r.id}" class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-400">Loading ingredients…</div>`;
            }
        }

        return `
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3" id="arCard${r.id}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-800">${escHtml(r.name)}</span>
                        ${r.is_default ? `<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700" id="arDefaultBadge${r.id}">⭐ Default</span>` : `<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 hidden" id="arDefaultBadge${r.id}">⭐ Default</span>`}
                        ${r.is_packed ? '<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">📦 Packed</span>' : ''}
                    </div>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center shrink-0">
                            <span class="text-[9px] font-bold text-slate-600">${escHtml(initial)}</span>
                        </div>
                        <span class="text-[11px] text-gray-500">${escHtml(r.chef_name || 'Unknown')}${r.kitchen_name ? ' · ' + escHtml(r.kitchen_name) : ''}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${catColor}">${escHtml(catLabel)}</span>
                        ${r.cuisine ? `<span class="text-[10px] text-gray-400">${escHtml(r.cuisine)}</span>` : ''}
                        <span class="text-[10px] text-gray-400">${r.ingredient_count} ingredient${r.ingredient_count != 1 ? 's' : ''}</span>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1.5 shrink-0">
                    <button onclick="arToggleDefault(${r.id}, ${r.is_default ? 1 : 0})"
                            class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border ${r.is_default ? 'border-indigo-200 text-indigo-600 hover:bg-indigo-50' : 'border-gray-200 text-gray-500 hover:bg-gray-50'} transition"
                            id="arDefaultBtn${r.id}">
                        ${r.is_default ? 'Unset Default' : 'Set Default'}
                    </button>
                    <button onclick="arExpand(${r.id})"
                            class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition"
                            id="arExpandBtn${r.id}">
                        ${isExp ? 'Collapse ▲' : 'Expand ▼'}
                    </button>
                </div>
            </div>
            ${ingHtml ? `<div id="arIngWrap${r.id}">${ingHtml}</div>` : `<div id="arIngWrap${r.id}"></div>`}
        </div>`;
    }).join('');

    // Trigger lazy-loads for expanded cards with no cached data
    filtered.forEach(r => {
        if (arExpanded[r.id] && !arIngredients[r.id]) {
            arFetchIngredients(r.id);
        }
    });
}

function arBuildIngredientList(ingredients) {
    if (!ingredients.length) return '<div class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-400 italic">No ingredients listed</div>';
    const primary = ingredients.filter(i => i.is_primary);
    const staple  = ingredients.filter(i => !i.is_primary);
    let html = '<div class="mt-2 pt-2 border-t border-gray-100">';
    if (primary.length) {
        html += '<div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Primary Ingredients</div>';
        html += '<div class="flex flex-wrap gap-1.5">' + primary.map(i =>
            `<span class="text-[11px] px-2 py-0.5 bg-orange-50 text-orange-700 rounded-full">${escHtml(i.item_name)}${i.qty ? ' · ' + i.qty + (i.uom ? ' ' + escHtml(i.uom) : '') : ''}</span>`
        ).join('') + '</div>';
    }
    if (staple.length) {
        if (primary.length) html += '<div class="mt-1.5"></div>';
        html += '<div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Staples / Pantry</div>';
        html += '<div class="flex flex-wrap gap-1.5">' + staple.map(i =>
            `<span class="text-[11px] px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full">${escHtml(i.item_name)}${i.qty ? ' · ' + i.qty + (i.uom ? ' ' + escHtml(i.uom) : '') : ''}</span>`
        ).join('') + '</div>';
    }
    html += '</div>';
    return html;
}

// ── Expand / collapse ─────────────────────────────────────────────────────────
async function arExpand(id) {
    arExpanded[id] = !arExpanded[id];

    const wrap    = document.getElementById(`arIngWrap${id}`);
    const btn     = document.getElementById(`arExpandBtn${id}`);

    if (!arExpanded[id]) {
        if (wrap) wrap.innerHTML = '';
        if (btn) btn.textContent = 'Expand ▼';
        return;
    }

    if (btn) btn.textContent = 'Collapse ▲';

    if (arIngredients[id]) {
        if (wrap) wrap.innerHTML = arBuildIngredientList(arIngredients[id]);
        return;
    }

    if (wrap) wrap.innerHTML = '<div class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-400">Loading ingredients…</div>';
    arFetchIngredients(id);
}

async function arFetchIngredients(id) {
    try {
        const data = await api(`api/recipes.php?action=get&id=${id}`);
        arIngredients[id] = data.recipe?.ingredients || [];
        if (arExpanded[id]) {
            const wrap = document.getElementById(`arIngWrap${id}`);
            if (wrap) wrap.innerHTML = arBuildIngredientList(arIngredients[id]);
        }
    } catch (e) {
        const wrap = document.getElementById(`arIngWrap${id}`);
        if (wrap) wrap.innerHTML = '<div class="mt-2 pt-2 border-t border-gray-100 text-xs text-red-400">Failed to load ingredients</div>';
    }
}

// ── Toggle default ────────────────────────────────────────────────────────────
async function arToggleDefault(id, currentVal) {
    const btn   = document.getElementById(`arDefaultBtn${id}`);
    const badge = document.getElementById(`arDefaultBadge${id}`);
    if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }

    try {
        const data = await api('api/recipes.php', { method: 'POST', body: { action: 'toggle_default', recipe_id: id } });
        const isDefault = data.is_default;

        // Update in-memory
        const rec = arRecipes.find(r => r.id === id);
        if (rec) rec.is_default = isDefault;

        // Update badge in-place
        if (badge) {
            if (isDefault) {
                badge.textContent = '⭐ Default';
                badge.className = 'text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700';
                badge.classList.remove('hidden');
            } else {
                badge.className = 'text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 hidden';
            }
        }

        // Update toggle button
        if (btn) {
            btn.textContent = isDefault ? 'Unset Default' : 'Set Default';
            btn.className = btn.className.replace(
                isDefault ? /border-gray-200 text-gray-500 hover:bg-gray-50/ : /border-indigo-200 text-indigo-600 hover:bg-indigo-50/,
                isDefault ? 'border-indigo-200 text-indigo-600 hover:bg-indigo-50' : 'border-gray-200 text-gray-500 hover:bg-gray-50'
            );
        }

        // Update count line
        const defaults = arRecipes.filter(r => r.is_default).length;
        const countEl  = document.getElementById('arCount');
        if (countEl) {
            const parts = countEl.textContent.split('·');
            if (parts.length >= 3) {
                parts[2] = ` ${defaults} default${defaults !== 1 ? 's' : ''}`;
                countEl.textContent = parts.join('·');
            }
        }

        showToast(isDefault ? 'Marked as default' : 'Removed from defaults', 'success');
    } catch (e) {
        showToast(e.message || 'Failed to update', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.style.opacity = ''; }
    }
}

// ── Category filter helpers ───────────────────────────────────────────────────
function arSetCategory(cat) {
    arCategory = cat;
    document.querySelectorAll('[id^="arCat"]').forEach(b => {
        b.className = b.className.replace('bg-slate-800 text-white', 'bg-gray-100 text-gray-600');
    });
    const idMap = {
        '': 'arCatAll', main_course: 'arCatMain_course', breakfast: 'arCatBreakfast',
        lunch: 'arCatLunch', dinner: 'arCatDinner', dessert: 'arCatDessert', other: 'arCatOther'
    };
    const active = document.getElementById(idMap[cat] || 'arCatAll');
    if (active) active.className = active.className.replace('bg-gray-100 text-gray-600', 'bg-slate-800 text-white');
    arRender();
}

function arUpdateDefaultBtn() {
    const btn = document.getElementById('arDefaultBtn');
    if (!btn) return;
    if (arDefaultOnly) {
        btn.className = btn.className.replace('bg-gray-100 text-gray-600', 'bg-slate-800 text-white');
    } else {
        btn.className = btn.className.replace('bg-slate-800 text-white', 'bg-gray-100 text-gray-600');
    }
}

// ── Boot ──────────────────────────────────────────────────────────────────────
arLoad();
</script>
