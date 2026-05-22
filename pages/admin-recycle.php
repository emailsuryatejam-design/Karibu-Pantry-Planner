<?php
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-bold text-gray-800">Recycle Bin</h2>
        <p class="text-xs text-gray-400 mt-0.5">Everything deleted is kept here — restore anything at any time</p>
    </div>
    <button onclick="rbLoad()" class="border border-gray-200 text-gray-500 px-3 py-2 rounded-xl text-xs font-semibold hover:bg-gray-50 transition flex items-center gap-1 compact-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
        Refresh
    </button>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-4 border-b border-gray-100 overflow-x-auto">
    <button onclick="rbTab('recipes')"             id="tab-recipes"             class="rb-tab active">Recipes</button>
    <button onclick="rbTab('recipe_ingredients')"  id="tab-recipe_ingredients"  class="rb-tab">Ingredients</button>
    <button onclick="rbTab('notification_emails')" id="tab-notification_emails" class="rb-tab">Emails</button>
    <button onclick="rbTab('requisition_lines')"   id="tab-requisition_lines"   class="rb-tab">Order Lines</button>
    <button onclick="rbTab('requisition_dishes')"  id="tab-requisition_dishes"  class="rb-tab">Order Dishes</button>
</div>

<style>
.rb-tab {
    padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 8px 8px 0 0;
    color: #6b7280; border: none; background: transparent; cursor: pointer; white-space: nowrap;
    border-bottom: 2px solid transparent; transition: all .15s;
}
.rb-tab.active { color: #ea580c; border-bottom-color: #ea580c; background: #fff7ed; }
.rb-tab .rb-badge {
    display: inline-block; background: #fee2e2; color: #dc2626;
    font-size: 10px; padding: 0 5px; border-radius: 9999px; margin-left: 4px; font-weight: 700;
}
.rb-tab.empty .rb-badge { background: #f3f4f6; color: #9ca3af; }
</style>

<!-- Panel -->
<div id="rbPanel" class="space-y-2">
    <div class="text-center py-12 text-gray-400 text-sm">Loading…</div>
</div>

<script>
let rbData = {};
let rbCurrentTab = 'recipes';

const rbLabels = {
    recipes:             { label: 'Recipes',       empty: 'No deleted recipes' },
    recipe_ingredients:  { label: 'Ingredients',   empty: 'No deleted ingredients' },
    notification_emails: { label: 'Emails',         empty: 'No deleted notification emails' },
    requisition_lines:   { label: 'Order Lines',   empty: 'No deleted order lines' },
    requisition_dishes:  { label: 'Order Dishes',  empty: 'No deleted order dishes' },
};

async function rbLoad() {
    document.getElementById('rbPanel').innerHTML = '<div class="text-center py-12 text-gray-400 text-sm">Loading…</div>';
    try {
        rbData = await api('api/recycle.php?action=list');
        // Update badge counts on tabs
        Object.keys(rbLabels).forEach(type => {
            const tab = document.getElementById('tab-' + type);
            if (!tab) return;
            const count = (rbData[type] || []).length;
            const badge = count > 0 ? `<span class="rb-badge">${count}</span>` : `<span class="rb-badge">0</span>`;
            tab.innerHTML = rbLabels[type].label + badge;
            tab.classList.toggle('empty', count === 0);
        });
        rbRender();
    } catch(e) {
        document.getElementById('rbPanel').innerHTML =
            `<div class="text-center py-10 text-red-400 text-sm">Failed to load — ${escHtml(e.message || 'error')}</div>`;
    }
}

function rbTab(type) {
    rbCurrentTab = type;
    document.querySelectorAll('.rb-tab').forEach(t => t.classList.remove('active'));
    const tab = document.getElementById('tab-' + type);
    if (tab) tab.classList.add('active');
    rbRender();
}

function rbRender() {
    const rows = rbData[rbCurrentTab] || [];
    const panel = document.getElementById('rbPanel');
    if (!rows.length) {
        panel.innerHTML = `<div class="text-center py-12 text-gray-400 text-sm">✓ ${rbLabels[rbCurrentTab].empty}</div>`;
        return;
    }
    panel.innerHTML = rows.map(r => rbCard(rbCurrentTab, r)).join('');
}

function rbCard(type, r) {
    const when = r.deleted_at ? new Date(r.deleted_at).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '';
    const who  = r.deleted_by_name || 'Unknown';

    let title = '', sub = '';
    if (type === 'recipes') {
        title = escHtml(r.name);
        sub   = `${escHtml(r.category || '')} · ${r.servings} pax`;
    } else if (type === 'recipe_ingredients') {
        title = escHtml(r.item_name);
        sub   = `${r.qty} ${escHtml(r.uom)} · from recipe: ${escHtml(r.recipe_name || '—')}`;
    } else if (type === 'notification_emails') {
        title = escHtml(r.name);
        sub   = `${escHtml(r.email)} · ${escHtml(r.notify_on)} · ${r.kitchen_name ? escHtml(r.kitchen_name) : 'All kitchens'}`;
    } else if (type === 'requisition_lines') {
        title = escHtml(r.item_name);
        sub   = `${r.order_qty} ${escHtml(r.uom)} · Order #${r.requisition_id} ${escHtml(r.meals||'')} ${escHtml(r.req_date||'')} · ${escHtml(r.kitchen_name||'')}`;
    } else if (type === 'requisition_dishes') {
        title = escHtml(r.recipe_name || '—');
        sub   = `Order #${r.requisition_id} ${escHtml(r.meals||'')} ${escHtml(r.req_date||'')} · ${escHtml(r.kitchen_name||'')}`;
    }

    return `
    <div class="bg-white border border-gray-100 rounded-xl px-4 py-3 shadow-sm flex items-center justify-between gap-3">
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-gray-800">${title}</div>
            <div class="text-xs text-gray-400 mt-0.5">${sub}</div>
            <div class="text-[10px] text-red-400 mt-1">🗑 Deleted by ${escHtml(who)} · ${when}</div>
        </div>
        <button onclick="rbRestore('${type}', ${r.id})"
            class="shrink-0 bg-green-50 text-green-700 hover:bg-green-100 text-[11px] font-semibold px-3 py-1.5 rounded-lg transition compact-btn">
            Restore
        </button>
    </div>`;
}

async function rbRestore(type, id) {
    try {
        await api('api/recycle.php', { method: 'POST', body: { action: 'restore', type, id } });
        showToast('Restored ✓');
        // Remove from local data and re-render
        rbData[type] = (rbData[type] || []).filter(r => r.id !== id);
        // Update badge
        const tab = document.getElementById('tab-' + type);
        if (tab) {
            const count = rbData[type].length;
            const badge = `<span class="rb-badge${count === 0 ? '' : ''}">${count}</span>`;
            tab.innerHTML = rbLabels[type].label + badge;
            tab.classList.toggle('empty', count === 0);
        }
        rbRender();
    } catch(e) {
        showToast(e.message || 'Failed to restore', 'error');
    }
}

// Load on page open
rbLoad();
</script>
