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
        <div class="text-[10px] text-gray-400">Total Sessions</div>
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
</div>

<!-- Daily Breakdown -->
<h3 class="text-sm font-semibold text-gray-700 mb-2">Daily Breakdown</h3>
<div id="rpDaily" class="space-y-1 mb-4"></div>

<!-- Top Items -->
<h3 class="text-sm font-semibold text-gray-700 mb-2">Most Ordered Items</h3>
<div id="rpTopItems" class="space-y-1"></div>

<script>
const RP_KID = <?= (int)$kitchenId ?>;

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
    let dailyData = [];
    let itemTotals = {};

    try {
        let d = from;
        while (d <= to) {
            const data = await api(`api/requisitions.php?action=day_summary&date=${d}&kitchen_id=${RP_KID}`);
            const reqs = data.requisitions || [];
            let dayKg = 0, daySessions = reqs.length;

            for (const r of reqs) {
                totalSessions++;
                if (r.has_dispute == 1) disputes++;
                const kg = parseFloat(r.total_kg || 0);
                dayKg += kg;
                totalKg += kg;

                // Load lines for item breakdown
                if (r.status !== 'draft') {
                    try {
                        const detail = await api(`api/requisitions.php?action=get&id=${r.id}`);
                        (detail.lines || []).forEach(l => {
                            const oq = parseFloat(l.order_qty) || 0;
                            const fq = parseFloat(l.fulfilled_qty) || 0;
                            totalOrdered += oq;
                            totalFulfilled += fq;
                            if (!itemTotals[l.item_name]) itemTotals[l.item_name] = 0;
                            itemTotals[l.item_name] += oq;
                        });
                    } catch(e) {}
                }
            }

            if (daySessions > 0) {
                dailyData.push({ date: d, sessions: daySessions, kg: dayKg });
            }
            d = changeDate(d, 1);
        }

        // Render stats
        document.getElementById('rpTotalSessions').textContent = totalSessions;
        document.getElementById('rpTotalKg').textContent = totalKg.toFixed(1);
        document.getElementById('rpFulfillRate').textContent = totalOrdered > 0 ? Math.round((totalFulfilled / totalOrdered) * 100) + '%' : '—';
        document.getElementById('rpDisputes').textContent = disputes;

        // Daily breakdown
        const dailyContainer = document.getElementById('rpDaily');
        if (dailyData.length === 0) {
            dailyContainer.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No data</p>';
        } else {
            const maxKg = Math.max(...dailyData.map(d => d.kg), 1);
            let html = '';
            dailyData.forEach(d => {
                const pct = Math.round((d.kg / maxKg) * 100);
                html += `<div class="bg-white border border-gray-100 rounded-lg px-3 py-2">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="text-gray-700 font-medium">${formatDate(d.date)}</span>
                        <span class="text-gray-500">${d.sessions} sessions &bull; ${d.kg.toFixed(1)} kg</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-orange-500 h-1.5 rounded-full transition-all" style="width: ${pct}%"></div>
                    </div>
                </div>`;
            });
            dailyContainer.innerHTML = html;
        }

        // Top items
        const topContainer = document.getElementById('rpTopItems');
        const sorted = Object.entries(itemTotals).sort((a, b) => b[1] - a[1]).slice(0, 10);
        if (sorted.length === 0) {
            topContainer.innerHTML = '<p class="text-xs text-gray-400 text-center py-4">No data</p>';
        } else {
            const maxItem = sorted[0][1] || 1;
            let html = '';
            sorted.forEach(([name, qty], i) => {
                const pct = Math.round((qty / maxItem) * 100);
                html += `<div class="bg-white border border-gray-100 rounded-lg px-3 py-2">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="text-gray-700 font-medium">${i + 1}. ${name}</span>
                        <span class="text-orange-600 font-semibold">${qty.toFixed(1)} kg</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="bg-orange-400 h-1.5 rounded-full" style="width: ${pct}%"></div>
                    </div>
                </div>`;
            });
            topContainer.innerHTML = html;
        }
    } catch(e) {
        document.getElementById('rpDaily').innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}
</script>
