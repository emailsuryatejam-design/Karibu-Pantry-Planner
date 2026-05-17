<?php
/**
 * Karibu Pantry Planner — Admin Meal Types
 */
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Meal Types</h1>
        <p class="text-xs text-gray-400 mt-0.5">Configure meal types that chefs can select</p>
    </div>
    <button onclick="artShowCreate()" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 compact-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add Type
    </button>
</div>

<div id="artList" class="space-y-2">
    <div class="text-center py-8 text-xs text-gray-400">Loading...</div>
</div>

<script>
let artTypes = [];

artLoad();

async function artLoad() {
    const container = document.getElementById('artList');
    try {
        const data = await api('api/requisition-types.php?action=list_all');
        artTypes = data.types || [];
        artRender();
    } catch(e) {
        container.innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

function artRender() {
    const container = document.getElementById('artList');
    if (artTypes.length === 0) {
        container.innerHTML = '<div class="text-center py-8"><p class="text-xs text-gray-400">No meal types configured</p><p class="text-[10px] text-gray-300 mt-1">Add meal types for chefs to select when creating requisitions</p></div>';
        return;
    }

    let html = '';
    artTypes.forEach((t, i) => {
        const isActive = t.is_active == 1;
        html += `<div class="bg-white border ${isActive ? 'border-gray-200' : 'border-red-200 bg-red-50/50'} rounded-xl px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex flex-col gap-0.5">
                        ${i > 0 ? `<button onclick="artMove(${t.id}, 'up')" class="text-gray-300 hover:text-gray-500 transition"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m18 15-6-6-6 6"/></svg></button>` : '<div class="h-3"></div>'}
                        ${i < artTypes.length - 1 ? `<button onclick="artMove(${t.id}, 'down')" class="text-gray-300 hover:text-gray-500 transition"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg></button>` : '<div class="h-3"></div>'}
                    </div>
                    <div>
                        <div class="text-sm font-semibold ${isActive ? 'text-gray-800' : 'text-gray-400'}">${t.name}</div>
                        <div class="text-[10px] text-gray-400">Code: ${t.code}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${isActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">${isActive ? 'Active' : 'Inactive'}</span>
                    <button onclick="artShowEdit(${t.id})" class="p-1.5 text-gray-400 hover:text-blue-600 rounded transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                    </button>
                    <button onclick="artToggle(${t.id}, ${isActive ? 1 : 0})" class="p-1.5 ${isActive ? 'text-gray-400 hover:text-red-500' : 'text-green-400 hover:text-green-600'} rounded transition" title="${isActive ? 'Disable' : 'Enable'}">
                        ${isActive
                            ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4" y1="4" x2="20" y2="20"/></svg>'
                            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>'
                        }
                    </button>
                </div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function artShowCreate() {
    openSheet(`
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Add Meal Type</h3>
            <button onclick="closeSheet()" class="p-1"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Name *</label>
                <input type="text" id="artName" placeholder="e.g. Breakfast"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Code <span class="text-gray-400 normal-case font-normal">(auto-generated if empty)</span></label>
                <input type="text" id="artCode" placeholder="e.g. breakfast"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <button onclick="artCreate()" id="artCreateBtn"
                class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition mt-2">
                Create Meal Type
            </button>
        </div>
    `);
}

async function artCreate() {
    const name = document.getElementById('artName')?.value.trim();
    const code = document.getElementById('artCode')?.value.trim();
    if (!name) { showToast('Name is required', 'warning'); return; }

    const btn = document.getElementById('artCreateBtn');
    setLoading(btn, true);

    try {
        await api('api/requisition-types.php?action=save', {
            method: 'POST', body: { name, code }
        });
        showToast('Meal type created', 'success');
        closeSheet();
        try { sessionStorage.removeItem('api_api/requisition-types.php?action=list'); } catch {}
        artLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
        setLoading(btn, false);
    }
}

function artShowEdit(id) {
    const t = artTypes.find(x => x.id == id);
    if (!t) return;

    openSheet(`
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Edit Meal Type</h3>
            <button onclick="closeSheet()" class="p-1"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Name *</label>
                <input type="text" id="artEditName" value="${t.name}"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Code</label>
                <input type="text" id="artEditCode" value="${t.code}"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-gray-700">Active</span>
                <label class="relative inline-flex cursor-pointer">
                    <input type="checkbox" id="artEditActive" ${t.is_active == 1 ? 'checked' : ''} class="sr-only peer">
                    <div class="w-9 h-5 bg-gray-200 peer-checked:bg-green-500 rounded-full peer-focus:ring-2 peer-focus:ring-green-300 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
            </div>
            <button onclick="artUpdate(${t.id})" id="artUpdateBtn"
                class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl transition">
                Save Changes
            </button>
        </div>
    `);
}

async function artUpdate(id) {
    const name = document.getElementById('artEditName')?.value.trim();
    const code = document.getElementById('artEditCode')?.value.trim();
    const isActive = document.getElementById('artEditActive')?.checked;
    if (!name) { showToast('Name is required', 'warning'); return; }

    const btn = document.getElementById('artUpdateBtn');
    setLoading(btn, true);

    try {
        const t = artTypes.find(x => x.id == id);
        await api('api/requisition-types.php?action=save', {
            method: 'POST', body: { id, name, code, sort_order: t?.sort_order || 0, is_active: isActive ? 1 : 0 }
        });
        showToast('Meal type updated', 'success');
        closeSheet();
        try { sessionStorage.removeItem('api_api/requisition-types.php?action=list'); } catch {}
        artLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
        setLoading(btn, false);
    }
}

async function artToggle(id, isActive) {
    try {
        await api('api/requisition-types.php?action=toggle_active', {
            method: 'POST', body: { id }
        });
        showToast(isActive ? 'Meal type disabled' : 'Meal type enabled');
        try { sessionStorage.removeItem('api_api/requisition-types.php?action=list'); } catch {}
        artLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}

async function artMove(id, direction) {
    const idx = artTypes.findIndex(x => x.id == id);
    if (idx === -1) return;
    const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
    if (swapIdx < 0 || swapIdx >= artTypes.length) return;

    const items = artTypes.map((t, i) => ({
        id: t.id,
        sort_order: i === idx ? artTypes[swapIdx].sort_order : (i === swapIdx ? artTypes[idx].sort_order : t.sort_order)
    }));

    try {
        await api('api/requisition-types.php?action=reorder', {
            method: 'POST', body: { items }
        });
        try { sessionStorage.removeItem('api_api/requisition-types.php?action=list'); } catch {}
        artLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}
</script>
