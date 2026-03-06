<?php
/**
 * Karibu Pantry Planner — Admin Requisition Types
 */
$user = currentUser();
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Requisition Types</h2>
<p class="text-xs text-gray-500 mb-4">Configure meal/order types that chefs can select</p>

<!-- Add New -->
<button onclick="artShowCreate()" class="mb-4 px-4 py-2 bg-orange-500 text-white text-sm font-semibold rounded-xl hover:bg-orange-600 transition">
    + Add Type
</button>

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
        container.innerHTML = '<div class="text-center py-8"><p class="text-xs text-gray-400">No requisition types configured</p><p class="text-[10px] text-gray-300 mt-1">Add types for chefs to select when creating requisitions</p></div>';
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
                    <button onclick="artToggle(${t.id})" class="p-1.5 ${isActive ? 'text-gray-400 hover:text-red-500' : 'text-green-400 hover:text-green-600'} rounded transition" title="${isActive ? 'Disable' : 'Enable'}">
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
    openSheet(`<div class="p-4">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Add Requisition Type</h3>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Name</label>
                <input type="text" id="artName" placeholder="e.g. Breakfast"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Code (auto-generated if empty)</label>
                <input type="text" id="artCode" placeholder="e.g. breakfast"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
            </div>
            <button onclick="artCreate()" id="artCreateBtn"
                class="w-full py-3 bg-orange-500 text-white text-sm font-semibold rounded-xl active:bg-orange-600 mt-2">
                Create Type
            </button>
        </div>
    </div>`);
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
        showToast('Type created', 'success');
        closeSheet();
        // Clear client cache
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

    openSheet(`<div class="p-4">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Edit Requisition Type</h3>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Name</label>
                <input type="text" id="artEditName" value="${t.name}"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Code</label>
                <input type="text" id="artEditCode" value="${t.code}"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-gray-700">Active</span>
                <label class="relative inline-flex cursor-pointer">
                    <input type="checkbox" id="artEditActive" ${t.is_active == 1 ? 'checked' : ''} class="sr-only peer">
                    <div class="w-9 h-5 bg-gray-200 peer-checked:bg-green-500 rounded-full peer-focus:ring-2 peer-focus:ring-green-300 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
            </div>
            <button onclick="artUpdate(${t.id})" id="artUpdateBtn"
                class="w-full py-3 bg-orange-500 text-white text-sm font-semibold rounded-xl active:bg-orange-600">
                Save Changes
            </button>
        </div>
    </div>`);
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
        showToast('Type updated', 'success');
        closeSheet();
        try { sessionStorage.removeItem('api_api/requisition-types.php?action=list'); } catch {}
        artLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
        setLoading(btn, false);
    }
}

async function artToggle(id) {
    try {
        await api('api/requisition-types.php?action=toggle_active', {
            method: 'POST', body: { id }
        });
        showToast('Type toggled', 'success');
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

    // Swap sort_order values
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
