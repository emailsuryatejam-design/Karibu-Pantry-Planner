<?php if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; } ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Users</h1>
        <p class="text-xs text-gray-400 mt-0.5" id="auCount">Loading...</p>
    </div>
    <button onclick="auShowCreate()" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 compact-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add User
    </button>
</div>

<!-- Role filter -->
<div class="flex gap-1.5 mb-3 overflow-x-auto pb-1">
    <button onclick="auSetRole('')"            id="auRoleAll"   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 text-white transition">All</button>
    <button onclick="auSetRole('chef')"        id="auRoleChef"  class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Chefs</button>
    <button onclick="auSetRole('storekeeper')" id="auRoleStore" class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Storekeepers</button>
    <button onclick="auSetRole('admin')"       id="auRoleAdmin" class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Admins</button>
</div>

<div id="auList" class="space-y-2">
    <div class="text-center py-10 text-gray-300 text-sm">Loading...</div>
</div>

<script>
let auUsers    = [];
let auKitchens = [];
let auRole     = '';

const auRoleBadge = {
    chef:        'bg-orange-100 text-orange-700',
    storekeeper: 'bg-green-100 text-green-700',
    admin:       'bg-slate-200 text-slate-700',
};
const auRoleLabel = { chef: 'Chef', storekeeper: 'Storekeeper', admin: 'Admin' };

(async () => {
    try {
        const [ud, kd] = await Promise.all([
            api('api/users.php?action=list'),
            api('api/kitchens.php?action=list')
        ]);
        auUsers    = ud.users    || [];
        auKitchens = kd.kitchens || [];
        auRender();
    } catch(e) { showToast('Failed to load users', 'error'); }
})();

function auSetRole(role) {
    auRole = role;
    document.querySelectorAll('[id^="auRole"]').forEach(b => {
        b.className = b.className.replace('bg-slate-800 text-white', 'bg-gray-100 text-gray-600');
    });
    const ids = { '': 'All', chef: 'Chef', storekeeper: 'Store', admin: 'Admin' };
    const active = document.getElementById('auRole' + ids[role]);
    if (active) active.className = active.className.replace('bg-gray-100 text-gray-600', 'bg-slate-800 text-white');
    auRender();
}

function auRender() {
    const filtered = auRole ? auUsers.filter(u => u.role === auRole) : auUsers;
    const active   = filtered.filter(u => u.is_active == 1);
    document.getElementById('auCount').textContent = `${filtered.length} user${filtered.length !== 1 ? 's' : ''} · ${active.length} active`;

    if (!filtered.length) {
        document.getElementById('auList').innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No users found</div>';
        return;
    }

    document.getElementById('auList').innerHTML = filtered.map(u => `
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 ${u.is_active == 1 ? '' : 'opacity-50'}">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center shrink-0">
                        <span class="text-sm font-bold text-slate-600">${escHtml(u.name.charAt(0).toUpperCase())}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-800">${escHtml(u.name)}</span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${auRoleBadge[u.role] || 'bg-gray-100 text-gray-600'}">${auRoleLabel[u.role] || u.role}</span>
                            ${u.is_active == 0 ? '<span class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">Inactive</span>' : ''}
                        </div>
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            ${u.kitchen_name ? '' + escHtml(u.kitchen_name) + ' · ' : ''}@${escHtml(u.username)}${u.email ? ' · ' + escHtml(u.email) : ''}
                        </div>
                    </div>
                </div>
                <button onclick="auShowEdit(${u.id})" class="shrink-0 p-2 text-gray-300 hover:text-slate-600 hover:bg-gray-50 rounded-lg transition compact-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
                </button>
            </div>
        </div>
    `).join('');
}

function auKitchenOptions(selectedId) {
    return '<option value="">— No Camp —</option>' +
        auKitchens.map(k => `<option value="${k.id}" ${k.id == selectedId ? 'selected' : ''}>${escHtml(k.name)}</option>`).join('');
}

function auShowCreate() { auOpenForm(null); }
function auShowEdit(id) { auOpenForm(auUsers.find(u => u.id === id)); }

function auOpenForm(u) {
    const isEdit = !!u;
    openSheet(`
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">${isEdit ? 'Edit User' : 'New User'}</h3>
            <button onclick="closeSheet()" class="p-1"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3 scroll-touch">
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Full Name *</label>
                    <input type="text" id="auFormName" value="${u?.name || ''}" placeholder="e.g. John Mwangi" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                </div>
                <div>
                    <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Username *</label>
                    <input type="text" id="auFormUsername" value="${u?.username || ''}" placeholder="johnm" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                </div>
                <div>
                    <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">PIN ${isEdit ? '(leave blank)' : '*'}</label>
                    <input type="text" id="auFormPin" value="" placeholder="4 digits" maxlength="4" inputmode="numeric" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                </div>
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Role *</label>
                <select id="auFormRole" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    <option value="chef"        ${u?.role === 'chef'        ? 'selected' : ''}>Chef</option>
                    <option value="storekeeper" ${u?.role === 'storekeeper' ? 'selected' : ''}>Storekeeper</option>
                    <option value="admin"       ${u?.role === 'admin'       ? 'selected' : ''}>Admin</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Camp</label>
                <select id="auFormKitchen" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    ${auKitchenOptions(u?.kitchen_id)}
                </select>
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Email</label>
                <input type="email" id="auFormEmail" value="${u?.email || ''}" placeholder="chef@example.com" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Password ${isEdit ? '(leave blank to keep)' : '(optional)'}</label>
                <input type="password" id="auFormPassword" placeholder="For email/admin login" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            </div>
            <button onclick="auSave(${isEdit ? u.id : 'null'})" id="auSaveBtn" class="w-full bg-slate-800 hover:bg-slate-700 text-white py-2.5 rounded-xl text-sm font-semibold transition mt-2">
                ${isEdit ? 'Save Changes' : 'Create User'}
            </button>
            ${isEdit ? `<button onclick="auToggleActive(${u.id}, ${u.is_active})" class="w-full border ${u.is_active == 1 ? 'border-red-200 text-red-500 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50'} py-2 rounded-xl text-sm font-medium transition">
                ${u.is_active == 1 ? 'Deactivate User' : 'Activate User'}
            </button>` : ''}
        </div>
    `);
}

async function auSave(editId) {
    const name      = document.getElementById('auFormName').value.trim();
    const username  = document.getElementById('auFormUsername').value.trim();
    const pin       = document.getElementById('auFormPin').value.trim();
    const role      = document.getElementById('auFormRole').value;
    const kitchenId = document.getElementById('auFormKitchen').value || null;
    const email     = document.getElementById('auFormEmail').value.trim() || null;
    const password  = document.getElementById('auFormPassword').value || null;

    if (!name || !username) return showToast('Name and username are required', 'warning');
    if (!editId && (!pin || pin.length !== 4 || !/^\d{4}$/.test(pin))) return showToast('PIN must be exactly 4 digits', 'warning');

    const btn = document.getElementById('auSaveBtn');
    setLoading(btn, true, editId ? 'Saving...' : 'Creating...');

    try {
        await api('api/users.php', { method: 'POST', body: {
            action: editId ? 'update' : 'create',
            id: editId,
            name, username, pin: pin || undefined,
            role, kitchen_id: kitchenId, email, password
        }});
        closeSheet();
        showToast(editId ? 'User updated' : 'User created!', 'success');
        const ud = await api('api/users.php?action=list');
        auUsers = ud.users || [];
        auRender();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
        setLoading(btn, false);
    }
}

async function auToggleActive(id, currentActive) {
    const action = currentActive == 1 ? 'Deactivate' : 'Activate';
    if (!confirm(`${action} this user?`)) return;
    try {
        await api('api/users.php', { method: 'POST', body: { action: 'toggle_active', id } });
        closeSheet();
        showToast(`User ${action.toLowerCase()}d`);
        const ud = await api('api/users.php?action=list');
        auUsers = ud.users || [];
        auRender();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}
</script>
