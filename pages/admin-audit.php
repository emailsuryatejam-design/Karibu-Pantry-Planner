<?php if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; } ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Audit Log</h1>
        <p class="text-xs text-gray-400 mt-0.5" id="alSubtitle">Loading...</p>
    </div>
</div>

<!-- Filter bar -->
<div class="flex gap-2 mb-3 overflow-x-auto pb-1 items-center">
    <input
        type="text"
        id="alSearch"
        placeholder="Search action or user..."
        class="shrink-0 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300 w-44"
        onkeydown="if(event.key==='Enter') alLoad(1)"
    >
    <select id="alEntity" class="shrink-0 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
        <option value="">All Entities</option>
    </select>
    <select id="alUser" class="shrink-0 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
        <option value="">All Users</option>
    </select>
    <input
        type="date"
        id="alDateFrom"
        class="shrink-0 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300"
    >
    <input
        type="date"
        id="alDateTo"
        class="shrink-0 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300"
    >
    <button
        onclick="alLoad(1)"
        class="shrink-0 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-xs font-semibold transition"
    >Search</button>
</div>

<!-- Log entries -->
<div id="alList" class="space-y-2">
    <div class="text-center py-10 text-gray-300 text-sm">Loading...</div>
</div>

<!-- Pagination -->
<div id="alPagination" class="flex items-center justify-center gap-3 mt-4 hidden">
    <button
        id="alPrevBtn"
        onclick="alLoad(alCurrentPage - 1)"
        class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
    >Prev</button>
    <span id="alPageLabel" class="text-xs text-gray-500"></span>
    <button
        id="alNextBtn"
        onclick="alLoad(alCurrentPage + 1)"
        class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
    >Next</button>
</div>

<script>
let alCurrentPage = 1;
let alTotalPages  = 1;
let alExpanded    = {};

// Entity dot colours
const alEntityColor = {
    requisitions: 'bg-orange-400',
    recipes:      'bg-green-400',
    items:        'bg-blue-400',
    users:        'bg-slate-400',
};

const alEntityBadge = {
    requisitions: 'bg-orange-100 text-orange-700',
    recipes:      'bg-green-100 text-green-700',
    items:        'bg-blue-100 text-blue-700',
    users:        'bg-slate-100 text-slate-700',
};

function rpRelTime(ts) {
    const d    = new Date(ts);
    const now  = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60)     return 'just now';
    if (diff < 3600)   return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return d.toLocaleDateString('en-GB', { weekday: 'short', hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function alFmtAction(action) {
    return (action || '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

async function alInitFilters() {
    try {
        const [ed, ud] = await Promise.all([
            api('api/audit.php?action=entities'),
            api('api/audit.php?action=users'),
        ]);

        const entitySel = document.getElementById('alEntity');
        (ed.entities || []).forEach(e => {
            const opt = document.createElement('option');
            opt.value = e;
            opt.textContent = alFmtAction(e);
            entitySel.appendChild(opt);
        });

        const userSel = document.getElementById('alUser');
        (ud.users || []).forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.user_id;
            opt.textContent = escHtml(u.user_name);
            userSel.appendChild(opt);
        });
    } catch(e) {
        showToast('Failed to load filters', 'error');
    }
}

async function alLoad(page = 1) {
    alCurrentPage = page;

    const search   = document.getElementById('alSearch').value.trim();
    const entity   = document.getElementById('alEntity').value;
    const userId   = document.getElementById('alUser').value;
    const dateFrom = document.getElementById('alDateFrom').value;
    const dateTo   = document.getElementById('alDateTo').value;

    const params = new URLSearchParams({ action: 'list', page });
    if (search)   params.set('search',    search);
    if (entity)   params.set('entity',    entity);
    if (userId)   params.set('user_id',   userId);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo)   params.set('date_to',   dateTo);

    document.getElementById('alList').innerHTML =
        '<div class="text-center py-10 text-gray-300 text-sm">Loading...</div>';

    try {
        const data = await api('api/audit.php?' + params.toString());
        alRender(data);
    } catch(e) {
        showToast('Failed to load audit log', 'error');
        document.getElementById('alList').innerHTML =
            '<div class="text-center py-10 text-red-400 text-sm">Failed to load entries</div>';
    }
}

function alRender(data) {
    alTotalPages = data.pages || 1;

    document.getElementById('alSubtitle').textContent =
        `${data.total.toLocaleString()} total entr${data.total !== 1 ? 'ies' : 'y'} · Page ${data.page} of ${data.pages}`;

    const rows = data.rows || [];

    if (!rows.length) {
        document.getElementById('alList').innerHTML =
            '<div class="text-center py-10 text-gray-400 text-sm">No entries found</div>';
        document.getElementById('alPagination').classList.add('hidden');
        return;
    }

    document.getElementById('alList').innerHTML = rows.map(r => {
        const dotCls    = alEntityColor[r.entity]  || 'bg-gray-400';
        const badgeCls  = alEntityBadge[r.entity]  || 'bg-gray-100 text-gray-600';
        const expanded  = !!alExpanded[r.id];
        const hasValues = r.old_value || r.new_value;

        let oldJson = '';
        let newJson = '';
        try { oldJson = r.old_value ? JSON.stringify(JSON.parse(r.old_value), null, 2) : ''; } catch { oldJson = r.old_value || ''; }
        try { newJson = r.new_value ? JSON.stringify(JSON.parse(r.new_value), null, 2) : ''; } catch { newJson = r.new_value || ''; }

        return `
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
            <div class="flex items-start gap-3">
                <div class="mt-1.5 shrink-0 w-2.5 h-2.5 rounded-full ${dotCls}"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-800">${escHtml(alFmtAction(r.action))}</span>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${badgeCls}">${escHtml(r.entity || '—')}</span>
                        ${r.entity_id ? `<span class="text-[10px] text-gray-400">#${escHtml(String(r.entity_id))}</span>` : ''}
                    </div>
                    <div class="text-[11px] text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
                        <span>${escHtml(r.user_name || 'System')}</span>
                        <span class="text-gray-200">&middot;</span>
                        <span title="${escHtml(r.created_at)}">${rpRelTime(r.created_at)}</span>
                    </div>
                    ${hasValues ? `
                    <button
                        onclick="alExpand(${r.id})"
                        class="mt-1.5 text-[10px] text-slate-500 hover:text-slate-700 font-medium flex items-center gap-1 transition"
                        id="alToggle${r.id}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="transition-transform ${expanded ? 'rotate-180' : ''}" id="alChevron${r.id}"><path d="m6 9 6 6 6-6"/></svg>
                        ${expanded ? 'Hide' : 'Show'} changes
                    </button>
                    <div id="alDetail${r.id}" class="${expanded ? '' : 'hidden'} mt-2 space-y-2">
                        ${oldJson ? `
                        <div>
                            <div class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Before</div>
                            <pre class="text-[10px] font-mono bg-red-50 text-red-800 rounded-lg px-3 py-2 overflow-x-auto whitespace-pre-wrap break-all">${escHtml(oldJson)}</pre>
                        </div>` : ''}
                        ${newJson ? `
                        <div>
                            <div class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider mb-1">After</div>
                            <pre class="text-[10px] font-mono bg-green-50 text-green-800 rounded-lg px-3 py-2 overflow-x-auto whitespace-pre-wrap break-all">${escHtml(newJson)}</pre>
                        </div>` : ''}
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>`;
    }).join('');

    // Pagination
    const pg = document.getElementById('alPagination');
    pg.classList.remove('hidden');
    document.getElementById('alPageLabel').textContent = `Page ${data.page} of ${data.pages}`;
    document.getElementById('alPrevBtn').disabled = data.page <= 1;
    document.getElementById('alNextBtn').disabled = data.page >= data.pages;
}

function alExpand(id) {
    alExpanded[id] = !alExpanded[id];
    const detail  = document.getElementById('alDetail' + id);
    const chevron = document.getElementById('alChevron' + id);
    const toggle  = document.getElementById('alToggle' + id);

    if (alExpanded[id]) {
        detail.classList.remove('hidden');
        chevron.classList.add('rotate-180');
        toggle.innerHTML = toggle.innerHTML.replace('Show changes', 'Hide changes');
    } else {
        detail.classList.add('hidden');
        chevron.classList.remove('rotate-180');
        toggle.innerHTML = toggle.innerHTML.replace('Hide changes', 'Show changes');
    }
}

// Initialise
(async () => {
    await alInitFilters();
    alLoad(1);
})();
</script>
