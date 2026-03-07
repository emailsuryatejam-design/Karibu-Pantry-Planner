<?php
/**
 * Karibu Pantry Planner — Admin: Weekly Set Menu Configuration
 * Configure which dishes (recipes) rotate on each day of the week per meal type.
 */
$user = currentUser();
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-bold text-gray-800">Weekly Set Menu</h2>
        <p class="text-xs text-gray-500 mt-0.5">Configure rotational dishes for each day</p>
    </div>
</div>

<!-- Day Tabs -->
<div class="flex gap-1.5 mb-3 overflow-x-auto pb-1" id="smDayTabs"></div>

<!-- Type Tabs (per day) -->
<div class="flex gap-1.5 mb-3 overflow-x-auto pb-1" id="smTypeTabs"></div>

<!-- Current Day/Type Dishes -->
<div id="smContent">
    <div class="text-center py-8 text-xs text-gray-400">Loading...</div>
</div>

<script>
const SM_DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
let smActiveDay = new Date().getDay(); // JS: 0=Sun, we need 1=Mon..7=Sun
smActiveDay = smActiveDay === 0 ? 7 : smActiveDay; // Convert to ISO
let smActiveType = '';
let smTypes = [];
let smWeek = {};   // { day: { type: [dishes] } }
let smSearchTimer = null;

smInit();

async function smInit() {
    await smLoadTypes();
    await smLoadWeek();
    smRenderDayTabs();
    smRenderTypeTabs();
    smRenderContent();
}

async function smLoadTypes() {
    try {
        const data = await cachedApi('api/requisition-types.php?action=list', 600000);
        smTypes = data.types || [];
        if (smTypes.length > 0 && !smActiveType) {
            smActiveType = smTypes[0].code;
        }
    } catch(e) {
        smTypes = [{code:'breakfast',name:'Breakfast'},{code:'lunch',name:'Lunch'},{code:'dinner',name:'Dinner'}];
        if (!smActiveType) smActiveType = 'breakfast';
    }
}

async function smLoadWeek() {
    try {
        const data = await api('api/set-menus.php?action=get_week');
        smWeek = data.week || {};
    } catch(e) {
        showToast('Failed to load set menu', 'error');
    }
}

function smRenderDayTabs() {
    const container = document.getElementById('smDayTabs');
    let html = '';
    SM_DAYS.forEach((name, i) => {
        const day = i + 1; // 1=Mon
        const isActive = day === smActiveDay;
        const dishCount = smDayDishCount(day);
        html += `<button onclick="smSelectDay(${day})"
            class="text-xs font-semibold px-3 py-1.5 rounded-full border whitespace-nowrap transition
            ${isActive ? 'bg-orange-500 text-white border-orange-500' : 'bg-white text-gray-600 border-gray-200 hover:bg-orange-50'}">
            ${name.substring(0,3)}
            ${dishCount > 0 ? `<span class="text-[9px] opacity-75 ml-0.5">(${dishCount})</span>` : ''}
        </button>`;
    });
    container.innerHTML = html;
}

function smRenderTypeTabs() {
    const container = document.getElementById('smTypeTabs');
    let html = '';
    smTypes.forEach(t => {
        const isActive = t.code === smActiveType;
        const dishes = (smWeek[smActiveDay] || {})[t.code] || [];
        html += `<button onclick="smSelectType('${t.code}')"
            class="text-xs font-medium px-3 py-1.5 rounded-full border whitespace-nowrap transition
            ${isActive ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-500 border-gray-200 hover:bg-blue-50'}">
            ${t.name}
            ${dishes.length > 0 ? `<span class="text-[9px] opacity-75 ml-0.5">(${dishes.length})</span>` : ''}
        </button>`;
    });
    container.innerHTML = html;
}

function smDayDishCount(day) {
    const dayData = smWeek[day] || {};
    let count = 0;
    for (const type in dayData) {
        count += dayData[type].length;
    }
    return count;
}

function smSelectDay(day) {
    smActiveDay = day;
    smRenderDayTabs();
    smRenderTypeTabs();
    smRenderContent();
}

function smSelectType(code) {
    smActiveType = code;
    smRenderTypeTabs();
    smRenderContent();
}

function smRenderContent() {
    const container = document.getElementById('smContent');
    const dishes = (smWeek[smActiveDay] || {})[smActiveType] || [];
    const dayName = SM_DAYS[smActiveDay - 1];
    const typeName = smTypes.find(t => t.code === smActiveType)?.name || smActiveType;

    let html = '';

    // Header with actions
    html += `<div class="flex items-center justify-between mb-3">
        <div>
            <span class="text-sm font-bold text-gray-800">${dayName}</span>
            <span class="text-sm text-gray-400 mx-1">&bull;</span>
            <span class="text-sm font-medium text-gray-600">${typeName}</span>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="smShowCopyDay()" class="text-[10px] font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg hover:bg-blue-100 transition" title="Copy from another day">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline -mt-0.5"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                Copy
            </button>
            ${dishes.length > 0 ? `<button onclick="smClearDay()" class="text-[10px] font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-lg hover:bg-red-100 transition">Clear</button>` : ''}
        </div>
    </div>`;

    // Add Dish button
    html += `<button onclick="smShowAddDish()" class="w-full mb-3 border-2 border-dashed border-gray-200 rounded-xl py-3 text-sm text-gray-400 font-medium hover:border-orange-300 hover:text-orange-500 transition flex items-center justify-center gap-1.5">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
        Add Dish
    </button>`;

    // Dish list
    if (dishes.length === 0) {
        html += `<div class="text-center py-8">
            <div class="text-2xl mb-2">&#127869;</div>
            <p class="text-sm text-gray-500">No dishes set for ${dayName} ${typeName}</p>
            <p class="text-[10px] text-gray-400 mt-1">Add dishes to auto-populate this meal's requisition</p>
        </div>`;
    } else {
        html += '<div class="space-y-2">';
        dishes.forEach((d, i) => {
            html += `<div class="bg-white border border-gray-200 rounded-xl px-3 py-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5 flex-1 min-w-0">
                        <div class="flex flex-col gap-0.5 shrink-0">
                            ${i > 0 ? `<button onclick="smMoveDish(${d.id}, 'up')" class="text-gray-300 hover:text-gray-500 transition"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m18 15-6-6-6 6"/></svg></button>` : '<div class="h-2.5"></div>'}
                            ${i < dishes.length - 1 ? `<button onclick="smMoveDish(${d.id}, 'down')" class="text-gray-300 hover:text-gray-500 transition"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg></button>` : '<div class="h-2.5"></div>'}
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" x2="18" y1="17" y2="17"/></svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-800 truncate">${d.recipe_name}</div>
                            <div class="text-[10px] text-gray-400">${d.cuisine || ''} ${d.ingredient_count || 0} ingredients &bull; serves ${d.recipe_servings || '?'}</div>
                        </div>
                    </div>
                    <button onclick="smRemoveDish(${d.id}, '${d.recipe_name.replace(/'/g, "\\'")}')" class="text-gray-300 hover:text-red-500 transition p-1 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>`;
        });
        html += '</div>';
    }

    container.innerHTML = html;
}

// ── Add Dish (bottom sheet with search) ──
function smShowAddDish() {
    const dayName = SM_DAYS[smActiveDay - 1];
    const typeName = smTypes.find(t => t.code === smActiveType)?.name || smActiveType;

    openSheet(`
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Add Dish to ${dayName} ${typeName}</h3>
            <p class="text-[10px] text-gray-400 mt-0.5">Search recipes by name</p>
        </div>
        <div class="px-5 py-4 space-y-3">
            <div class="relative">
                <input type="text" id="smDishSearch" placeholder="Search recipes..." oninput="smSearchRecipes()"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2.5 pl-9 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                <svg class="absolute left-3 top-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <div id="smSearchResults" class="max-h-64 overflow-y-auto"></div>
        </div>
    `);
    setTimeout(() => document.getElementById('smDishSearch')?.focus(), 300);
}

function smSearchRecipes() {
    clearTimeout(smSearchTimer);
    smSearchTimer = setTimeout(async () => {
        const q = document.getElementById('smDishSearch')?.value.trim();
        const container = document.getElementById('smSearchResults');
        if (!q || q.length < 2) { container.innerHTML = ''; return; }

        try {
            const data = await api(`api/set-menus.php?action=search_recipes&q=${encodeURIComponent(q)}`);
            const recipes = data.recipes || [];
            const existing = ((smWeek[smActiveDay] || {})[smActiveType] || []).map(d => d.recipe_id);

            if (recipes.length === 0) {
                container.innerHTML = '<p class="text-xs text-gray-400 py-3">No recipes found</p>';
                return;
            }

            container.innerHTML = recipes.map(r => {
                const already = existing.includes(parseInt(r.id));
                return `<button onclick="smAddDish(${r.id}, '${r.name.replace(/'/g, "\\'")}')" class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-orange-50 transition text-left border-b border-gray-50 last:border-0 rounded-lg ${already ? 'opacity-40' : ''}" ${already ? 'disabled' : ''}>
                    <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" x2="18" y1="17" y2="17"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-800 truncate">${r.name}</div>
                        <div class="text-[10px] text-gray-400">${r.cuisine || ''} ${r.ingredient_count} ingredients &bull; serves ${r.servings}</div>
                    </div>
                    ${already ? '<span class="text-[10px] text-orange-500 font-semibold shrink-0">Added</span>' : '<span class="text-orange-500 shrink-0"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg></span>'}
                </button>`;
            }).join('');
        } catch(e) {
            container.innerHTML = '<p class="text-xs text-red-400 py-2">Search failed</p>';
        }
    }, 300);
}

async function smAddDish(recipeId, recipeName) {
    try {
        await api('api/set-menus.php?action=add_dish', {
            method: 'POST',
            body: JSON.stringify({
                day_of_week: smActiveDay,
                type_code: smActiveType,
                recipe_id: recipeId,
                recipe_name: recipeName
            })
        });
        showToast(`${recipeName} added`, 'success');
        closeSheet();
        await smLoadWeek();
        smRenderDayTabs();
        smRenderTypeTabs();
        smRenderContent();
    } catch(e) {
        showToast(e.message || 'Failed to add', 'error');
    }
}

async function smRemoveDish(id, name) {
    if (!confirm(`Remove "${name}" from this day?`)) return;
    try {
        await api('api/set-menus.php?action=remove_dish', {
            method: 'POST', body: JSON.stringify({ id })
        });
        showToast('Dish removed', 'info');
        await smLoadWeek();
        smRenderDayTabs();
        smRenderTypeTabs();
        smRenderContent();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}

async function smMoveDish(id, direction) {
    const dishes = (smWeek[smActiveDay] || {})[smActiveType] || [];
    const idx = dishes.findIndex(d => d.id == id);
    if (idx === -1) return;
    const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
    if (swapIdx < 0 || swapIdx >= dishes.length) return;

    const items = dishes.map((d, i) => ({
        id: d.id,
        sort_order: i === idx ? dishes[swapIdx].sort_order : (i === swapIdx ? dishes[idx].sort_order : d.sort_order)
    }));

    try {
        await api('api/set-menus.php?action=reorder', {
            method: 'POST', body: JSON.stringify({ items })
        });
        await smLoadWeek();
        smRenderContent();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}

function smShowCopyDay() {
    const dayName = SM_DAYS[smActiveDay - 1];
    const typeName = smTypes.find(t => t.code === smActiveType)?.name || smActiveType;

    let html = `<div class="p-4">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Copy Dishes</h3>
        <p class="text-[10px] text-gray-400 mb-3">Copy ${typeName} dishes from another day to ${dayName}</p>
        <div class="space-y-2">`;

    SM_DAYS.forEach((name, i) => {
        const day = i + 1;
        if (day === smActiveDay) return;
        const count = ((smWeek[day] || {})[smActiveType] || []).length;
        html += `<button onclick="smDoCopy(${day})" class="w-full flex items-center justify-between px-3 py-2.5 bg-gray-50 rounded-xl hover:bg-blue-50 transition ${count === 0 ? 'opacity-40' : ''}" ${count === 0 ? 'disabled' : ''}>
            <span class="text-sm font-medium text-gray-700">${name}</span>
            <span class="text-[10px] text-gray-400">${count} dish${count !== 1 ? 'es' : ''}</span>
        </button>`;
    });

    html += '</div></div>';
    openSheet(html);
}

async function smDoCopy(fromDay) {
    try {
        await api('api/set-menus.php?action=copy_day', {
            method: 'POST',
            body: JSON.stringify({
                from_day: fromDay,
                to_day: smActiveDay,
                type_code: smActiveType
            })
        });
        showToast('Dishes copied', 'success');
        closeSheet();
        await smLoadWeek();
        smRenderDayTabs();
        smRenderTypeTabs();
        smRenderContent();
    } catch(e) {
        showToast(e.message || 'Failed to copy', 'error');
    }
}

async function smClearDay() {
    const dayName = SM_DAYS[smActiveDay - 1];
    const typeName = smTypes.find(t => t.code === smActiveType)?.name || smActiveType;
    if (!confirm(`Clear all dishes for ${dayName} ${typeName}?`)) return;

    try {
        await api('api/set-menus.php?action=clear_day', {
            method: 'POST',
            body: JSON.stringify({
                day_of_week: smActiveDay,
                type_code: smActiveType
            })
        });
        showToast('Cleared', 'info');
        await smLoadWeek();
        smRenderDayTabs();
        smRenderTypeTabs();
        smRenderContent();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}
</script>
