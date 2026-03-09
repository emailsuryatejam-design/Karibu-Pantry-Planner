<?php
/**
 * Karibu Pantry Planner — Store Dashboard
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
$kitchenName = $user['kitchen_name'] ?? 'Store';
?>

<div class="mb-4">
    <h2 class="text-lg font-bold text-gray-800">Store Dashboard</h2>
    <p class="text-xs text-gray-500"><?= htmlspecialchars($kitchenName) ?> &mdash; <?= date('l, d M Y') ?></p>
</div>

<!-- Stats -->
<div class="flex gap-3 overflow-x-auto pb-2 mb-4">
    <div class="min-w-[120px] bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="sdStatNew">—</div>
        <div class="text-[10px] opacity-80 font-medium">New Orders</div>
    </div>
    <div class="min-w-[120px] bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="sdStatProc">—</div>
        <div class="text-[10px] opacity-80 font-medium">Processing</div>
    </div>
    <div class="min-w-[120px] bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-3 text-white flex-1">
        <div class="text-2xl font-bold" id="sdStatDone">—</div>
        <div class="text-[10px] opacity-80 font-medium">Fulfilled Today</div>
    </div>
</div>

<!-- Incoming Requisitions -->
<h3 class="text-sm font-semibold text-gray-700 mb-2">Incoming Requisitions</h3>
<div id="sdIncoming" class="space-y-2 mb-4"></div>

<script>
const SD_KID = <?= (int)$kitchenId ?>;

sdLoadStats();
sdLoadIncoming();

async function sdLoadStats() {
    try {
        const data = await api(`api/requisitions.php?action=store_stats&kitchen_id=${SD_KID}`);
        const s = data.stats || {};
        document.getElementById('sdStatNew').textContent = s.new_orders || 0;
        document.getElementById('sdStatProc').textContent = s.processing || 0;
        document.getElementById('sdStatDone').textContent = s.fulfilled_today || 0;
        // Voice: announce new orders on page load
        if ((s.new_orders || 0) > 0) {
            voice.say(`Store dashboard. You have ${s.new_orders} new order${s.new_orders > 1 ? 's' : ''} to fulfill.`);
        }
    } catch(e) {}
}

async function sdLoadIncoming() {
    const container = document.getElementById('sdIncoming');
    try {
        const data = await api(`api/requisitions.php?action=list&date=${todayStr()}&kitchen_id=${SD_KID}&status=submitted`);
        const reqs = data.requisitions || [];

        // Also load processing
        const data2 = await api(`api/requisitions.php?action=list&date=${todayStr()}&kitchen_id=${SD_KID}&status=processing`);
        const all = [...reqs, ...(data2.requisitions || [])];

        if (all.length === 0) {
            container.innerHTML = '<div class="text-center py-8"><p class="text-xs text-gray-400">No pending requisitions</p></div>';
            return;
        }

        let html = '';
        all.forEach(r => {
            const isNew = r.status === 'submitted';
            html += `<div class="bg-white border ${isNew ? 'border-red-200' : 'border-amber-200'} rounded-xl px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">${reqLabel(r)}</span>
                        <span class="text-[10px] text-gray-400 ml-2">${r.chef_name || 'Chef'}</span>
                    </div>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${isNew ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}">${r.status}</span>
                </div>
                <div class="text-[10px] text-gray-400 mb-2">${r.line_count} items</div>
                <button onclick="sdFulfill(${r.id})" class="w-full bg-green-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-green-600 transition">
                    ${isNew ? 'Start Fulfilling' : 'Continue'}
                </button>
            </div>`;
        });
        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

async function sdFulfill(reqId) {
    try {
        const data = await api(`api/requisitions.php?action=get&id=${reqId}`);
        const lines = data.lines || [];
        const req = data.requisition;

        let html = `<div class="p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Fulfill ${reqLabel(req)}</h3>
            <p class="text-[10px] text-gray-400 mb-3">${req.chef_name || 'Chef'}</p>
            <div class="space-y-2 max-h-[50vh] overflow-y-auto">`;

        lines.forEach(l => {
            const orderQty = parseFloat(l.order_qty) || 0;
            html += `<div class="bg-gray-50 rounded-lg px-3 py-2">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-gray-800">${l.item_name}</div>
                        <div class="text-[10px] text-gray-400">Requested: ${orderQty} ${l.uom}</div>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] text-gray-500">Send:</span>
                        <input type="number" value="${orderQty}" min="0" step="0.5" data-line-id="${l.id}"
                            class="sd-fulfill w-16 text-center border border-gray-200 rounded py-1 text-sm font-semibold focus:outline-none focus:ring-1 focus:ring-green-300">
                        <span class="text-[10px] text-gray-400">${l.uom}</span>
                    </div>
                </div>
            </div>`;
        });

        html += `</div>
            <button onclick="sdDoFulfill(${reqId})" class="mt-3 w-full bg-green-500 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-600 transition">Mark as Fulfilled</button>
        </div>`;
        openSheet(html);
    } catch(e) {
        showToast('Failed to load', 'error');
    }
}

async function sdDoFulfill(reqId) {
    const inputs = document.querySelectorAll('.sd-fulfill');
    const lines = [];
    inputs.forEach(inp => { lines.push({ id: parseInt(inp.dataset.lineId), fulfilled_qty: parseFloat(inp.value) || 0 }); });

    try {
        await api('api/requisitions.php?action=fulfill', {
            method: 'POST', body: JSON.stringify({ requisition_id: reqId, lines })
        });
        closeSheet();
        showToast('Requisition fulfilled', 'success');
        voice.orderFulfilled('', '<?= addslashes($kitchenName) ?>');
        sdLoadStats();
        sdLoadIncoming();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
        voice.error('Failed to fulfill order');
    }
}
</script>
