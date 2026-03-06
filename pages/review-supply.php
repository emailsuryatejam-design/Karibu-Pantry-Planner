<?php
/**
 * Karibu Pantry Planner — Review Supply (Chef confirms received items)
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Review Supply</h2>
<p class="text-xs text-gray-500 mb-3">Confirm items received from store</p>

<!-- Date Nav -->
<div class="flex items-center gap-2 mb-4">
    <button onclick="rsNavDate(-1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
    </button>
    <div class="flex-1 text-center text-sm font-semibold text-gray-800" id="rsDateLabel"></div>
    <button onclick="rsNavDate(1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
    </button>
</div>

<div id="rsList" class="space-y-2"></div>

<script>
let rsDate = todayStr();
const RS_KID = <?= (int)$kitchenId ?>;

rsRenderDate();
rsLoad();

function rsNavDate(d) { rsDate = changeDate(rsDate, d); rsRenderDate(); rsLoad(); }
function rsRenderDate() { document.getElementById('rsDateLabel').textContent = formatDate(rsDate); }

async function rsLoad() {
    const container = document.getElementById('rsList');
    container.innerHTML = '<div class="text-center py-8 text-xs text-gray-400">Loading...</div>';
    try {
        const data = await api(`api/requisitions.php?action=list&date=${rsDate}&kitchen_id=${RS_KID}&status=fulfilled`);
        const reqs = data.requisitions || [];

        if (reqs.length === 0) {
            container.innerHTML = '<div class="text-center py-8"><p class="text-xs text-gray-400">No fulfilled requisitions to review</p></div>';
            return;
        }

        let html = '';
        reqs.forEach(r => {
            html += `<div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">Requisition ${r.session_number}</span>
                        <span class="text-[10px] text-gray-400 ml-2">${r.line_count || 0} items</span>
                    </div>
                    <span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Fulfilled</span>
                </div>
                <button onclick="rsConfirm(${r.id})" class="w-full bg-green-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-green-600 transition">Confirm Receipt</button>
            </div>`;
        });
        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

async function rsConfirm(reqId) {
    try {
        const data = await api(`api/requisitions.php?action=get&id=${reqId}`);
        const lines = data.lines || [];

        let html = `<div class="p-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Confirm Receipt</h3>
            <div class="space-y-2 max-h-[55vh] overflow-y-auto">`;

        lines.forEach(l => {
            const fulfilled = parseFloat(l.fulfilled_qty) || 0;
            html += `<div class="bg-gray-50 rounded-lg px-3 py-2">
                <div class="text-sm font-medium text-gray-800">${l.item_name}</div>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-xs text-gray-500">Ordered: ${parseFloat(l.order_qty).toFixed(1)} ${l.uom} | Sent: ${fulfilled} ${l.uom}</span>
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] text-gray-500">Got:</span>
                        <input type="number" value="${fulfilled}" min="0" step="0.5" data-line-id="${l.id}"
                            class="rs-recv w-16 text-center border border-gray-200 rounded py-1 text-sm font-semibold focus:outline-none focus:ring-1 focus:ring-green-300">
                    </div>
                </div>
            </div>`;
        });

        html += `</div>
            <button onclick="rsDoConfirm(${reqId})" class="mt-3 w-full bg-green-500 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-600 transition">Confirm</button>
        </div>`;
        openSheet(html);
    } catch(e) {
        showToast('Failed to load details', 'error');
    }
}

async function rsDoConfirm(reqId) {
    const inputs = document.querySelectorAll('.rs-recv');
    const lines = [];
    inputs.forEach(inp => { lines.push({ id: parseInt(inp.dataset.lineId), received_qty: parseFloat(inp.value) || 0 }); });

    try {
        const data = await api('api/requisitions.php?action=confirm_receipt', {
            method: 'POST', body: JSON.stringify({ requisition_id: reqId, lines })
        });
        closeSheet();
        showToast(data.has_dispute ? 'Confirmed with disputes' : 'Receipt confirmed', data.has_dispute ? 'warning' : 'success');
        rsLoad();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}
</script>
