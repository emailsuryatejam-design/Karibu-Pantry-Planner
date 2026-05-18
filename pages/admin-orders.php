<?php if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; } ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">All Orders</h1>
        <p class="text-xs text-gray-400 mt-0.5" id="aoCount">Loading...</p>
    </div>
    <button onclick="aoLoad()" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 compact-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
        Refresh
    </button>
</div>

<!-- Date range + Kitchen filter -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 mb-3 space-y-2">
    <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-1.5 flex-1 min-w-0">
            <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider shrink-0">From</label>
            <input type="date" id="aoDateFromInput" class="flex-1 min-w-0 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
        <div class="flex items-center gap-1.5 flex-1 min-w-0">
            <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider shrink-0">To</label>
            <input type="date" id="aoDateToInput" class="flex-1 min-w-0 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider shrink-0">Camp</label>
        <select id="aoKitchenSelect" onchange="aoSetKitchen(this.value)" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
            <option value="0">All Camps</option>
        </select>
    </div>
</div>

<!-- Status filter pills -->
<div class="flex gap-1.5 mb-3 overflow-x-auto pb-1">
    <button onclick="aoSetStatus('')"           id="aoStAll"         class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 text-white transition">All</button>
    <button onclick="aoSetStatus('draft')"      id="aoStDraft"       class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Draft</button>
    <button onclick="aoSetStatus('processing')" id="aoStProcessing"  class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Processing</button>
    <button onclick="aoSetStatus('submitted')"  id="aoStSubmitted"   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Submitted</button>
    <button onclick="aoSetStatus('fulfilled')"  id="aoStFulfilled"   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Fulfilled</button>
    <button onclick="aoSetStatus('received')"   id="aoStReceived"    class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Received</button>
    <button onclick="aoSetStatus('closed')"     id="aoStClosed"      class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">Closed</button>
</div>

<div id="aoList" class="space-y-2">
    <div class="text-center py-10 text-gray-300 text-sm">Loading...</div>
</div>

<script>
let aoReqs      = [];
let aoKitchens  = [];
let aoStatus    = '';
let aoKitchenId = 0;
let aoDateFrom  = '';
let aoDateTo    = '';
let aoExpanded  = {};   // id -> true/false
let aoLines     = {};   // id -> lines array or 'loading'

const aoStatusBadge = {
    draft:      'bg-gray-100 text-gray-600',
    processing: 'bg-blue-100 text-blue-700',
    submitted:  'bg-orange-100 text-orange-700',
    fulfilled:  'bg-green-100 text-green-700',
    received:   'bg-teal-100 text-teal-700',
    closed:     'bg-slate-100 text-slate-600',
};

function aoFmtDate(dateStr) {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
}

function aoDefaultDates() {
    const today = new Date();
    const pad   = n => String(n).padStart(2, '0');
    const fmt   = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
    const past  = new Date(today); past.setDate(today.getDate() - 7);
    return { from: fmt(past), to: fmt(today) };
}

// Init dates
(function() {
    const { from, to } = aoDefaultDates();
    aoDateFrom = from;
    aoDateTo   = to;
    document.getElementById('aoDateFromInput').value = from;
    document.getElementById('aoDateToInput').value   = to;
    document.getElementById('aoDateFromInput').addEventListener('change', e => { aoDateFrom = e.target.value; aoLoad(); });
    document.getElementById('aoDateToInput').addEventListener('change',   e => { aoDateTo   = e.target.value; aoLoad(); });
})();

// Load kitchens then orders
(async () => {
    try {
        const kd = await api('api/kitchens.php?action=list');
        aoKitchens = kd.kitchens || [];
        const sel  = document.getElementById('aoKitchenSelect');
        aoKitchens.forEach(k => {
            const opt = document.createElement('option');
            opt.value = k.id;
            opt.textContent = k.name;
            sel.appendChild(opt);
        });
    } catch(e) { /* non-fatal */ }
    aoLoad();
})();

function aoSetStatus(st) {
    aoStatus = st;
    document.querySelectorAll('[id^="aoSt"]').forEach(b => {
        b.className = b.className.replace('bg-slate-800 text-white', 'bg-gray-100 text-gray-600');
    });
    const map = { '': 'All', draft: 'Draft', processing: 'Processing', submitted: 'Submitted', fulfilled: 'Fulfilled', received: 'Received', closed: 'Closed' };
    const active = document.getElementById('aoSt' + map[st]);
    if (active) active.className = active.className.replace('bg-gray-100 text-gray-600', 'bg-slate-800 text-white');
    aoRender();
}

function aoSetKitchen(val) {
    aoKitchenId = parseInt(val) || 0;
    aoLoad();
}

async function aoLoad() {
    document.getElementById('aoList').innerHTML = '<div class="text-center py-10 text-gray-300 text-sm">Loading...</div>';
    aoExpanded = {};
    aoLines    = {};
    try {
        let url = `api/requisitions.php?action=admin_list&date_from=${encodeURIComponent(aoDateFrom)}&date_to=${encodeURIComponent(aoDateTo)}`;
        if (aoKitchenId) url += `&kitchen_id=${aoKitchenId}`;
        const data = await api(url);
        aoReqs = data.requisitions || [];
        aoRender();
    } catch(e) {
        showToast(e.message || 'Failed to load orders', 'error');
        document.getElementById('aoList').innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">Failed to load</div>';
    }
}

function aoRender() {
    const filtered = aoStatus ? aoReqs.filter(r => r.status === aoStatus) : aoReqs;
    document.getElementById('aoCount').textContent = `${filtered.length} order${filtered.length !== 1 ? 's' : ''}`;

    if (!filtered.length) {
        document.getElementById('aoList').innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No orders found</div>';
        return;
    }

    document.getElementById('aoList').innerHTML = filtered.map(r => {
        const badge   = aoStatusBadge[r.status] || 'bg-gray-100 text-gray-600';
        const label   = r.status.charAt(0).toUpperCase() + r.status.slice(1);
        const isSupp  = parseInt(r.supplement_number) > 1;
        const mealLbl = escHtml(r.meals || '—');
        const expanded = aoExpanded[r.id];

        const actionBtns = (() => {
            const st = r.status;
            const btns = [];
            if (st === 'submitted' || st === 'fulfilled' || st === 'received') {
                btns.push(`<button onclick="event.stopPropagation();aoForceClose(${r.id})" class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition compact-btn">Force Close</button>`);
            }
            if (st === 'closed') {
                btns.push(`<button onclick="event.stopPropagation();aoReopen(${r.id})" class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-green-200 text-green-600 hover:bg-green-50 transition compact-btn">Reopen</button>`);
            }
            return btns.join('');
        })();

        const linesHtml = (() => {
            if (!expanded) return '';
            const lines = aoLines[r.id];
            if (!lines || lines === 'loading') {
                return `<div class="mt-3 pt-3 border-t border-gray-100 text-center text-xs text-gray-300 py-2">Loading items…</div>`;
            }
            if (!lines.length) {
                return `<div class="mt-3 pt-3 border-t border-gray-100 text-center text-xs text-gray-400 py-2">No line items</div>`;
            }
            const rows = lines.map(l => `
                <tr class="border-b border-gray-50 last:border-0">
                    <td class="py-1.5 pr-2 text-xs text-gray-700">${escHtml(l.item_name || '—')}</td>
                    <td class="py-1.5 px-2 text-xs text-right text-gray-600">${l.order_qty ?? '—'}</td>
                    <td class="py-1.5 px-2 text-xs text-right text-gray-600">${l.fulfilled_qty ?? '—'}</td>
                    <td class="py-1.5 pl-2 text-xs text-right text-gray-600">${l.received_qty ?? '—'}</td>
                    <td class="py-1.5 pl-2 text-xs text-right text-gray-400">${escHtml(l.uom || '')}</td>
                </tr>
            `).join('');
            return `
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="text-left text-[9px] font-semibold text-gray-400 uppercase pb-1">Item</th>
                                <th class="text-right text-[9px] font-semibold text-gray-400 uppercase pb-1 px-2">Ord</th>
                                <th class="text-right text-[9px] font-semibold text-gray-400 uppercase pb-1 px-2">Fulfil</th>
                                <th class="text-right text-[9px] font-semibold text-gray-400 uppercase pb-1 pl-2">Rcvd</th>
                                <th class="text-right text-[9px] font-semibold text-gray-400 uppercase pb-1 pl-2">UOM</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        })();

        return `
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 cursor-pointer" onclick="aoExpand(${r.id})">
                <div class="flex items-start justify-between gap-3">
                    <!-- Left: date + kitchen + meal -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-bold text-gray-800">${escHtml(aoFmtDate(r.req_date))}</span>
                            ${isSupp ? `<span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700">Supp #${r.supplement_number}</span>` : ''}
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${badge}">${label}</span>
                        </div>
                        <div class="text-[10px] text-gray-500 mt-0.5">
                            🏕️ ${escHtml(r.kitchen_name || '—')} · ${mealLbl}
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-[10px] text-gray-400">
                            <span>${r.guest_count ?? 0} guests</span>
                            <span>${r.line_count ?? 0} items</span>
                            ${r.chef_name ? `<span>by ${escHtml(r.chef_name)}</span>` : ''}
                        </div>
                    </div>
                    <!-- Right: action buttons + chevron -->
                    <div class="flex items-center gap-2 shrink-0">
                        ${actionBtns}
                        <span class="text-gray-300 transition-transform ${expanded ? 'rotate-180' : ''}" style="display:inline-block">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </div>
                </div>
                ${linesHtml}
            </div>
        `;
    }).join('');
}

async function aoExpand(id) {
    aoExpanded[id] = !aoExpanded[id];
    if (aoExpanded[id] && aoLines[id] === undefined) {
        aoLines[id] = 'loading';
        aoRender();
        try {
            const data = await api(`api/requisitions.php?action=get&id=${id}`);
            aoLines[id] = data.lines || [];
        } catch(e) {
            aoLines[id] = [];
            showToast('Failed to load line items', 'error');
        }
    }
    aoRender();
}

async function aoForceClose(id) {
    if (!confirm('Force-close this order? Unfilled received quantities will be copied from fulfilled quantities.')) return;
    try {
        await api('api/requisitions.php', { method: 'POST', body: { action: 'admin_close', requisition_id: id } });
        showToast('Order closed', 'success');
        aoLoad();
    } catch(e) {
        showToast(e.message || 'Failed to close order', 'error');
    }
}

async function aoReopen(id) {
    if (!confirm('Reopen this order? Status will be set back to Submitted.')) return;
    try {
        await api('api/requisitions.php', { method: 'POST', body: { action: 'admin_reopen', requisition_id: id } });
        showToast('Order reopened', 'success');
        aoLoad();
    } catch(e) {
        showToast(e.message || 'Failed to reopen order', 'error');
    }
}
</script>
