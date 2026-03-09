<?php
/**
 * Karibu Pantry Planner — Day Close
 * Supports unused portions tracking per item
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Day Close</h2>
<p class="text-xs text-gray-500 mb-3">End-of-day closeout — record unused portions to update inventory</p>

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

<div id="dcContent"></div>

<script>
let dcDate = todayStr();
const DC_KID = <?= (int)$kitchenId ?>;
let dcLinesByReq = {}; // lines keyed by requisition id

dcRenderDate();
dcLoad();

function dcNavDate(d) { dcDate = changeDate(dcDate, d); dcRenderDate(); dcLoad(); }
function dcRenderDate() { document.getElementById('dcDateLabel').textContent = formatDate(dcDate); }

async function dcLoad() {
    const container = document.getElementById('dcContent');
    container.innerHTML = '<div class="text-center py-8 text-xs text-gray-400">Loading...</div>';

    try {
        const data = await api(`api/requisitions.php?action=day_summary&date=${dcDate}&kitchen_id=${DC_KID}`);
        const reqs = data.requisitions || [];
        const summary = data.summary || {};
        dcLinesByReq = data.lines_by_req || {};

        if (reqs.length === 0) {
            container.innerHTML = '<div class="text-center py-8"><p class="text-xs text-gray-400">No requisitions for this date</p></div>';
            return;
        }

        const statusColors = {
            draft: 'bg-gray-100 text-gray-700', submitted: 'bg-blue-100 text-blue-700',
            processing: 'bg-amber-100 text-amber-700', fulfilled: 'bg-green-100 text-green-700',
            received: 'bg-green-50 text-green-700 border border-green-200', closed: 'bg-gray-200 text-gray-500'
        };

        let html = `<div class="bg-white border border-gray-200 rounded-xl p-3 mb-3">
            <div class="grid grid-cols-3 gap-2 text-center text-[10px]">
                <div><div class="text-lg font-bold text-gray-800">${summary.total_sessions}</div>Requisitions</div>
                <div><div class="text-lg font-bold text-green-600">${(summary.received || 0) + (summary.closed || 0)}</div>Done</div>
                <div><div class="text-lg font-bold text-amber-600">${(summary.draft || 0) + (summary.submitted || 0) + (summary.processing || 0) + (summary.fulfilled || 0)}</div>Pending</div>
            </div>
        </div>`;

        html += '<div class="space-y-2 mb-4">';
        reqs.forEach(r => {
            const color = statusColors[r.status] || 'bg-gray-100 text-gray-700';
            const isReceived = r.status === 'received';
            const isClosed = r.status === 'closed';
            const lines = dcLinesByReq[r.id] || [];
            const hasLines = lines.length > 0;

            html += `<div class="bg-white border ${isReceived ? 'border-green-200' : 'border-gray-200'} rounded-xl overflow-hidden">`;

            // Header row - clickable to expand for received/closed
            if (hasLines) {
                html += `<div onclick="dcToggle(this)" class="px-4 py-3 cursor-pointer active:bg-gray-50">`;
            } else {
                html += `<div class="px-4 py-3">`;
            }
            html += `<div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">${reqLabel(r)}</span>
                        <div class="text-[10px] text-gray-400 mt-0.5">${r.line_count} items &bull; ${parseFloat(r.total_kg || 0).toFixed(1)} kg</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${color}">${r.status}${r.has_dispute ? ' !' : ''}</span>
                        ${hasLines ? '<svg class="dc-chev w-4 h-4 text-gray-300 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>' : ''}
                    </div>
                </div>
            </div>`;

            // Expandable detail with line items and unused inputs
            if (hasLines) {
                html += `<div class="dc-detail hidden border-t border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="w-full text-[11px]">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left px-3 py-1.5 text-gray-500 font-semibold">Item</th>
                                    <th class="text-center px-2 py-1.5 text-green-600 font-semibold w-16">Recv</th>
                                    <th class="text-center px-2 py-1.5 text-orange-600 font-semibold w-20">${isReceived ? 'Unused' : 'Unused'}</th>
                                    <th class="text-center px-2 py-1.5 text-blue-600 font-semibold w-16">Used</th>
                                </tr>
                            </thead>
                            <tbody>`;

                lines.forEach(l => {
                    const recv = parseFloat(l.received_qty) || 0;
                    const unused = parseFloat(l.unused_qty) || 0;
                    const used = Math.max(0, recv - unused);

                    if (isReceived) {
                        // Editable unused input
                        html += `<tr>
                            <td class="px-3 py-1.5 text-gray-700">${escHtml(l.item_name)} <span class="text-gray-300">${l.uom || ''}</span></td>
                            <td class="text-center px-2 py-1.5 text-green-700 font-medium">${recv > 0 ? recv.toFixed(1) : '—'}</td>
                            <td class="text-center px-1 py-1">
                                <input type="number" min="0" max="${recv}" step="0.1" value="${unused > 0 ? unused : ''}"
                                    placeholder="0"
                                    data-line-id="${l.id}" data-max="${recv}"
                                    onchange="dcCapUnused(this)"
                                    class="dc-unused-input w-16 text-center text-xs border border-gray-200 rounded-lg py-1 focus:border-orange-400 focus:ring-1 focus:ring-orange-200 outline-none">
                            </td>
                            <td class="text-center px-2 py-1.5 text-blue-700 font-medium dc-used" data-recv="${recv}" data-line-id="${l.id}">${recv > 0 ? recv.toFixed(1) : '—'}</td>
                        </tr>`;
                    } else {
                        // Read-only for closed
                        html += `<tr>
                            <td class="px-3 py-1.5 text-gray-700">${escHtml(l.item_name)} <span class="text-gray-300">${l.uom || ''}</span></td>
                            <td class="text-center px-2 py-1.5 text-green-700 font-medium">${recv > 0 ? recv.toFixed(1) : '—'}</td>
                            <td class="text-center px-2 py-1.5 ${unused > 0 ? 'text-orange-600 font-semibold' : 'text-gray-300'}">${unused > 0 ? unused.toFixed(1) : '—'}</td>
                            <td class="text-center px-2 py-1.5 text-blue-700 font-medium">${used > 0 ? used.toFixed(1) : '—'}</td>
                        </tr>`;
                    }
                });

                html += `</tbody></table></div>`;

                // Show totals row for closed requisitions
                if (isClosed) {
                    const totRecv = lines.reduce((s, l) => s + (parseFloat(l.received_qty) || 0), 0);
                    const totUnused = lines.reduce((s, l) => s + (parseFloat(l.unused_qty) || 0), 0);
                    const totUsed = totRecv - totUnused;
                    if (totUnused > 0) {
                        html += `<div class="px-3 py-2 bg-orange-50 border-t border-orange-100 text-[10px]">
                            <span class="text-orange-700 font-semibold">Unused: ${totUnused.toFixed(1)} kg</span>
                            <span class="text-gray-400 mx-1">&bull;</span>
                            <span class="text-blue-700 font-semibold">Consumed: ${totUsed.toFixed(1)} kg</span>
                            <span class="text-gray-400 mx-1">&bull;</span>
                            <span class="text-gray-500">Received: ${totRecv.toFixed(1)} kg</span>
                        </div>`;
                    }
                }

                html += `</div>`;
            }

            html += `</div>`;
        });
        html += '</div>';

        // Close button only if there are received sessions
        const canClose = (summary.received || 0) > 0;
        const allDone = (summary.draft || 0) === 0 && (summary.submitted || 0) === 0 && (summary.processing || 0) === 0 && (summary.fulfilled || 0) === 0;

        if (canClose) {
            html += `<div class="bg-orange-50 border border-orange-200 rounded-xl p-3 mb-2">
                <p class="text-[11px] text-orange-700 mb-1"><strong>Tip:</strong> Expand each received requisition above and enter any unused quantities. These will be added back to your kitchen inventory.</p>
            </div>`;
            html += `<button onclick="dcCloseDay()" class="w-full bg-blue-500 text-white py-3 rounded-xl text-sm font-semibold hover:bg-blue-600 transition">
                Close ${summary.received} Received Requisition${summary.received > 1 ? 's' : ''} & Update Inventory
            </button>`;
            if (!allDone) {
                html += `<p class="text-[10px] text-amber-600 text-center mt-2">Note: ${(summary.draft || 0) + (summary.submitted || 0) + (summary.processing || 0) + (summary.fulfilled || 0)} requisition(s) still in progress</p>`;
            }
        } else if (summary.closed === summary.total_sessions) {
            // Show consumption summary for fully closed days
            let totalRecv = 0, totalUnused = 0;
            reqs.forEach(r => {
                const lines = dcLinesByReq[r.id] || [];
                lines.forEach(l => {
                    totalRecv += parseFloat(l.received_qty) || 0;
                    totalUnused += parseFloat(l.unused_qty) || 0;
                });
            });
            const totalUsed = totalRecv - totalUnused;

            html += `<div class="bg-green-50 border border-green-200 rounded-xl p-3 text-center">
                <span class="text-xs text-green-600 font-semibold">All requisitions closed for this day</span>`;
            if (totalRecv > 0) {
                html += `<div class="grid grid-cols-3 gap-2 mt-2 text-[10px]">
                    <div><div class="text-sm font-bold text-green-700">${totalRecv.toFixed(1)}</div>Received (kg)</div>
                    <div><div class="text-sm font-bold text-blue-700">${totalUsed.toFixed(1)}</div>Consumed (kg)</div>
                    <div><div class="text-sm font-bold text-orange-600">${totalUnused.toFixed(1)}</div>Unused (kg)</div>
                </div>`;
            }
            html += `</div>`;
        } else {
            html += `<div class="text-center py-4"><span class="text-xs text-gray-400">No requisitions ready to close (must be received first)</span></div>`;
        }

        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

function dcToggle(header) {
    const detail = header.nextElementSibling;
    if (!detail || !detail.classList.contains('dc-detail')) return;
    const chevron = header.querySelector('.dc-chev');
    detail.classList.toggle('hidden');
    if (chevron) chevron.style.transform = detail.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

function dcCapUnused(input) {
    const max = parseFloat(input.dataset.max) || 0;
    let val = parseFloat(input.value) || 0;
    if (val < 0) val = 0;
    if (val > max) val = max;
    input.value = val > 0 ? val : '';

    // Update the "Used" column
    const lineId = input.dataset.lineId;
    const usedCell = document.querySelector(`.dc-used[data-line-id="${lineId}"]`);
    if (usedCell) {
        const recv = parseFloat(usedCell.dataset.recv) || 0;
        const used = recv - val;
        usedCell.textContent = used > 0 ? used.toFixed(1) : '—';
        usedCell.className = `text-center px-2 py-1.5 font-medium dc-used ${val > 0 ? 'text-blue-700' : 'text-blue-700'}`;
    }
}

async function dcCloseDay() {
    // Collect all unused quantities from inputs
    const inputs = document.querySelectorAll('.dc-unused-input');
    const unusedLines = [];
    inputs.forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        if (val > 0) {
            unusedLines.push({
                line_id: parseInt(inp.dataset.lineId),
                unused_qty: val
            });
        }
    });

    const hasUnused = unusedLines.length > 0;
    const totalUnusedKg = unusedLines.reduce((s, l) => s + l.unused_qty, 0);

    let confirmMsg = hasUnused
        ? `Close all received requisitions?\n\n${unusedLines.length} item(s) with ${totalUnusedKg.toFixed(1)} kg unused will be added back to inventory.`
        : 'Close all received requisitions?\n\nNo unused quantities entered.';

    if (!confirm(confirmMsg)) return;

    try {
        await api('api/requisitions.php?action=close_with_unused', {
            method: 'POST',
            body: JSON.stringify({
                date: dcDate,
                kitchen_id: DC_KID,
                unused_lines: unusedLines
            })
        });
        const msg = hasUnused
            ? `Day closed! ${totalUnusedKg.toFixed(1)} kg returned to inventory.`
            : 'Day closed successfully';
        showToast(msg, 'success');
        voice.dayClosed(formatDate(dcDate));
        dcLoad();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}
</script>
