<?php if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; } ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5" id="rpSubtitle">Select a tab and press Load</p>
    </div>
    <button onclick="rpLoad()" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 compact-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
        Load
    </button>
</div>

<!-- Filters card -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 mb-3 space-y-2">
    <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-1.5 flex-1 min-w-0">
            <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider shrink-0">From</label>
            <input type="date" id="rpDateFrom" class="flex-1 min-w-0 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
        <div class="flex items-center gap-1.5 flex-1 min-w-0">
            <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider shrink-0">To</label>
            <input type="date" id="rpDateTo" class="flex-1 min-w-0 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>
    </div>
    <!-- Kitchen filter — only shown for Order Summary tab -->
    <div class="flex items-center gap-2" id="rpKitchenRow">
        <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider shrink-0">Camp</label>
        <select id="rpKitchenSelect" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-slate-300">
            <option value="">All Camps</option>
        </select>
    </div>
</div>

<!-- Tab pills -->
<div class="flex gap-1.5 mb-3 overflow-x-auto pb-1">
    <button onclick="rpSetTab('summary')"  id="rpTabSummary" class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 text-white transition">📋 Order Summary</button>
    <button onclick="rpSetTab('items')"    id="rpTabItems"   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">🔝 Top Items</button>
    <button onclick="rpSetTab('waste')"    id="rpTabWaste"   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition">♻️ Waste Tracker</button>
</div>

<!-- Tab: Order Summary -->
<div id="rpPaneSummary">
    <div class="flex items-center justify-between mb-2">
        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Results</span>
        <button onclick="rpExportSummaryCSV()" class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition compact-btn">⬇ Export CSV</button>
    </div>
    <div id="rpSummaryContent">
        <div class="text-center py-10 text-gray-300 text-sm">Press Load to fetch data</div>
    </div>
</div>

<!-- Tab: Top Items -->
<div id="rpPaneItems" class="hidden">
    <div class="flex items-center justify-between mb-2">
        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Results</span>
        <button onclick="rpExportItemsCSV()" class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition compact-btn">⬇ Export CSV</button>
    </div>
    <div id="rpItemsContent">
        <div class="text-center py-10 text-gray-300 text-sm">Press Load to fetch data</div>
    </div>
</div>

<!-- Tab: Waste Tracker -->
<div id="rpPaneWaste" class="hidden">
    <div class="flex items-center justify-between mb-2">
        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Results</span>
        <button onclick="rpExportWasteCSV()" class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition compact-btn">⬇ Export CSV</button>
    </div>
    <div id="rpWasteContent">
        <div class="text-center py-10 text-gray-300 text-sm">Press Load to fetch data</div>
    </div>
</div>

<script>
let rpTab       = 'summary';
let rpKitchens  = [];
let rpSummaryRows = [];
let rpItemsList   = [];
let rpWasteList   = [];

const rpStatusBadge = {
    draft:      'bg-gray-100 text-gray-600',
    processing: 'bg-blue-100 text-blue-700',
    submitted:  'bg-orange-100 text-orange-700',
    fulfilled:  'bg-green-100 text-green-700',
    received:   'bg-teal-100 text-teal-700',
    closed:     'bg-slate-100 text-slate-600',
};

// ── Init dates (last 30 days) ──
(function () {
    const today = new Date();
    const pad   = n => String(n).padStart(2, '0');
    const fmt   = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const past  = new Date(today);
    past.setDate(today.getDate() - 30);
    document.getElementById('rpDateFrom').value = fmt(past);
    document.getElementById('rpDateTo').value   = fmt(today);
})();

// ── Load kitchens for filter ──
(async () => {
    try {
        const kd = await api('api/kitchens.php?action=list');
        rpKitchens = kd.kitchens || [];
        const sel  = document.getElementById('rpKitchenSelect');
        rpKitchens.forEach(k => {
            const opt = document.createElement('option');
            opt.value = k.id;
            opt.textContent = k.name;
            sel.appendChild(opt);
        });
    } catch (e) { /* non-fatal */ }
})();

// ── Tab switching ──
function rpSetTab(tab) {
    rpTab = tab;

    // Update pill styles
    ['summary', 'items', 'waste'].forEach(t => {
        const btn = document.getElementById('rpTab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) {
            btn.className = btn.className
                .replace('bg-slate-800 text-white', 'bg-gray-100 text-gray-600')
                .replace('bg-gray-100 text-gray-600', 'bg-gray-100 text-gray-600');
        }
    });
    const active = document.getElementById('rpTab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (active) {
        active.className = active.className
            .replace('bg-gray-100 text-gray-600', 'bg-slate-800 text-white');
    }

    // Show/hide panels
    document.getElementById('rpPaneSummary').classList.toggle('hidden', tab !== 'summary');
    document.getElementById('rpPaneItems').classList.toggle('hidden',   tab !== 'items');
    document.getElementById('rpPaneWaste').classList.toggle('hidden',   tab !== 'waste');

    // Show kitchen filter only on Order Summary
    document.getElementById('rpKitchenRow').classList.toggle('hidden', tab !== 'summary');

    rpLoad();
}

// Fix initial active pill (re-apply since classList.replace doesn't error on miss)
(function () {
    const btn = document.getElementById('rpTabSummary');
    if (btn) btn.className = btn.className.replace('bg-gray-100 text-gray-600', 'bg-slate-800 text-white');
    document.getElementById('rpKitchenRow').classList.remove('hidden');
})();

// ── Main load dispatcher ──
async function rpLoad() {
    const from    = document.getElementById('rpDateFrom').value;
    const to      = document.getElementById('rpDateTo').value;
    const kitchen = document.getElementById('rpKitchenSelect').value;

    if (rpTab === 'summary') {
        const el = document.getElementById('rpSummaryContent');
        el.innerHTML = '<div class="text-center py-10 text-gray-300 text-sm">Loading…</div>';
        try {
            let url = `api/reports.php?action=order_summary&date_from=${encodeURIComponent(from)}&date_to=${encodeURIComponent(to)}`;
            if (kitchen) url += `&kitchen_id=${encodeURIComponent(kitchen)}`;
            const data = await api(url);
            rpSummaryRows = data.rows || [];
            rpRenderSummary(rpSummaryRows);
        } catch (e) {
            el.innerHTML = '<div class="text-center py-10 text-red-400 text-sm">Failed to load</div>';
            showToast(e.message || 'Failed to load order summary', 'error');
        }

    } else if (rpTab === 'items') {
        const el = document.getElementById('rpItemsContent');
        el.innerHTML = '<div class="text-center py-10 text-gray-300 text-sm">Loading…</div>';
        try {
            const data = await api(`api/reports.php?action=top_items&date_from=${encodeURIComponent(from)}&date_to=${encodeURIComponent(to)}`);
            rpItemsList = data.items || [];
            rpRenderItems(rpItemsList);
        } catch (e) {
            el.innerHTML = '<div class="text-center py-10 text-red-400 text-sm">Failed to load</div>';
            showToast(e.message || 'Failed to load top items', 'error');
        }

    } else if (rpTab === 'waste') {
        const el = document.getElementById('rpWasteContent');
        el.innerHTML = '<div class="text-center py-10 text-gray-300 text-sm">Loading…</div>';
        try {
            const data = await api(`api/reports.php?action=waste_summary&date_from=${encodeURIComponent(from)}&date_to=${encodeURIComponent(to)}`);
            rpWasteList = data.kitchens || [];
            rpRenderWaste(rpWasteList);
        } catch (e) {
            el.innerHTML = '<div class="text-center py-10 text-red-400 text-sm">Failed to load</div>';
            showToast(e.message || 'Failed to load waste summary', 'error');
        }
    }
}

// ── Tab 1: Order Summary ──
function rpRenderSummary(rows) {
    const el = document.getElementById('rpSummaryContent');
    document.getElementById('rpSubtitle').textContent = `${rows.length} row${rows.length !== 1 ? 's' : ''}`;

    if (!rows.length) {
        el.innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No orders in this period</div>';
        return;
    }

    // Totals
    let totOrdered = 0, totFulfilled = 0, totWasted = 0, totGuests = 0;
    rows.forEach(r => {
        totOrdered   += parseFloat(r.total_kg_ordered   || 0);
        totFulfilled += parseFloat(r.total_kg_fulfilled || 0);
        totWasted    += parseFloat(r.total_kg_wasted    || 0);
        totGuests    += parseInt(r.guest_count          || 0);
    });

    const fmtDate = d => {
        if (!d) return '—';
        const dt = new Date(d + 'T00:00:00');
        return dt.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    };
    const fmtN = n => parseFloat(n || 0).toFixed(1);

    const rows_html = rows.map(r => {
        const badge = rpStatusBadge[r.status] || 'bg-gray-100 text-gray-600';
        const label = r.status ? r.status.charAt(0).toUpperCase() + r.status.slice(1) : '—';
        return `<tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
            <td class="py-2 pr-2 text-xs text-gray-700 whitespace-nowrap">${escHtml(fmtDate(r.req_date))}</td>
            <td class="py-2 px-2 text-xs text-gray-700">${escHtml(r.kitchen_name || '—')}</td>
            <td class="py-2 px-2 text-xs text-gray-600 capitalize">${escHtml(r.meals || '—')}</td>
            <td class="py-2 px-2 text-xs"><span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ${badge}">${label}</span></td>
            <td class="py-2 px-2 text-xs text-right text-gray-600">${parseInt(r.guest_count || 0)}</td>
            <td class="py-2 px-2 text-xs text-right text-gray-600">${fmtN(r.total_kg_ordered)}</td>
            <td class="py-2 px-2 text-xs text-right text-green-700">${fmtN(r.total_kg_fulfilled)}</td>
            <td class="py-2 pl-2 text-xs text-right text-red-500">${fmtN(r.total_kg_wasted)}</td>
        </tr>`;
    }).join('');

    el.innerHTML = `
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left text-[9px] font-semibold text-gray-400 uppercase px-0 py-2 pr-2 pl-4">Date</th>
                            <th class="text-left text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Camp</th>
                            <th class="text-left text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Meal</th>
                            <th class="text-left text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Status</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Guests</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Ordered</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Fulfilled</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2 pr-4">Wasted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 [&>tr]:px-4">
                        ${rows_html}
                        <tr class="bg-gray-50 border-t border-gray-200 font-semibold">
                            <td colspan="4" class="py-2 pl-4 text-xs text-gray-500">Totals</td>
                            <td class="py-2 px-2 text-xs text-right text-gray-700">${totGuests}</td>
                            <td class="py-2 px-2 text-xs text-right text-gray-700">${totOrdered.toFixed(1)}</td>
                            <td class="py-2 px-2 text-xs text-right text-green-700">${totFulfilled.toFixed(1)}</td>
                            <td class="py-2 pl-2 pr-4 text-xs text-right text-red-500">${totWasted.toFixed(1)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// ── Tab 2: Top Items ──
function rpRenderItems(items) {
    const el = document.getElementById('rpItemsContent');
    document.getElementById('rpSubtitle').textContent = `${items.length} item${items.length !== 1 ? 's' : ''}`;

    if (!items.length) {
        el.innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No items in this period</div>';
        return;
    }

    const fmtN = n => parseFloat(n || 0).toFixed(1);
    const wastePctClass = pct => {
        const p = parseFloat(pct || 0);
        if (p > 20) return 'text-red-600 font-semibold';
        if (p > 10) return 'text-amber-600 font-semibold';
        return 'text-green-600 font-semibold';
    };

    const rows_html = items.map((item, i) => {
        const pct = parseFloat(item.waste_pct || 0);
        return `<tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
            <td class="py-2 pl-4 pr-2 text-xs text-gray-400">${i + 1}</td>
            <td class="py-2 px-2 text-xs font-medium text-gray-800">${escHtml(item.item_name || '—')}</td>
            <td class="py-2 px-2 text-xs text-gray-500">${escHtml(item.uom || '—')}</td>
            <td class="py-2 px-2 text-xs text-right text-gray-600">${parseInt(item.times_ordered || 0)}</td>
            <td class="py-2 px-2 text-xs text-right text-gray-700 font-medium">${fmtN(item.total_ordered)}</td>
            <td class="py-2 px-2 text-xs text-right text-green-700">${fmtN(item.total_fulfilled)}</td>
            <td class="py-2 px-2 text-xs text-right text-red-500">${fmtN(item.total_wasted)}</td>
            <td class="py-2 pl-2 pr-4 text-xs text-right ${wastePctClass(pct)}">${item.waste_pct !== null ? pct + '%' : '—'}</td>
        </tr>`;
    }).join('');

    el.innerHTML = `
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left text-[9px] font-semibold text-gray-400 uppercase pl-4 pr-2 py-2">#</th>
                            <th class="text-left text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Item</th>
                            <th class="text-left text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">UOM</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Orders</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Ordered</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Fulfilled</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2">Wasted</th>
                            <th class="text-right text-[9px] font-semibold text-gray-400 uppercase px-2 py-2 pr-4">Waste %</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows_html}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// ── Tab 3: Waste Tracker ──
function rpRenderWaste(kitchens) {
    const el = document.getElementById('rpWasteContent');
    document.getElementById('rpSubtitle').textContent = `${kitchens.length} camp${kitchens.length !== 1 ? 's' : ''}`;

    if (!kitchens.length) {
        el.innerHTML = '<div class="text-center py-10 text-gray-400 text-sm">No data in this period</div>';
        return;
    }

    const fmtN = n => parseFloat(n || 0).toFixed(1);

    // Grand total card
    const totalWasted    = kitchens.reduce((s, k) => s + parseFloat(k.total_wasted    || 0), 0);
    const totalFulfilled = kitchens.reduce((s, k) => s + parseFloat(k.total_fulfilled || 0), 0);
    const totalOrders    = kitchens.reduce((s, k) => s + parseInt(k.total_orders      || 0), 0);
    const overallPct     = totalFulfilled > 0 ? ((totalWasted / totalFulfilled) * 100).toFixed(1) : '0.0';
    const overallColor   = parseFloat(overallPct) > 20 ? 'text-red-600' : parseFloat(overallPct) > 10 ? 'text-amber-600' : 'text-green-600';

    const summaryCard = `
        <div class="bg-white rounded-xl border border-orange-200 shadow-sm px-4 py-3 mb-3">
            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Overall Summary</div>
            <div class="grid grid-cols-3 gap-3">
                <div class="text-center">
                    <div class="text-lg font-bold text-gray-800">${fmtN(totalFulfilled)}</div>
                    <div class="text-[9px] text-gray-400">kg Fulfilled</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-red-600">${fmtN(totalWasted)}</div>
                    <div class="text-[9px] text-gray-400">kg Wasted</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold ${overallColor}">${overallPct}%</div>
                    <div class="text-[9px] text-gray-400">Waste Rate</div>
                </div>
            </div>
            <div class="mt-2 w-full bg-gray-100 rounded-full h-2">
                <div class="bg-red-500 h-2 rounded-full transition-all" style="width: ${Math.min(parseFloat(overallPct), 100)}%"></div>
            </div>
            <div class="text-[9px] text-gray-400 mt-1 text-right">${totalOrders} total orders</div>
        </div>
    `;

    const campCards = kitchens.map(k => {
        const pct       = parseFloat(k.waste_pct || 0);
        const barWidth  = Math.min(pct, 100);
        const pctColor  = pct > 20 ? 'text-red-600' : pct > 10 ? 'text-amber-600' : 'text-green-600';
        const barColor  = pct > 20 ? 'bg-red-500' : pct > 10 ? 'bg-amber-400' : 'bg-green-500';

        return `
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-gray-800">${escHtml(k.kitchen_name || '—')}</div>
                        <div class="text-[10px] text-gray-400 mt-0.5">${parseInt(k.total_orders || 0)} orders</div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-sm font-bold ${pctColor}">${k.waste_pct !== null ? pct + '%' : '—'}</div>
                        <div class="text-[9px] text-gray-400">waste rate</div>
                    </div>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
                    <div class="${barColor} h-2 rounded-full transition-all" style="width: ${barWidth}%"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-green-50 rounded-lg px-2.5 py-1.5">
                        <div class="text-xs font-bold text-green-700">${fmtN(k.total_fulfilled)}</div>
                        <div class="text-[9px] text-gray-400">kg fulfilled</div>
                    </div>
                    <div class="bg-red-50 rounded-lg px-2.5 py-1.5">
                        <div class="text-xs font-bold text-red-600">${fmtN(k.total_wasted)}</div>
                        <div class="text-[9px] text-gray-400">kg wasted</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    el.innerHTML = summaryCard + `<div class="space-y-2">${campCards}</div>`;
}

// ── CSV Export helpers ──
function rpExportCSV(headers, rows, filename) {
    const escape = v => {
        const s = String(v ?? '');
        return s.includes(',') || s.includes('"') || s.includes('\n')
            ? '"' + s.replace(/"/g, '""') + '"'
            : s;
    };
    const lines = [headers.map(escape).join(',')];
    rows.forEach(row => lines.push(row.map(escape).join(',')));
    const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function rpExportSummaryCSV() {
    if (!rpSummaryRows.length) { showToast('No data to export', 'warning'); return; }
    const headers = ['Date', 'Camp', 'Meal', 'Status', 'Guests', 'Kg Ordered', 'Kg Fulfilled', 'Kg Wasted'];
    const rows = rpSummaryRows.map(r => [
        r.req_date,
        r.kitchen_name,
        r.meals,
        r.status,
        r.guest_count,
        parseFloat(r.total_kg_ordered   || 0).toFixed(1),
        parseFloat(r.total_kg_fulfilled || 0).toFixed(1),
        parseFloat(r.total_kg_wasted    || 0).toFixed(1),
    ]);
    const from = document.getElementById('rpDateFrom').value;
    const to   = document.getElementById('rpDateTo').value;
    rpExportCSV(headers, rows, `order-summary-${from}-${to}.csv`);
}

function rpExportItemsCSV() {
    if (!rpItemsList.length) { showToast('No data to export', 'warning'); return; }
    const headers = ['#', 'Item', 'UOM', 'Times Ordered', 'Total Ordered', 'Total Fulfilled', 'Total Wasted', 'Waste %'];
    const rows = rpItemsList.map((item, i) => [
        i + 1,
        item.item_name,
        item.uom,
        item.times_ordered,
        parseFloat(item.total_ordered   || 0).toFixed(1),
        parseFloat(item.total_fulfilled || 0).toFixed(1),
        parseFloat(item.total_wasted    || 0).toFixed(1),
        item.waste_pct !== null ? item.waste_pct : '',
    ]);
    const from = document.getElementById('rpDateFrom').value;
    const to   = document.getElementById('rpDateTo').value;
    rpExportCSV(headers, rows, `top-items-${from}-${to}.csv`);
}

function rpExportWasteCSV() {
    if (!rpWasteList.length) { showToast('No data to export', 'warning'); return; }
    const headers = ['Camp', 'Total Fulfilled (kg)', 'Total Wasted (kg)', 'Waste %', 'Total Orders'];
    const rows = rpWasteList.map(k => [
        k.kitchen_name,
        parseFloat(k.total_fulfilled || 0).toFixed(1),
        parseFloat(k.total_wasted    || 0).toFixed(1),
        k.waste_pct !== null ? k.waste_pct : '',
        k.total_orders,
    ]);
    const from = document.getElementById('rpDateFrom').value;
    const to   = document.getElementById('rpDateTo').value;
    rpExportCSV(headers, rows, `waste-tracker-${from}-${to}.csv`);
}
</script>
