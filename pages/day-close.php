<?php
/**
 * Karibu Pantry Planner — Day Close (per-item kitchen reconciliation)
 *
 * The chef declares what is LEFT (unused) of each item against the total that was
 * available today = opening kitchen stock + what the store sent. Whatever is left
 * becomes the new kitchen stock (overwrite). Next day's orders subtract that stock.
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Day Close</h2>
<p class="text-xs text-gray-500 mb-3">Count what's left in the kitchen. Whatever you don't use becomes tomorrow's stock.</p>

<!-- Date Nav -->
<div class="flex items-center gap-2 mb-4">
    <button onclick="dcNavDate(-1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
    </button>
    <div class="flex-1 text-center text-sm font-semibold text-gray-800" id="dcDateLabel"></div>
    <button onclick="dcNavDate(1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
    </button>
</div>

<div class="flex gap-2 mb-4">
    <button onclick="printWholeDay(dcDate, DC_KID)" class="flex-1 bg-white border border-gray-200 rounded-xl py-2.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 active:bg-gray-100 transition flex items-center justify-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
        Print Whole Day
    </button>
    <button onclick="window.open('api/requisitions.php?action=day_pdf&date=' + dcDate + '&kitchen_id=' + DC_KID, '_blank')" class="flex-1 bg-white border border-gray-200 rounded-xl py-2.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 active:bg-gray-100 transition flex items-center justify-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
        Download PDF
    </button>
</div>

<div id="dcContent"></div>

<script>
let dcDate = todayStr();
const DC_KID = <?= (int)$kitchenId ?>;
let dcItems = [];          // per-item reconciliation rows
let dcDayClosed = false;

dcRenderDate();
dcLoad();

function dcNavDate(d) { dcDate = changeDate(dcDate, d); dcRenderDate(); dcLoad(); }
function dcRenderDate() { document.getElementById('dcDateLabel').textContent = formatDate(dcDate); }

async function dcLoad() {
    const container = document.getElementById('dcContent');
    container.innerHTML = '<div class="text-center py-8 text-xs text-gray-400">Loading...</div>';

    try {
        // Status list (order lifecycle) + per-item reconciliation
        const [summary, recon] = await Promise.all([
            api(`api/requisitions.php?action=day_summary&date=${dcDate}&kitchen_id=${DC_KID}`),
            api(`api/requisitions.php?action=day_close_items&date=${dcDate}&kitchen_id=${DC_KID}`)
        ]);

        const reqs = summary.requisitions || [];
        const sum = summary.summary || {};
        dcItems = recon.items || [];
        const sc = recon.status_counts || {};

        // Day is "closed" when there's nothing left awaiting receipt/fulfilment
        const pendingClose = (sc.fulfilled || 0) + (sc.received || 0);
        const stillOpen = (sc.submitted || 0) + (sc.processing || 0);
        dcDayClosed = (sc.closed || 0) > 0 && pendingClose === 0 && stillOpen === 0;

        if (reqs.length === 0) {
            container.innerHTML = '<div class="text-center py-8"><p class="text-xs text-gray-400">No requisitions for this date</p></div>';
            return;
        }

        let html = '';

        // ── Per-item reconciliation table ──
        if (dcItems.length > 0) {
            html += `<div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-3">
                <div class="px-3 py-2.5 bg-gray-50 border-b border-gray-100">
                    <p class="text-xs font-bold text-gray-700">Kitchen reconciliation</p>
                    <p class="text-[10px] text-gray-400">Available = what you had in stock + what the store sent. Enter what's LEFT.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[11px]">
                        <thead><tr class="bg-gray-50 text-gray-500">
                            <th class="text-left px-3 py-1.5 font-semibold">Item</th>
                            <th class="text-center px-1 py-1.5 font-semibold w-20 text-gray-600" title="Stock + Received">Available</th>
                            <th class="text-center px-1 py-1.5 font-semibold w-14 text-blue-600">Used</th>
                            <th class="text-center px-1 py-1.5 font-semibold w-20 text-orange-600" title="What's left → becomes new stock">Left</th>
                        </tr></thead>
                        <tbody>`;

            dcItems.forEach(it => {
                const avail = parseFloat(it.available) || 0;
                const ordered = parseFloat(it.ordered) || 0;
                const sent = parseFloat(it.received) || 0;
                // Did the store change the quantity from what the chef ordered?
                const changed = ordered > 0 && Math.abs(sent - ordered) > 0.01;
                const changeNote = changed
                    ? ` <span class="text-[9px] ${sent < ordered ? 'text-red-500' : 'text-green-600'} font-semibold">(ordered ${ordered.toFixed(1)} → store sent ${sent.toFixed(1)})</span>`
                    : '';
                // Prefill: re-editing a closed day shows the last declared stock; otherwise blank
                const prefill = dcDayClosed ? (parseFloat(it.current_stock) || 0) : null;
                const usedInit = prefill !== null ? Math.max(0, avail - prefill) : avail;
                html += `<tr class="border-b border-gray-50">
                    <td class="px-3 py-2">
                        <p class="text-xs font-medium text-gray-800">${escHtml(it.item_name)} <span class="text-gray-300 text-[9px]">${escHtml(it.uom)}</span></p>
                        <p class="text-[9px] text-gray-400">stock ${(parseFloat(it.opening_stock)||0).toFixed(1)} + sent ${sent.toFixed(1)}${changeNote}</p>
                    </td>
                    <td class="text-center px-1 py-2 text-gray-700 font-semibold">${avail.toFixed(1)}</td>
                    <td class="text-center px-1 py-2 text-blue-700 font-medium dc-used" data-item="${it.item_id}">${usedInit > 0 ? usedInit.toFixed(1) : '—'}</td>
                    <td class="text-center px-1 py-2">
                        <input type="number" min="0" max="${avail}" step="0.1"
                            value="${prefill !== null && prefill > 0 ? prefill : ''}" placeholder="0"
                            data-item="${it.item_id}" data-avail="${avail}"
                            onchange="dcCapLeft(this)" oninput="dcCapLeft(this)"
                            class="dc-left-input w-16 text-center text-xs font-bold border border-orange-300 rounded-lg py-1 bg-orange-50 focus:outline-none focus:ring-1 focus:ring-orange-300">
                    </td>
                </tr>`;
            });

            html += `</tbody></table></div></div>`;

            html += `<div class="bg-orange-50 border border-orange-200 rounded-xl p-3 mb-2">
                <p class="text-[11px] text-orange-700"><strong>How it works:</strong> Enter what is physically left of each item. That amount becomes your new kitchen stock and is subtracted from your next order. Items left blank are treated as fully used (0 left).</p>
            </div>`;

            html += `<button onclick="dcCloseDay()" class="w-full bg-blue-500 text-white py-3 rounded-xl text-sm font-semibold hover:bg-blue-600 transition">
                ${dcDayClosed ? 'Update Kitchen Stock' : 'Close Day & Update Stock'}
            </button>`;
            if (stillOpen > 0) {
                html += `<p class="text-[10px] text-amber-600 text-center mt-2">Note: ${stillOpen} order(s) still in progress (not yet received from store).</p>`;
            }
        } else {
            html += `<div class="text-center py-6"><span class="text-xs text-gray-400">No items to reconcile yet — lock a menu first.</span></div>`;
        }

        // ── Order status list (read-only) ──
        const statusColors = {
            draft: 'bg-gray-100 text-gray-700', submitted: 'bg-blue-100 text-blue-700',
            processing: 'bg-amber-100 text-amber-700', fulfilled: 'bg-green-100 text-green-700',
            received: 'bg-green-50 text-green-700 border border-green-200', closed: 'bg-gray-200 text-gray-500'
        };
        html += `<div class="mt-4"><p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Orders this day</p><div class="space-y-1.5">`;
        reqs.forEach(r => {
            if (r.status === 'draft' && parseInt(r.line_count) === 0) return; // hide empty drafts
            const color = statusColors[r.status] || 'bg-gray-100 text-gray-700';
            html += `<div class="bg-white border border-gray-200 rounded-xl px-3 py-2.5 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-gray-800">${reqLabel(r)}</span>
                    <div class="text-[10px] text-gray-400 mt-0.5">${r.line_count} items &bull; ${parseFloat(r.total_kg || 0).toFixed(1)} kg</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${color}">${r.status}</span>
                    <button onclick="printOrder(${r.id})" class="text-gray-300 hover:text-gray-600 transition" title="Print">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                    </button>
                </div>
            </div>`;
        });
        html += `</div></div>`;

        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

// Clamp "left" to available and live-update the Used column
function dcCapLeft(input) {
    const avail = parseFloat(input.dataset.avail) || 0;
    let left = parseFloat(input.value);
    if (isNaN(left)) left = null;
    if (left !== null) {
        if (left < 0) left = 0;
        if (left > avail) { left = avail; input.value = avail; }
    }
    const usedCell = document.querySelector(`.dc-used[data-item="${input.dataset.item}"]`);
    if (usedCell) {
        const used = left === null ? avail : Math.max(0, avail - left);
        usedCell.textContent = used > 0 ? used.toFixed(1) : '—';
    }
}

async function dcCloseDay() {
    const inputs = document.querySelectorAll('.dc-left-input');
    const items = [];
    let totalLeft = 0;
    inputs.forEach(inp => {
        const left = parseFloat(inp.value) || 0; // blank = 0 (fully used)
        items.push({ item_id: parseInt(inp.dataset.item), unused: left });
        totalLeft += left;
    });

    const confirmed = await customConfirm(
        dcDayClosed ? 'Update Kitchen Stock' : 'Close Day',
        `${items.length} item(s) will be reconciled.\n\nKitchen stock will be SET to what you entered as left (total ${totalLeft.toFixed(1)}). Items left blank become 0 (fully used).`,
        dcDayClosed ? 'Update Stock' : 'Close & Update', 'Cancel'
    );
    if (!confirmed) return;

    try {
        await api('api/requisitions.php?action=day_close_reconcile', {
            method: 'POST',
            body: { date: dcDate, kitchen_id: DC_KID, items }
        });
        showToast(dcDayClosed ? 'Kitchen stock updated' : 'Day closed — kitchen stock updated', 'success');
        dcLoad();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}
</script>
