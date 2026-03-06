<?php
/**
 * Karibu Pantry Planner — Admin Kitchen Management
 */
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
?>

<div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-bold text-gray-800">Kitchen Management</h2>
    <button onclick="akShowCreate()" class="bg-orange-500 text-white px-3 py-2 rounded-xl text-xs font-semibold hover:bg-orange-600 transition flex items-center gap-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add Kitchen
    </button>
</div>

<div id="akList" class="space-y-2"></div>

<script>
let akKitchens = [];

akLoad();

async function akLoad() {
    try {
        const data = await api('api/kitchens.php?action=list&active=0');
        akKitchens = data.kitchens || [];
        akRender();
    } catch(e) {
        showToast('Failed to load kitchens', 'error');
    }
}

function akRender() {
    const container = document.getElementById('akList');
    if (akKitchens.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-8 text-sm">No kitchens</p>';
        return;
    }
    let html = '';
    akKitchens.forEach(k => {
        html += `<div class="bg-white border border-gray-200 rounded-xl px-4 py-3 ${k.is_active ? '' : 'opacity-50'}">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-800">${k.name}</span>
                        ${!k.is_active ? '<span class="text-[9px] bg-red-100 text-red-600 px-1 py-0.5 rounded">Inactive</span>' : ''}
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded font-mono">${k.code}</span>
                        <span class="text-[10px] text-gray-400">${k.user_count || 0} user${k.user_count != 1 ? 's' : ''}</span>
                    </div>
                </div>
                <button onclick="akShowEdit(${k.id})" class="p-2 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
                </button>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function akShowCreate() { akOpenForm(null); }
function akShowEdit(id) {
    const k = akKitchens.find(x => x.id === id);
    if (k) akOpenForm(k);
}

function akOpenForm(kitchen) {
    const isEdit = !!kitchen;
    const html = `<div class="p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">${isEdit ? 'Edit' : 'New'} Kitchen</h3>
        <div class="space-y-3">
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Name *</label>
                <input type="text" id="akFormName" value="${kitchen?.name || ''}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Code *</label>
                <input type="text" id="akFormCode" value="${kitchen?.code || ''}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-orange-200">
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            ${isEdit ? `<button onclick="akToggle(${kitchen.id})" class="flex-1 py-2.5 rounded-xl text-xs font-semibold ${kitchen.is_active ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'} transition">${kitchen.is_active ? 'Deactivate' : 'Activate'}</button>` : ''}
            <button onclick="akSave(${kitchen?.id || 0})" class="flex-1 bg-orange-500 text-white py-2.5 rounded-xl text-xs font-semibold hover:bg-orange-600 transition">Save</button>
        </div>
    </div>`;
    openSheet(html);
}

async function akSave(id) {
    const data = {
        id: id || undefined,
        name: document.getElementById('akFormName').value,
        code: document.getElementById('akFormCode').value.toUpperCase()
    };
    try {
        await api('api/kitchens.php?action=save', { method: 'POST', body: JSON.stringify(data) });
        closeSheet();
        showToast(id ? 'Kitchen updated' : 'Kitchen created', 'success');
        akLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}

async function akToggle(id) {
    try {
        await api('api/kitchens.php?action=toggle_active', { method: 'POST', body: JSON.stringify({ id }) });
        closeSheet();
        showToast('Status changed', 'success');
        akLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}
</script>
