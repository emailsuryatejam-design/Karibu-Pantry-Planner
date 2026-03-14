<?php
/**
 * Karibu Pantry Planner — Reports
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Reports</h2>
<p class="text-xs text-gray-500 mb-3">Order analytics and summaries</p>

<!-- Date Range -->
<div class="flex items-center gap-2 mb-3">
    <input type="date" id="rpDateFrom" class="text-xs border border-gray-200 rounded-lg px-3 py-2 bg-white flex-1" onchange="rpLoad()">
    <span class="text-xs text-gray-400">to</span>
    <input type="date" id="rpDateTo" class="text-xs border border-gray-200 rounded-lg px-3 py-2 bg-white flex-1" onchange="rpLoad()">
</div>

<!-- Quick Ranges -->
<div class="flex gap-2 mb-4">
    <button onclick="rpSetRange(0)" class="text-[10px] font-medium px-2.5 py-1 rounded-full bg-orange-100 text-orange-600">Today</button>
    <button onclick="rpSetRange(7)" class="text-[10px] font-medium px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">Last 7 days</button>
    <button onclick="rpSetRange(30)" class="text-[10px] font-medium px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">Last 30 days</button>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white border border-gray-200 rounded-xl p-3">
        <div class="text-2xl font-bold text-gray-800" id="rpTotalSessions">—</div>
        <div class="text-[10px] text-gray-400">Total Requisitions</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-3">
        <div class="text-2xl font-bold text-orange-600" id="rpTotalKg">—</div>
        <div class="text-[10px] text-gray-400">Total KG Ordered</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-3">
        <div class="text-2xl font-bold text-green-600" id="rpFulfillRate">—</div>
        <div class="text-[10px] text-gray-400">Fulfillment Rate</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-3">
        <div class="text-2xl font-bold text-red-600" id="rpDisputes">—</div>
        <div class="text-[10px] text-gray-400">Disputes</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-3">
        <div class="text-2xl font-bold text-blue-600" id="rpConsumed">—</div>
        <div class="text-[10px] text-gray-400">Consumed (kg)</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-3">
        <div class="text-2xl font-bold text-amber-500" id="rpUnused">—</div>
        <div class="text-[10px] text-gray-400">Unused (kg)</div>
    </div>
</div>

<!-- By Meal Type -->
<h3 class="text-sm font-semibold text-gray-700 mb-2">By Meal Type</h3>
<div id="rpByMeal" class="space-y-1 mb-4"></div>

<!-- Requisition Details (expandable) -->
<h3 class="text-sm font-semibold text-gray-700 mb-2">Requisition Details</h3>
<div id="rpReqList" class="space-y-2 mb-4"></div>

<!-- Top Items -->
<h3 class="text-sm font-semibold text-gray-700 mb-2">Most Ordered Items</h3>
<div id="rpTopItems" class="space-y-1 mb-4"></div>

<!-- Stock Discrepancies -->
<h3 class="text-sm font-semibold text-gray-700 mb-2">Stock Adjustments</h3>
<div id="rpDiscrepancies" class="space-y-2"></div>

<script>
const RP_KID = <?= (int)$kitchenId ?>;

const rpMealColors = {
    breakfast: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200', bar: 'bg-amber-400', dot: 'bg-amber-500' },
    lunch:     { bg: 'bg-blue-50',  text: 'text-blue-700',  border: 'border-blue-200',  bar: 'bg-blue-400',  dot: 'bg-blue-500' },
    dinner:    { bg: 'bg-purple-50',text: 'text-purple-700',border: 'border-purple-200',bar: 'bg-purple-400',dot: 'bg-purple-500' },
    lunchboxes:{ bg: 'bg-green-50', text: 'text-green-700', border: 'border-green-200', bar: 'bg-green-400', dot: 'bg-green-500' },
    picnics:   { bg: 'bg-rose-50',  text: 'text-rose-700',  border: 'border-rose-200',  bar: 'bg-rose-400',  dot: 'bg-rose-500' }
};

const rpStatusBadge = {
    draft:     'bg-gray-100 text-gray-500',
    submitted: 'bg-blue-100 text-blue-600',
    processing:'bg-amber-100 text-amber-600',
    fulfilled: 'bg-green-100 text-green-600',
    received:  'bg-green-100 text-green-700',
    closed:    'bg-gray-200 text-gray-500'
};

function rpMealName(code) {
    if (!code) return 'Unknown';
    return code.replace(/^./, c => c.toUpperCase());
}

document.getElementById('rpDateFrom').value = changeDate(todayStr(), -7);
document.getElementById('rpDateTo').value = todayStr();
rpLoad();

function rpSetRange(days) {
    document.getElementById('rpDateTo').value = todayStr();
    document.getElementById('rpDateFrom').value = days === 0 ? todayStr() : changeDate(todayStr(), -days);
    rpLoad();
}

async function rpLoad() {
    const from = document.getElementById('rpDateFrom').value;
    const to = document.getElementById('rpDateTo').value;

    let totalSessions = 0, totalKg = 0, totalFulfilled = 0, totalOrdered = 0, disputes = 0;
    let totalReceived = 0, totalUnused = 0;
    let allReqs = [];         // full list of requisitions with lines
    let mealTotals = {};      // { breakfast: {count, kg, unused}, ... }
    let itemTotals = {};

    // Show loading
    document.getElementById('rpByMeal').innerHTML = '<p class="text-xs text-gray-400 text-center py-2">Loading...</p>';
    document.getElementById('rpReqList').innerHTML = '<p class="text-xs text-gray-400 text-center py-2">Loading...</p>';
    document.getElementById('rpTopItems').innerHTML = '<p class="text-xs text-gray-400 text-center py-2">Loading...</p>';

    try {
        let d = from;
        while (d <= to) {
            const data = await api(`api/requisitions.php?action=day_summary&date=${d}&kitchen_id=${RP_KID}`);
            const reqs = data.requisitions || [];

            for (const r of reqs) {
                if (r.status === 'draft') continue;

                totalSessions++;
                const kg = parseFloat(r.total_kg || 0);
                totalKg += kg;
                const meal = r.meals || 'unknown';
                const chef = r.chef_name || 'Unknown';
                const isDispute = r.has_dispute == 1;
                if (isDispute) disputes++;

                // Meal type totals
                if (!mealTotals[meal]) mealTotals[meal] = { count: 0, kg: 0, unused: 0 };
                mealTotals[meal].count++;
                mealTotals[meal].kg += kg;

                // Load lines for detail
                let lines = [];
                try {
                    const detail = await api(`api/requisitions.php?action=get&id=${r.id}`);
                    lines = detail.lines || [];
                    lines.forEach(l => {
                        const oq = parseFloat(l.order_qty) || 0;
                        const fq = parseFloat(l.fulfilled_qty) || 0;
                        const rq = parseFloat(l.received_qty) || 0;
                        const uq = parseFloat(l.unused_qty) || 0;
                        totalOrdered += oq;
                        totalFulfilled += fq;
                        totalReceived += rq;
                        totalUnused += uq;
                        if (!itemTotals[l.item_name]) itemTotals[l.item_name] = { ordered: 0, unused: 0 };
                        itemTotals[l.item_name].ordered += oq;
                        itemTotals[l.item_name].unused += uq;
                        mealTotals[meal].unused += uq;
                    });
                } catch(e) {}

                allReqs.push({
                    id: r.id,
                    date: d,
                    meals: meal,
                    chef_name: chef,
                    status: r.status,
                    kg: kg,
                    has_dispute: isDispute,
                    line_count: parseInt(r.line_count) || 0,
                    lines: lines
                });
            }
            d = changeDate(d, 1);
        }

        // ── Render Summary Cards ──
        document.getElementById('rpTotalSessions').textContent = totalSessions;
        document.getElementById('rpTotalKg').textContent = totalKg.toFixed(1);
        document.getElementById('rpFulfillRate').textContent = totalOrdered > 0 ? Math.round((totalFulfilled / totalOrdered) * 100) + '%' : '—';
        document.getElementById('rpDisputes').textContent = disputes;
        document.getElementById('rpConsumed').textContent = totalReceived > 0 ? (totalReceived - totalUnused).toFixed(1) : '—';
        document.getElementById('rpUnused').textContent = totalUnused > 0 ? totalUnused.toFixed(1) : '—';

        // ── By Meal Type ──
        rpRenderMealType(mealTotals);

        // ── Requisition Details ──
        rpRenderReqList(allReqs);

        // ── Top Items ──
        rpRenderTopItems(itemTotals);

        // ── Stock Discrepancies ──
        rpLoadDiscrepancies(from, to);

    } catch(e) {
        document.getElementById('rpReqList').innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

function rpRenderMealType(mealTotals) {
    const container = document.getElementById('rpByMeal');
    const entries = Object.entries(mealTotals).sort((a, b) => b[1].kg - a[1].kg);
    if (entries.length === 0) {
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No data</p>';
        return;
    }
    const maxKg = Math.max(...entries.map(e => e[1].kg), 1);
    let html = '';
    entries.forEach(([meal, data]) => {
        const c = rpMealColors[meal] || { bg: 'bg-gray-50', text: 'text-gray-700', border: 'border-gray-200', bar: 'bg-gray-400' };
        const pct = Math.round((data.kg / maxKg) * 100);
        const unusedLabel = data.unused > 0 ? ` &bull; <span class="text-amber-600">${data.unused.toFixed(1)} unused</span>` : '';
        html += `<div class="${c.bg} border ${c.border} rounded-lg px-3 py-2">
            <div class="flex items-center justify-between text-xs mb-1">
                <span class="${c.text} font-semibold">${rpMealName(meal)}</span>
                <span class="text-gray-500">${data.count} orders &bull; ${data.kg.toFixed(1)} kg${unusedLabel}</span>
            </div>
            <div class="w-full bg-white/60 rounded-full h-1.5">
                <div class="${c.bar} h-1.5 rounded-full transition-all" style="width: ${pct}%"></div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function rpRenderReqList(allReqs) {
    const container = document.getElementById('rpReqList');
    if (allReqs.length === 0) {
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No requisitions</p>';
        return;
    }

    // Group by date
    const byDate = {};
    allReqs.forEach(r => {
        if (!byDate[r.date]) byDate[r.date] = [];
        byDate[r.date].push(r);
    });

    let html = '';
    // Sort dates descending
    Object.keys(byDate).sort((a, b) => b.localeCompare(a)).forEach(date => {
        const reqs = byDate[date];
        html += `<div class="mb-3">
            <div class="text-[11px] font-semibold text-gray-500 mb-1.5 uppercase tracking-wider">${formatDate(date)}</div>`;

        reqs.forEach(r => {
            const c = rpMealColors[r.meals] || { bg: 'bg-gray-50', text: 'text-gray-700', border: 'border-gray-200', dot: 'bg-gray-400' };
            const statusCls = rpStatusBadge[r.status] || 'bg-gray-100 text-gray-500';
            const hasLines = r.lines && r.lines.length > 0;

            html += `<div class="bg-white border ${r.has_dispute ? 'border-red-200' : 'border-gray-100'} rounded-xl overflow-hidden mb-1.5">
                <div onclick="rpToggleDetail(this)" class="px-3 py-2.5 flex items-center justify-between cursor-pointer active:bg-gray-50">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <span class="w-2 h-2 rounded-full ${c.dot} shrink-0"></span>
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-gray-800">
                                <span class="${c.text} font-semibold">${rpMealName(r.meals)}</span>
                                <span class="text-gray-400 mx-1">by</span>
                                <span class="text-gray-600">${escHtml(r.chef_name)}</span>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-0.5">
                                ${r.line_count} items &bull; ${r.kg.toFixed(1)} kg
                                ${r.has_dispute ? '<span class="text-red-500 font-semibold ml-1">⚠ Dispute</span>' : ''}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[9px] font-medium px-2 py-0.5 rounded-full ${statusCls} capitalize">${r.status}</span>
                        <svg class="rp-chevron w-4 h-4 text-gray-300 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </div>
                </div>`;

            // Expandable detail section (hidden by default)
            if (hasLines) {
                html += `<div class="rp-detail hidden border-t border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="w-full text-[11px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left px-3 py-1.5 text-gray-500 font-semibold">Item</th>
                                    <th class="text-center px-2 py-1.5 text-blue-600 font-semibold">Req</th>
                                    <th class="text-center px-2 py-1.5 text-green-600 font-semibold">Sent</th>
                                    <th class="text-center px-2 py-1.5 text-orange-600 font-semibold">Recv</th>
                                    <th class="text-center px-2 py-1.5 text-amber-500 font-semibold">Unsd</th>
                                    <th class="text-center px-2 py-1.5 text-gray-600 font-semibold">Diff</th>
                                </tr>
                            </thead>
                            <tbody>`;

                r.lines.forEach(l => {
                    const oq = parseFloat(l.order_qty) || 0;
                    const fq = parseFloat(l.fulfilled_qty) || 0;
                    const rq = parseFloat(l.received_qty) || 0;
                    const uq = parseFloat(l.unused_qty) || 0;
                    // Diff = received - ordered (negative means shortfall)
                    const diff = rq > 0 ? rq - oq : (fq > 0 ? fq - oq : 0);
                    const diffLabel = diff > 0 ? `+${diff.toFixed(1)}` : diff < 0 ? diff.toFixed(1) : '—';
                    const diffCls = diff > 0 ? 'text-blue-600 font-semibold' : diff < 0 ? 'text-red-600 font-semibold' : 'text-gray-300';
                    const rowBg = Math.abs(diff) > 0.01 ? 'bg-red-50/50' : '';

                    html += `<tr class="${rowBg}">
                        <td class="px-3 py-1.5 text-gray-700">${escHtml(l.item_name)} <span class="text-gray-300">${l.uom || ''}</span></td>
                        <td class="text-center px-2 py-1.5 text-blue-700 font-medium">${oq > 0 ? oq.toFixed(1) : '—'}</td>
                        <td class="text-center px-2 py-1.5 text-green-700 font-medium">${fq > 0 ? fq.toFixed(1) : '—'}</td>
                        <td class="text-center px-2 py-1.5 text-orange-700 font-medium">${rq > 0 ? rq.toFixed(1) : '—'}</td>
                        <td class="text-center px-2 py-1.5 ${uq > 0 ? 'text-amber-600 font-semibold' : 'text-gray-300'}">${uq > 0 ? uq.toFixed(1) : '—'}</td>
                        <td class="text-center px-2 py-1.5 ${diffCls}">${diffLabel}</td>
                    </tr>`;
                });

                html += `</tbody></table></div></div>`;
            }

            html += `</div>`;
        });

        html += `</div>`;
    });

    container.innerHTML = html;
}

function rpToggleDetail(header) {
    const detail = header.nextElementSibling;
    if (!detail || !detail.classList.contains('rp-detail')) return;
    const chevron = header.querySelector('.rp-chevron');
    detail.classList.toggle('hidden');
    if (chevron) chevron.style.transform = detail.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

function rpRenderTopItems(itemTotals) {
    const container = document.getElementById('rpTopItems');
    const sorted = Object.entries(itemTotals).sort((a, b) => b[1].ordered - a[1].ordered).slice(0, 10);
    if (sorted.length === 0) {
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No data</p>';
        return;
    }
    const maxItem = sorted[0][1].ordered || 1;
    let html = '';
    sorted.forEach(([name, data], i) => {
        const pct = Math.round((data.ordered / maxItem) * 100);
        const unusedLabel = data.unused > 0 ? `<span class="text-amber-500 text-[10px] ml-1">(${data.unused.toFixed(1)} unused)</span>` : '';
        html += `<div class="bg-white border border-gray-100 rounded-lg px-3 py-2">
            <div class="flex items-center justify-between text-xs mb-1">
                <span class="text-gray-700 font-medium">${i + 1}. ${escHtml(name)}</span>
                <span class="text-orange-600 font-semibold">${data.ordered.toFixed(1)} kg ${unusedLabel}</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5">
                <div class="bg-orange-400 h-1.5 rounded-full" style="width: ${pct}%"></div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

async function rpLoadDiscrepancies(from, to) {
    const container = document.getElementById('rpDiscrepancies');
    container.innerHTML = '<p class="text-xs text-gray-400 text-center py-2">Loading...</p>';
    try {
        const data = await api(`api/inventory.php?action=discrepancies&from=${from}&to=${to}`);
        const items = data.discrepancies || [];
        const summary = data.summary || {};

        if (items.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No stock adjustments in this period</p>';
            return;
        }

        // Summary card
        let html = `<div class="grid grid-cols-3 gap-2 mb-3">
            <div class="bg-white border border-gray-200 rounded-xl p-2.5 text-center">
                <div class="text-lg font-bold text-gray-800">${summary.total_count}</div>
                <div class="text-[9px] text-gray-400">Adjustments</div>
            </div>
            <div class="bg-white border border-green-200 rounded-xl p-2.5 text-center">
                <div class="text-lg font-bold text-green-600">+${(summary.total_positive || 0).toFixed(1)}</div>
                <div class="text-[9px] text-gray-400">Added</div>
            </div>
            <div class="bg-white border border-red-200 rounded-xl p-2.5 text-center">
                <div class="text-lg font-bold text-red-600">${(summary.total_negative || 0).toFixed(1)}</div>
                <div class="text-[9px] text-gray-400">Removed</div>
            </div>
        </div>`;

        // Group by date
        const byDate = {};
        items.forEach(d => {
            const dateKey = d.date.substring(0, 10);
            if (!byDate[dateKey]) byDate[dateKey] = [];
            byDate[dateKey].push(d);
        });

        Object.keys(byDate).sort((a, b) => b.localeCompare(a)).forEach(date => {
            const rows = byDate[date];
            html += `<div class="mb-2">
                <div class="text-[11px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">${formatDate(date)}</div>`;
            rows.forEach(r => {
                const isPositive = r.adjustment > 0;
                const adjLabel = isPositive ? `+${r.adjustment.toFixed(1)}` : r.adjustment.toFixed(1);
                const adjColor = isPositive ? 'text-green-600' : 'text-red-600';
                const bgColor = isPositive ? 'border-green-100' : 'border-red-100';
                html += `<div class="bg-white border ${bgColor} rounded-xl px-3 py-2 mb-1">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-medium text-gray-800">${escHtml(r.item_name)}</div>
                            <div class="text-[10px] text-gray-400">${escHtml(r.category || '')} &bull; by ${escHtml(r.adjusted_by)}</div>
                        </div>
                        <div class="text-right shrink-0 ml-2">
                            <div class="text-sm font-bold ${adjColor}">${adjLabel}</div>
                            <div class="text-[10px] text-gray-400">→ ${r.new_stock.toFixed(1)}</div>
                        </div>
                    </div>
                    ${r.reason ? `<div class="text-[10px] text-gray-500 mt-1 italic">"${escHtml(r.reason)}"</div>` : ''}
                </div>`;
            });
            html += `</div>`;
        });

        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = '<p class="text-xs text-red-400 text-center py-4">Failed to load adjustments</p>';
    }
}
</script>
