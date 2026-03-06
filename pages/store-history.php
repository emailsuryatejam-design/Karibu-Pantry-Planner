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
                        <span class="text-sm font-semibold text-gray-800">${formatDate(r.date)} — Session ${r.session_number}</span>
                        <div class="text-[10px] text-gray-400 mt-0.5">${r.chef_name || 'Chef'} &bull; ${r.meals || '—'} &bull; ${r.line_count} items</div>
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
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Session ${req.session_number} Details</h3>
            <p class="text-[10px] text-gray-400 mb-3">${req.meals || '—'} &bull; ${req.chef_name || 'Chef'}</p>
            <div class="space-y-1 max-h-[55vh] overflow-y-auto">
                <div class="grid grid-cols-[1fr_55px_55px_55px] gap-1 text-[9px] font-semibold text-gray-500 px-2 py-1 bg-gray-50 rounded-lg">
                    <span>Item</span><span class="text-center">Order</span><span class="text-center">Sent</span><span class="text-center">Got</span>
                </div>`;

        lines.forEach(l => {
            const order = parseFloat(l.order_qty) || 0;
            const fulfilled = parseFloat(l.fulfilled_qty) || 0;
            const received = parseFloat(l.received_qty) ?? '-';
            const diff = received !== '-' && Math.abs(fulfilled - received) > 0.01;
            html += `<div class="grid grid-cols-[1fr_55px_55px_55px] gap-1 text-xs px-2 py-1.5 ${diff ? 'bg-red-50' : ''}">
                <span class="text-gray-800 truncate">${l.item_name}</span>
                <span class="text-center text-gray-500">${order}</span>
                <span class="text-center text-gray-700 font-medium">${fulfilled}</span>
                <span class="text-center ${diff ? 'text-red-600 font-bold' : 'text-gray-700'}">${received}</span>
            </div>`;
        });

        html += '</div></div>';
        openSheet(html);
    } catch(e) { showToast('Failed to load', 'error'); }
}
</script>
