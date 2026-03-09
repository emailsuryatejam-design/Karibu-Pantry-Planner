<?php
/**
 * Karibu Pantry Planner — Store History
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Order History</h2>
<p class="text-xs text-gray-500 mb-3">Past requisitions and fulfillment records</p>

<!-- Status Filter -->
<div class="flex gap-2 overflow-x-auto pb-2 mb-3" id="shFilters">
    <button onclick="shFilter('')" class="sh-filter text-xs font-medium px-3 py-1.5 rounded-full bg-green-500 text-white whitespace-nowrap">All</button>
    <button onclick="shFilter('fulfilled')" class="sh-filter text-xs font-medium px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 whitespace-nowrap">Fulfilled</button>
    <button onclick="shFilter('received')" class="sh-filter text-xs font-medium px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 whitespace-nowrap">Received</button>
    <button onclick="shFilter('closed')" class="sh-filter text-xs font-medium px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 whitespace-nowrap">Closed</button>
</div>

<!-- Date Range -->
<div class="flex items-center gap-2 mb-3">
    <input type="date" id="shDateFrom" class="text-xs border border-gray-200 rounded-lg px-3 py-2 bg-white flex-1" onchange="shLoad()">
    <span class="text-xs text-gray-400">to</span>
    <input type="date" id="shDateTo" class="text-xs border border-gray-200 rounded-lg px-3 py-2 bg-white flex-1" onchange="shLoad()">
</div>

<div id="shList" class="space-y-2"></div>

<script>
const SH_KID = <?= (int)$kitchenId ?>;
let shStatus = '';

// Default date range: last 7 days
document.getElementById('shDateFrom').value = changeDate(todayStr(), -7);
document.getElementById('shDateTo').value = todayStr();

shLoad();

function shFilter(status) {
    shStatus = status;
    document.querySelectorAll('.sh-filter').forEach((btn, i) => {
        const isActive = (status === '' && i === 0) || btn.textContent.trim().toLowerCase() === status;
        btn.className = `sh-filter text-xs font-medium px-3 py-1.5 rounded-full whitespace-nowrap ${isActive ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-600'}`;
    });
    shLoad();
}

async function shLoad() {
    const container = document.getElementById('shList');
    container.innerHTML = '<div class="text-center py-8 text-xs text-gray-400">Loading...</div>';

    const from = document.getElementById('shDateFrom').value;
    const to = document.getElementById('shDateTo').value;

    try {
        // Load all days in range
        let allReqs = [];
        let d = from;
        while (d <= to) {
            let url = `api/requisitions.php?action=list&date=${d}&kitchen_id=${SH_KID}`;
            if (shStatus) url += `&status=${shStatus}`;
            const data = await api(url);
            const reqs = (data.requisitions || []).map(r => ({ ...r, date: d }));
            allReqs = [...allReqs, ...reqs];
            d = changeDate(d, 1);
        }

        // Filter out drafts and submitted for history view
        if (!shStatus) {
            allReqs = allReqs.filter(r => ['fulfilled', 'received', 'closed'].includes(r.status));
        }

        if (allReqs.length === 0) {
            container.innerHTML = '<div class="text-center py-8"><p class="text-xs text-gray-400">No records found</p></div>';
            return;
        }

        const statusColors = {
            fulfilled: 'bg-green-100 text-green-700', received: 'bg-green-50 text-green-700',
            closed: 'bg-gray-200 text-gray-500'
        };

        let html = '';
        allReqs.forEach(r => {
            const color = statusColors[r.status] || 'bg-gray-100 text-gray-700';
            html += `<div class="bg-white border border-gray-200 rounded-xl px-4 py-3 cursor-pointer hover:border-green-200 transition" onclick="shDetail(${r.id})">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">${formatDate(r.date)} — ${reqLabel(r)}</span>
                        <div class="text-[10px] text-gray-400 mt-0.5">${r.chef_name || 'Chef'} &bull; ${r.line_count} items</div>
                    </div>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${color}">${r.status}${r.has_dispute == 1 ? ' !' : ''}</span>
                </div>
            </div>`;
        });
        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

async function shDetail(reqId) {
    try {
        const data = await api(`api/requisitions.php?action=get&id=${reqId}`);
        const req = data.requisition;
        const lines = data.lines || [];

        let html = `<div class="p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">${reqLabel(req)} Details</h3>
            <p class="text-[10px] text-gray-400 mb-3">${req.chef_name || 'Chef'}</p>
            <div class="max-h-[55vh] overflow-y-auto">
                <table class="w-full text-[11px]">
                    <thead><tr class="bg-gray-50">
                        <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">Item</th>
                        <th class="text-center px-1 py-1.5 text-blue-600 font-semibold">Req</th>
                        <th class="text-center px-1 py-1.5 text-green-600 font-semibold">Sent</th>
                        <th class="text-center px-1 py-1.5 text-orange-600 font-semibold">Received</th>
                        <th class="text-center px-1 py-1.5 text-gray-600 font-semibold">Diff</th>
                    </tr></thead>
                    <tbody>`;

        lines.forEach(l => {
            const oq = parseFloat(l.order_qty) || 0;
            const fq = parseFloat(l.fulfilled_qty) || 0;
            const rq = parseFloat(l.received_qty) || 0;
            const diff = rq > 0 ? rq - oq : (fq > 0 ? fq - oq : 0);
            const diffLabel = diff > 0 ? '+' + diff.toFixed(1) : diff < 0 ? diff.toFixed(1) : '—';
            const diffCls = diff > 0 ? 'text-blue-600 font-semibold' : diff < 0 ? 'text-red-600 font-semibold' : 'text-gray-300';
            const rowBg = Math.abs(diff) > 0.01 ? 'bg-red-50/50' : '';
            html += `<tr class="${rowBg}">
                <td class="px-2 py-1.5 text-gray-700">${l.item_name} <span class="text-gray-300 text-[9px]">${l.uom || ''}</span></td>
                <td class="text-center px-1 py-1.5 text-blue-700 font-medium">${oq > 0 ? oq.toFixed(1) : '—'}</td>
                <td class="text-center px-1 py-1.5 text-green-700 font-medium">${fq > 0 ? fq.toFixed(1) : '—'}</td>
                <td class="text-center px-1 py-1.5 text-orange-700 font-medium">${rq > 0 ? rq.toFixed(1) : '—'}</td>
                <td class="text-center px-1 py-1.5 ${diffCls}">${diffLabel}</td>
            </tr>`;
        });

        html += `</tbody></table>`;

        html += `</div>
            <button onclick="printOrder(${reqId})" class="mt-3 w-full bg-gray-100 text-gray-700 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                Print Order
            </button>
        </div>`;
        openSheet(html);
    } catch(e) { showToast('Failed to load', 'error'); }
}
</script>
