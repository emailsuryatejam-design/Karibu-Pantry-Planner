<!-- Settings Page -->
<?php $user = currentUser(); ?>
<div class="space-y-4">

    <!-- Profile Card -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-14 h-14 bg-<?= isChef() ? 'orange' : (isStorekeeper() ? 'green' : 'blue') ?>-100 rounded-full flex items-center justify-center">
                <span class="text-<?= isChef() ? 'orange' : (isStorekeeper() ? 'green' : 'blue') ?>-700 font-bold text-xl">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </span>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="text-sm text-gray-500"><?= ucfirst($user['role']) ?></p>
            </div>
        </div>

        <div class="space-y-3 text-sm">
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Username</span>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Role</span>
                <span class="font-medium text-gray-800"><?= ucfirst($user['role']) ?></span>
            </div>
            <?php if (!empty($user['kitchen_name'])): ?>
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Kitchen</span>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($user['kitchen_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($user['camp_name'])): ?>
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Camp</span>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($user['camp_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications & Voice -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="font-semibold text-sm text-gray-800 mb-3">Notifications & Audio</h3>
        <div class="flex items-center justify-between mb-3">
            <div>
                <p class="text-sm text-gray-700">Push Notifications</p>
                <p class="text-[10px] text-gray-400" id="pushStatus">Checking...</p>
            </div>
            <label class="relative inline-flex cursor-pointer">
                <input type="checkbox" id="pushToggle" class="sr-only peer" onchange="togglePush(this.checked)">
                <div class="w-9 h-5 bg-gray-200 peer-checked:bg-green-500 rounded-full peer-focus:ring-2 peer-focus:ring-green-300 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
        </div>
        <div class="border-t border-gray-100 pt-3 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-700">Voice Announcements</p>
                <p class="text-[10px] text-gray-400" id="voiceStatus">Speaks order events aloud</p>
            </div>
            <label class="relative inline-flex cursor-pointer">
                <input type="checkbox" id="voiceToggle" class="sr-only peer" onchange="toggleVoice(this.checked)">
                <div class="w-9 h-5 bg-gray-200 peer-checked:bg-green-500 rounded-full peer-focus:ring-2 peer-focus:ring-green-300 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
        </div>
        <button onclick="testVoice()" class="mt-3 w-full py-2 bg-gray-50 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-100 transition">
            Test Voice Announcement
        </button>
    </div>

    <?php if (isAdmin()): ?>
    <!-- Meal Types Management (Admin only) -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-sm text-gray-800">Meal Types</h3>
            <button onclick="showAddMealType()"
                class="px-3 py-1.5 bg-orange-600 text-white text-xs font-medium rounded-lg active:bg-orange-700">
                + Add Type
            </button>
        </div>
        <div id="mealTypesList" class="space-y-2">
            <p class="text-xs text-gray-400 text-center py-4">Loading...</p>
        </div>
    </div>

    <!-- Scaling & Portioning Settings (Admin only) -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="font-semibold text-sm text-gray-800 mb-1">Scaling & Portioning</h3>
        <p class="text-[10px] text-gray-400 mb-4">Configure how ingredients are calculated when scaling recipes</p>
        <div id="scalingSettings" class="space-y-3">
            <p class="text-xs text-gray-400 text-center py-4">Loading...</p>
        </div>
    </div>

    <!-- User Management (Admin only) -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-sm text-gray-800">Manage Users</h3>
            <button onclick="showCreateUser()"
                class="px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg active:bg-blue-700">
                + Add User
            </button>
        </div>
        <div id="usersList" class="space-y-2">
            <p class="text-xs text-gray-400 text-center py-4">Loading users...</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- App Info -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="font-semibold text-sm text-gray-800 mb-3">About</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">App</span>
                <span class="font-medium text-gray-800">Karibu Pantry Planner</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Version</span>
                <span class="font-medium text-gray-800">2.0.0</span>
            </div>
        </div>
    </div>

    <!-- Logout -->
    <a href="logout.php"
       class="block w-full py-3.5 bg-red-50 text-red-600 text-sm font-semibold rounded-xl text-center active:bg-red-100 transition">
        Log Out
    </a>

</div>

<script>
// ── Push Notification Toggle ──
async function initPushToggle() {
    const toggle = document.getElementById('pushToggle');
    const status = document.getElementById('pushStatus');

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        status.textContent = 'Not supported on this device';
        toggle.disabled = true;
        return;
    }

    const subscribed = await isPushSubscribed();
    toggle.checked = subscribed;
    status.textContent = subscribed ? 'Enabled — you will receive alerts' : 'Disabled — enable to get order alerts';
}

async function togglePush(enabled) {
    const status = document.getElementById('pushStatus');
    const toggle = document.getElementById('pushToggle');

    if (enabled) {
        status.textContent = 'Subscribing...';
        const ok = await pushSubscribe();
        toggle.checked = ok;
        status.textContent = ok ? 'Enabled — you will receive alerts' : 'Failed to enable';
    } else {
        status.textContent = 'Unsubscribing...';
        const ok = await pushUnsubscribe();
        toggle.checked = !ok;
        status.textContent = ok ? 'Disabled — enable to get order alerts' : 'Failed to disable';
    }
}

initPushToggle();

// ── Voice Announcement Toggle ──
function initVoiceToggle() {
    const toggle = document.getElementById('voiceToggle');
    const status = document.getElementById('voiceStatus');

    if (!('speechSynthesis' in window)) {
        status.textContent = 'Not supported on this device';
        toggle.disabled = true;
        return;
    }

    toggle.checked = voice.enabled;
    status.textContent = voice.enabled ? 'Enabled — speaks order events aloud' : 'Disabled';
}

function toggleVoice(enabled) {
    voice.toggle(enabled);
    const status = document.getElementById('voiceStatus');
    status.textContent = enabled ? 'Enabled — speaks order events aloud' : 'Disabled';
    if (enabled) {
        voice.say('Voice announcements are now enabled.');
    }
}

function testVoice() {
    const wasEnabled = voice.enabled;
    if (!wasEnabled) voice.enabled = true;
    voice.say('This is a test announcement from Karibu Pantry Planner. Order submitted, requisition 1 for Test Kitchen.', 'high');
    if (!wasEnabled) {
        setTimeout(() => { voice.enabled = false; }, 5000);
    }
}

initVoiceToggle();
</script>

<?php if (isAdmin()): ?>
<script>
let usersData = [];
let kitchensList = [];

async function loadUsers() {
    try {
        const [usersRes, kitchensRes] = await Promise.all([
            api('api/users.php?action=list'),
            api('api/kitchens.php?action=list')
        ]);
        usersData = usersRes.users || [];
        kitchensList = kitchensRes.kitchens || [];
        renderUsers();
    } catch (err) {
        document.getElementById('usersList').innerHTML =
            `<p class="text-xs text-red-500 text-center py-4">${err.message}</p>`;
    }
}

function roleBadge(role) {
    const cls = {
        admin: 'bg-blue-100 text-blue-700',
        chef: 'bg-orange-100 text-orange-700',
        storekeeper: 'bg-green-100 text-green-700',
    };
    return `<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ${cls[role] || 'bg-gray-100 text-gray-600'}">${role.charAt(0).toUpperCase() + role.slice(1)}</span>`;
}

function renderUsers() {
    const list = document.getElementById('usersList');
    if (usersData.length === 0) {
        list.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No users found</p>';
        return;
    }

    list.innerHTML = usersData.map(u => `
        <div class="flex items-center justify-between py-2.5 px-3 rounded-lg ${u.is_active == 1 ? 'bg-gray-50' : 'bg-red-50 opacity-60'}">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 bg-${u.role === 'chef' ? 'orange' : u.role === 'storekeeper' ? 'green' : 'blue'}-100 rounded-full flex items-center justify-center shrink-0">
                    <span class="text-${u.role === 'chef' ? 'orange' : u.role === 'storekeeper' ? 'green' : 'blue'}-700 font-semibold text-xs">${u.name.charAt(0).toUpperCase()}</span>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${u.name}</p>
                    <p class="text-[10px] text-gray-400">${u.username}${u.kitchen_name ? ' · ' + u.kitchen_name : ''}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                ${roleBadge(u.role)}
                <button onclick="showEditUser(${u.id})" class="p-1.5 text-gray-400 hover:text-blue-600 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                </button>
            </div>
        </div>
    `).join('');
}

function kitchenOptions(selectedId) {
    let html = '<option value="">No Kitchen</option>';
    kitchensList.forEach(k => {
        html += `<option value="${k.id}" ${k.id == selectedId ? 'selected' : ''}>${k.name} (${k.code})</option>`;
    });
    return html;
}

function showCreateUser() {
    openSheet(`
        <div class="p-4 overflow-y-auto max-h-[75dvh]">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Add New User</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Full Name</label>
                    <input type="text" id="newName" placeholder="e.g. John Chef"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Username</label>
                    <input type="text" id="newUsername" placeholder="e.g. john"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">PIN (4 digits)</label>
                    <input type="text" id="newPin" placeholder="e.g. 1234" maxlength="4" inputmode="numeric"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm text-center tracking-[0.5em]">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Role</label>
                    <select id="newRole" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="chef">Chef</option>
                        <option value="storekeeper">Storekeeper</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Kitchen</label>
                    <select id="newKitchen" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-white">
                        ${kitchenOptions('')}
                    </select>
                </div>
                <button onclick="createUser()" id="createBtn"
                    class="w-full py-3 bg-blue-600 text-white text-sm font-semibold rounded-xl active:bg-blue-700 mt-2">
                    Create User
                </button>
            </div>
        </div>
    `);
}

async function createUser() {
    const name = document.getElementById('newName')?.value.trim();
    const username = document.getElementById('newUsername')?.value.trim();
    const pin = document.getElementById('newPin')?.value.trim();
    const role = document.getElementById('newRole')?.value;
    const kitchenId = document.getElementById('newKitchen')?.value || null;

    if (!name || !username || !pin) {
        showToast('Fill in all fields', 'warning');
        return;
    }
    if (pin.length < 4) {
        showToast('PIN must be at least 4 digits', 'warning');
        return;
    }

    const btn = document.getElementById('createBtn');
    setLoading(btn, true);

    try {
        await api('api/users.php?action=create', {
            method: 'POST',
            body: { name, username, pin, role, kitchen_id: kitchenId ? parseInt(kitchenId) : null }
        });
        showToast('User created!', 'success');
        closeSheet();
        loadUsers();
    } catch (err) {
        showToast(err.message, 'error');
        setLoading(btn, false);
    }
}

function showEditUser(id) {
    const u = usersData.find(x => x.id == id);
    if (!u) return;

    openSheet(`
        <div class="p-4 overflow-y-auto max-h-[75dvh]">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Edit User</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Full Name</label>
                    <input type="text" id="editName" value="${u.name}"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">New PIN (leave blank to keep current)</label>
                    <input type="text" id="editPin" placeholder="****" maxlength="4" inputmode="numeric"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm text-center tracking-[0.5em]">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Role</label>
                    <select id="editRole" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="chef" ${u.role === 'chef' ? 'selected' : ''}>Chef</option>
                        <option value="storekeeper" ${u.role === 'storekeeper' ? 'selected' : ''}>Storekeeper</option>
                        <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Kitchen</label>
                    <select id="editKitchen" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-white">
                        ${kitchenOptions(u.kitchen_id)}
                    </select>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-700">Active</span>
                    <label class="relative inline-flex cursor-pointer">
                        <input type="checkbox" id="editActive" ${u.is_active == 1 ? 'checked' : ''} class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-checked:bg-green-500 rounded-full peer-focus:ring-2 peer-focus:ring-green-300 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <button onclick="updateUser(${u.id})" id="updateBtn"
                    class="w-full py-3 bg-blue-600 text-white text-sm font-semibold rounded-xl active:bg-blue-700">
                    Save Changes
                </button>
                <button onclick="deleteUser(${u.id}, '${u.name.replace(/'/g, "\\'")}')"
                    class="w-full py-2.5 bg-red-50 text-red-600 text-xs font-medium rounded-xl active:bg-red-100">
                    Delete User
                </button>
            </div>
        </div>
    `);
}

async function updateUser(id) {
    const name = document.getElementById('editName')?.value.trim();
    const pin = document.getElementById('editPin')?.value.trim();
    const role = document.getElementById('editRole')?.value;
    const isActive = document.getElementById('editActive')?.checked;
    const kitchenId = document.getElementById('editKitchen')?.value || null;

    const body = { id, role, is_active: isActive, kitchen_id: kitchenId ? parseInt(kitchenId) : null };
    if (name) body.name = name;
    if (pin) body.pin = pin;

    const btn = document.getElementById('updateBtn');
    setLoading(btn, true);

    try {
        await api('api/users.php?action=update', { method: 'POST', body });
        showToast('User updated!', 'success');
        closeSheet();
        loadUsers();
    } catch (err) {
        showToast(err.message, 'error');
        setLoading(btn, false);
    }
}

async function deleteUser(id, name) {
    if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;

    try {
        await api('api/users.php?action=delete', { method: 'POST', body: { id } });
        showToast('User deleted', 'success');
        closeSheet();
        loadUsers();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// Init
loadUsers();

// ── Meal Types Management ──
let mealTypesData = [];

async function loadMealTypes() {
    try {
        const data = await api('api/requisition-types.php?action=list_all');
        mealTypesData = data.types || [];
        renderMealTypes();
    } catch (err) {
        document.getElementById('mealTypesList').innerHTML =
            `<p class="text-xs text-red-500 text-center py-4">${escHtml(err.message)}</p>`;
    }
}

function renderMealTypes() {
    const list = document.getElementById('mealTypesList');
    if (mealTypesData.length === 0) {
        list.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No meal types configured</p>';
        return;
    }

    list.innerHTML = mealTypesData.map((t, idx) => `
        <div class="flex items-center justify-between py-2.5 px-3 rounded-lg ${t.is_active == 1 ? 'bg-gray-50' : 'bg-red-50 opacity-60'}">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex flex-col gap-0.5">
                    ${idx > 0 ? `<button onclick="moveMealType(${t.id}, 'up')" class="text-gray-400 hover:text-orange-600 leading-none"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m18 15-6-6-6 6"/></svg></button>` : '<div class="h-3"></div>'}
                    ${idx < mealTypesData.length - 1 ? `<button onclick="moveMealType(${t.id}, 'down')" class="text-gray-400 hover:text-orange-600 leading-none"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg></button>` : '<div class="h-3"></div>'}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${escHtml(t.name)}</p>
                    <p class="text-[10px] text-gray-400">${escHtml(t.code)}${t.is_active != 1 ? ' · inactive' : ''}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <label class="relative inline-flex cursor-pointer">
                    <input type="checkbox" ${t.is_active == 1 ? 'checked' : ''} onchange="toggleMealType(${t.id})" class="sr-only peer">
                    <div class="w-8 h-4 bg-gray-200 peer-checked:bg-green-500 rounded-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
                <button onclick="showEditMealType(${t.id})" class="p-1.5 text-gray-400 hover:text-orange-600 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                </button>
            </div>
        </div>
    `).join('');
}

function showAddMealType() {
    openSheet(`
        <div class="p-4">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Add Meal Type</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Name</label>
                    <input type="text" id="mtName" placeholder="e.g. Lunchboxes"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Code (auto-generated if blank)</label>
                    <input type="text" id="mtCode" placeholder="e.g. lunchboxes"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <button onclick="saveMealType(0)" id="mtSaveBtn"
                    class="w-full py-3 bg-orange-600 text-white text-sm font-semibold rounded-xl active:bg-orange-700 mt-2">
                    Add Meal Type
                </button>
            </div>
        </div>
    `);
}

function showEditMealType(id) {
    const t = mealTypesData.find(x => x.id == id);
    if (!t) return;

    openSheet(`
        <div class="p-4">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Edit Meal Type</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Name</label>
                    <input type="text" id="mtName" value="${escHtml(t.name)}"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Code</label>
                    <input type="text" id="mtCode" value="${escHtml(t.code)}"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-700">Active</span>
                    <label class="relative inline-flex cursor-pointer">
                        <input type="checkbox" id="mtActive" ${t.is_active == 1 ? 'checked' : ''} class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-checked:bg-green-500 rounded-full peer-focus:ring-2 peer-focus:ring-green-300 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <button onclick="saveMealType(${t.id})" id="mtSaveBtn"
                    class="w-full py-3 bg-orange-600 text-white text-sm font-semibold rounded-xl active:bg-orange-700">
                    Save Changes
                </button>
            </div>
        </div>
    `);
}

async function saveMealType(id) {
    const name = document.getElementById('mtName')?.value.trim();
    const code = document.getElementById('mtCode')?.value.trim();
    if (!name) { showToast('Name is required', 'warning'); return; }

    const body = { name, code };
    if (id > 0) {
        body.id = id;
        const t = mealTypesData.find(x => x.id == id);
        body.sort_order = t ? t.sort_order : 0;
        body.is_active = document.getElementById('mtActive')?.checked ? 1 : 0;
    }

    const btn = document.getElementById('mtSaveBtn');
    setLoading(btn, true);
    try {
        await api('api/requisition-types.php?action=save', { method: 'POST', body });
        showToast(id ? 'Meal type updated!' : 'Meal type added!', 'success');
        closeSheet();
        loadMealTypes();
    } catch (err) {
        showToast(err.message, 'error');
        setLoading(btn, false);
    }
}

async function toggleMealType(id) {
    try {
        await api('api/requisition-types.php?action=toggle_active', { method: 'POST', body: { id } });
        loadMealTypes();
    } catch (err) {
        showToast(err.message, 'error');
        loadMealTypes();
    }
}

async function moveMealType(id, direction) {
    const idx = mealTypesData.findIndex(x => x.id == id);
    if (idx < 0) return;
    const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
    if (swapIdx < 0 || swapIdx >= mealTypesData.length) return;

    // Swap sort_order values
    const items = mealTypesData.map((t, i) => {
        let sortOrder = t.sort_order;
        if (i === idx) sortOrder = mealTypesData[swapIdx].sort_order;
        if (i === swapIdx) sortOrder = mealTypesData[idx].sort_order;
        return { id: t.id, sort_order: sortOrder };
    });

    try {
        await api('api/requisition-types.php?action=reorder', { method: 'POST', body: { items } });
        loadMealTypes();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

loadMealTypes();

// ── Scaling & Portioning Settings ──
const SETTINGS_KITCHEN_ID = <?= (int)($user['kitchen_id'] ?? 0) ?>;

async function loadScalingSettings() {
    const container = document.getElementById('scalingSettings');
    try {
        const data = await api(`api/kitchens.php?action=get_settings&kitchen_id=${SETTINGS_KITCHEN_ID}`);
        const s = data.settings;
        renderScalingSettings(s);
    } catch (err) {
        container.innerHTML = `<p class="text-xs text-red-500 text-center py-4">${escHtml(err.message)}</p>`;
    }
}

function renderScalingSettings(s) {
    const roundingLabels = { half: 'Nearest 0.5 (e.g. 1.3 \u2192 1.5)', whole: 'Nearest whole (e.g. 1.3 \u2192 2)', none: 'No rounding (exact)' };
    const container = document.getElementById('scalingSettings');
    container.innerHTML = `
        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Default Guest Count</label>
            <input type="number" id="scDefaultGuests" value="${s.default_guest_count}" min="1" max="500"
                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            <p class="text-[10px] text-gray-400 mt-1">Pre-filled when creating new requisitions</p>
        </div>
        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Rounding Mode</label>
            <select id="scRoundingMode" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-200">
                <option value="half" ${s.rounding_mode === 'half' ? 'selected' : ''}>Round up to nearest 0.5</option>
                <option value="whole" ${s.rounding_mode === 'whole' ? 'selected' : ''}>Round up to nearest whole</option>
                <option value="none" ${s.rounding_mode === 'none' ? 'selected' : ''}>No rounding (exact values)</option>
            </select>
            <p class="text-[10px] text-gray-400 mt-1">How ingredient quantities are rounded when scaling</p>
        </div>
        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Minimum Order Quantity</label>
            <input type="number" id="scMinOrderQty" value="${s.min_order_qty}" min="0" step="0.1"
                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            <p class="text-[10px] text-gray-400 mt-1">Smallest quantity that can be ordered (in base UOM)</p>
        </div>
        <button onclick="saveScalingSettings()" id="scSaveBtn"
            class="w-full py-2.5 bg-orange-600 text-white text-sm font-semibold rounded-xl active:bg-orange-700 transition">
            Save Scaling Settings
        </button>`;
}

async function saveScalingSettings() {
    const btn = document.getElementById('scSaveBtn');
    setLoading(btn, true);
    try {
        await api('api/kitchens.php?action=save_settings', {
            method: 'POST',
            body: {
                kitchen_id: SETTINGS_KITCHEN_ID,
                default_guest_count: parseInt(document.getElementById('scDefaultGuests').value) || 20,
                rounding_mode: document.getElementById('scRoundingMode').value,
                min_order_qty: parseFloat(document.getElementById('scMinOrderQty').value) || 0.5
            }
        });
        showToast('Scaling settings saved!', 'success');
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        setLoading(btn, false);
    }
}

loadScalingSettings();
</script>
<?php endif; ?>
