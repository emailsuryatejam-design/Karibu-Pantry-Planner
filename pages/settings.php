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
            <?php if (!empty($user['camp_name'])): ?>
            <div class="flex justify-between py-2 border-b border-gray-50">
                <span class="text-gray-500">Camp</span>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($user['camp_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isAdmin()): ?>
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
                <span class="font-medium text-gray-800">1.0.0</span>
            </div>
        </div>
    </div>

    <!-- Logout -->
    <a href="logout.php"
       class="block w-full py-3.5 bg-red-50 text-red-600 text-sm font-semibold rounded-xl text-center active:bg-red-100 transition">
        Log Out
    </a>

</div>

<?php if (isAdmin()): ?>
<script>
let usersData = [];

async function loadUsers() {
    try {
        const res = await api('api/users.php?action=list');
        usersData = res.users || [];
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
                    <p class="text-[10px] text-gray-400">${u.username}</p>
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

function showCreateUser() {
    openSheet(`
        <div class="p-4">
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
                    <label class="text-xs font-medium text-gray-600 mb-1 block">PIN (4+ digits)</label>
                    <input type="text" id="newPin" placeholder="e.g. 1234" maxlength="6" inputmode="numeric"
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
            body: { name, username, pin, role }
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
        <div class="p-4">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Edit User</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Full Name</label>
                    <input type="text" id="editName" value="${u.name}"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">New PIN (leave blank to keep current)</label>
                    <input type="text" id="editPin" placeholder="****" maxlength="6" inputmode="numeric"
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

    const body = { id, role, is_active: isActive };
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
</script>
<?php endif; ?>
