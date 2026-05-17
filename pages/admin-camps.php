<?php if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; } ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Camps</h1>
        <p class="text-xs text-gray-400 mt-0.5" id="acCount">Kitchen locations</p>
    </div>
    <button onclick="acShowCreate()" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 compact-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add Camp
    </button>
</div>

<div id="acList" class="space-y-3">
    <div class="text-center py-10 text-gray-300 text-sm">Loading...</div>
</div>

<script>
let acCamps = [];

acLoad();

async function acLoad() {
    try {
        const data = await api('api/kitchens.php?action=list&active=0');
        acCamps = data.kitchens || [];
        acRender();
    } catch(e) { showToast('Failed to load camps', 'error'); }
}

function acRender() {
    document.getElementById('acCount').textContent = `${acCamps.length} camp${acCamps.length !== 1 ? 's' : ''} · ${acCamps.filter(c => c.is_active).length} active`;
    if (!acCamps.length) {
        document.getElementById('acList').innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No camps yet</div>';
        return;
    }
    document.getElementById('acList').innerHTML = acCamps.map(c => `
        <div class="bg-white rounded-xl border ${c.is_active ? 'border-gray-100' : 'border-red-100'} shadow-sm px-4 py-4 ${c.is_active ? '' : 'opacity-60'}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center shrink-0 text-lg">🏕️</div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-800">${escHtml(c.name)}</span>
                            ${!c.is_active ? '<span class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">Inactive</span>' : ''}
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="font-mono text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded">${escHtml(c.code)}</span>
                            <span class="text-[10px] text-gray-400">👥 ${c.user_count || 0} staff</span>
                        </div>
                    </div>
                </div>
                <button onclick="acShowEdit(${c.id})" class="shrink-0 p-2 text-gray-300 hover:text-slate-600 hover:bg-gray-50 rounded-lg transition compact-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
                </button>
            </div>
        </div>
    `).join('');
}

function acShowCreate() { acOpenForm(null); }
function acShowEdit(id)  { acOpenForm(acCamps.find(c => c.id === id)); }

function acOpenForm(camp) {
    const isEdit = !!camp;
    openSheet(`
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">${isEdit ? 'Edit Camp' : 'New Camp'}</h3>
            <button onclick="closeSheet()" class="p-1"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="px-5 py-4 space-y-3">
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Camp Name *</label>
                <input type="text" id="acFormName" value="${camp?.name || ''}" placeholder="e.g. Ngorongoro Camp" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Code * <span class="text-gray-400 normal-case font-normal">(short unique ID, e.g. NCA)</span></label>
                <input type="text" id="acFormCode" value="${camp?.code || ''}" placeholder="NCA" maxlength="10" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <button onclick="acSave(${isEdit ? camp.id : 'null'})" id="acSaveBtn" class="w-full bg-slate-800 hover:bg-slate-700 text-white py-2.5 rounded-xl text-sm font-semibold transition mt-2">
                ${isEdit ? 'Save Changes' : 'Create Camp'}
            </button>
            ${isEdit ? `<button onclick="acToggle(${camp.id}, ${camp.is_active})" class="w-full border ${camp.is_active ? 'border-red-200 text-red-500 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50'} py-2 rounded-xl text-sm font-medium transition">
                ${camp.is_active ? 'Deactivate Camp' : 'Activate Camp'}
            </button>` : ''}
        </div>
    `);
}

async function acSave(id) {
    const name = document.getElementById('acFormName').value.trim();
    const code = document.getElementById('acFormCode').value.trim().toUpperCase();
    if (!name || !code) return showToast('Name and code are required', 'warning');

    const btn = document.getElementById('acSaveBtn');
    setLoading(btn, true, 'Saving...');
    try {
        await api('api/kitchens.php?action=save', { method: 'POST', body: { id: id || undefined, name, code } });
        closeSheet();
        showToast(id ? 'Camp updated' : 'Camp created!');
        acLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
        setLoading(btn, false);
    }
}

async function acToggle(id, isActive) {
    if (!confirm(isActive ? 'Deactivate this camp?' : 'Activate this camp?')) return;
    try {
        await api('api/kitchens.php?action=toggle_active', { method: 'POST', body: { id } });
        closeSheet();
        showToast(isActive ? 'Camp deactivated' : 'Camp activated');
        acLoad();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}
</script>
