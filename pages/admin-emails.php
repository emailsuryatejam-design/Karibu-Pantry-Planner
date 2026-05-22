<?php
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-bold text-gray-800">Email Notifications</h2>
        <p class="text-xs text-gray-400 mt-0.5">Manage who receives order alerts and PDF reports</p>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="aeDiag()" class="border border-gray-200 text-gray-500 px-3 py-2 rounded-xl text-xs font-semibold hover:bg-gray-50 transition flex items-center gap-1 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            Diagnose
        </button>
        <button onclick="aeShowCreate()" class="bg-orange-500 text-white px-3 py-2 rounded-xl text-xs font-semibold hover:bg-orange-600 transition flex items-center gap-1 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Add Email
        </button>
    </div>
</div>

<!-- Info banner -->
<div class="mb-4 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-xs text-blue-700">
    <strong>How it works:</strong> These addresses receive email alerts (+ PDF order sheet) whenever a requisition is submitted or fulfilled, in addition to the regular user accounts.
</div>

<div id="aeList" class="space-y-2">
    <div class="text-center py-8 text-gray-400 text-sm">Loading...</div>
</div>

<script>
let aeEmails   = [];
let aeKitchens = [];

let aeLoaded = false;
(async () => {
    try {
        const [ed, kd] = await Promise.all([
            api('api/notification-emails.php?action=list'),
            api('api/kitchens.php?action=list')
        ]);
        aeEmails   = ed.emails   || [];
        aeKitchens = kd.kitchens || [];
        aeLoaded = true;
        aeRender();
    } catch(e) {
        console.error('Email load error:', e);
        const el = document.getElementById('aeList');
        if (el) el.innerHTML = '<div class="text-center py-10 text-red-400 text-sm">Failed to load — ' + (e.message||'unknown error') + '</div>';
    }
})();

const notifyLabels = { submit: '📤 On Submit', fulfill: '✅ On Fulfill', both: '📧 Both' };
const notifyColors = { submit: 'bg-blue-50 text-blue-700', fulfill: 'bg-green-50 text-green-700', both: 'bg-orange-50 text-orange-700' };

function aeRender() {
    const el = document.getElementById('aeList');
    if (!aeEmails.length) {
        el.innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No notification emails yet — add one above</div>';
        return;
    }
    el.innerHTML = aeEmails.map(e => `
        <div class="bg-white border border-gray-100 rounded-xl px-4 py-3 shadow-sm ${e.is_active ? '' : 'opacity-50'}">
            <div class="flex items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-800">${escHtml(e.name)}</span>
                        ${!e.is_active ? '<span class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded">Paused</span>' : ''}
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">${escHtml(e.email)}</div>
                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium ${notifyColors[e.notify_on] || 'bg-gray-100 text-gray-600'}">${notifyLabels[e.notify_on] || e.notify_on}</span>
                        <span class="text-[10px] text-gray-400">${e.kitchen_name ? '🏕️ ' + escHtml(e.kitchen_name) : '🌍 All kitchens'}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button onclick="aeSendTest(${e.id})" title="Send test email" class="text-[10px] px-2.5 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition compact-btn">
                        Test
                    </button>
                    <button onclick="aeToggle(${e.id})" class="text-[10px] px-2.5 py-1.5 rounded-lg ${e.is_active ? 'bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-500' : 'bg-green-50 text-green-600 hover:bg-green-100'} transition compact-btn">
                        ${e.is_active ? 'Pause' : 'Enable'}
                    </button>
                    <button onclick="aeShowEdit(${e.id})" class="p-2 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition compact-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
                    </button>
                    <button onclick="aeDelete(${e.id})" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition compact-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function aeShowCreate() { aeOpenForm(null); }
function aeShowEdit(id) { aeOpenForm(aeEmails.find(e => e.id === id)); }

function aeOpenForm(item) {
    const isEdit = !!item;
    const kitchenOpts = '<option value="">🌍 All Kitchens</option>' +
        aeKitchens.map(k => `<option value="${k.id}" ${item?.kitchen_id == k.id ? 'selected' : ''}>${escHtml(k.name)}</option>`).join('');
    openSheet(`
        <div class="flex justify-center pt-2 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">${isEdit ? 'Edit' : 'Add'} Notification Email</h3>
            <button onclick="closeSheet()" class="p-1 compact-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-400"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Full Name *</label>
                <input type="text" id="aeFormName" value="${item?.name || ''}" placeholder="e.g. Store Manager" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Email Address *</label>
                <input type="email" id="aeFormEmail" value="${item?.email || ''}" placeholder="manager@example.com" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Notify When</label>
                <select id="aeFormNotifyOn" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="both" ${item?.notify_on === 'both' || !item ? 'selected' : ''}>📧 Both — Submit & Fulfill</option>
                    <option value="submit" ${item?.notify_on === 'submit' ? 'selected' : ''}>📤 Order Submitted only</option>
                    <option value="fulfill" ${item?.notify_on === 'fulfill' ? 'selected' : ''}>✅ Order Fulfilled only</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Kitchen</label>
                <select id="aeFormKitchen" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                    ${kitchenOpts}
                </select>
            </div>
            <button onclick="aeSave(${isEdit ? item.id : 'null'})" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-lg text-sm font-semibold transition mt-2">
                ${isEdit ? 'Save Changes' : 'Add Email'}
            </button>
            ${isEdit ? `<button onclick="aeDelete(${item.id})" class="w-full border border-red-200 text-red-500 py-2 rounded-lg text-sm font-medium transition hover:bg-red-50 mt-1">Remove</button>` : ''}
        </div>
    `);
}

async function aeSave(editId) {
    const name      = document.getElementById('aeFormName').value.trim();
    const email     = document.getElementById('aeFormEmail').value.trim();
    const notifyOn  = document.getElementById('aeFormNotifyOn').value;
    const kitchenId = document.getElementById('aeFormKitchen').value || null;
    if (!name || !email) return showToast('Name and email required', 'warning');
    try {
        await api('api/notification-emails.php', { method: 'POST', body: { action: 'save', id: editId, name, email, notify_on: notifyOn, kitchen_id: kitchenId } });
        closeSheet();
        showToast(editId ? 'Updated!' : 'Email added!');
        const [ed] = await Promise.all([api('api/notification-emails.php?action=list')]);
        aeEmails = ed.emails || [];
        aeRender();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}

async function aeToggle(id) {
    try {
        const d = await api('api/notification-emails.php', { method: 'POST', body: { action: 'toggle', id } });
        const em = aeEmails.find(e => e.id === id);
        if (em) em.is_active = d.is_active;
        aeRender();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}

async function aeDelete(id) {
    if (!confirm('Remove this email from notifications?')) return;
    try {
        await api('api/notification-emails.php', { method: 'POST', body: { action: 'delete', id } });
        closeSheet();
        showToast('Removed');
        aeEmails = aeEmails.filter(e => e.id !== id);
        aeRender();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}

async function aeSendTest(id) {
    const em = aeEmails.find(e => e.id === id);
    if (!em) return;
    showToast('Sending test to ' + em.email + '…', 'info');
    try {
        await api('api/notification-emails.php', { method: 'POST', body: { action: 'test_send', id } });
        showToast('Test email sent to ' + em.email + ' ✓');
    } catch(e) { showToast('Failed: ' + (e.message || 'unknown error'), 'error'); }
}

async function aeDiag() {
    try {
        showToast('Running diagnostics…', 'info');
        const d = await api('api/notification-emails.php?action=diag');
        const lines = [
            'PHPMailer: '    + (d.phpmailer_available ? '✅ installed' : '❌ missing (using php mail())'),
            'SMTP user: '    + (d.smtp_user_set ? '✅ set (' + d.from + ')' : '❌ NOT set'),
            'SMTP password: '+ (d.smtp_pass_set ? '✅ set' : '❌ NOT set — emails will fail'),
            'SMTP host: '    + d.smtp_host + ':' + d.smtp_port,
        ];
        alert('Email Diagnostics\n\n' + lines.join('\n'));
    } catch(e) { showToast('Diag failed: ' + (e.message || 'error'), 'error'); }
}
</script>
