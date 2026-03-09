<?php
/**
 * Karibu Pantry Planner — Day Close
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
?>

<h2 class="text-lg font-bold text-gray-800 mb-1">Day Close</h2>
<p class="text-xs text-gray-500 mb-3">End-of-day closeout for all requisitions</p>

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
            html += `<div class="bg-white border border-gray-200 rounded-xl px-4 py-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">${reqLabel(r)}</span>
                        <div class="text-[10px] text-gray-400 mt-0.5">${r.line_count} items &bull; ${parseFloat(r.total_kg || 0).toFixed(1)} kg</div>
                    </div>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${color}">${r.status}${r.has_dispute ? ' !' : ''}</span>
                </div>
            </div>`;
        });
        html += '</div>';

        // Close button only if there are received sessions
        const canClose = (summary.received || 0) > 0;
        const allDone = (summary.draft || 0) === 0 && (summary.submitted || 0) === 0 && (summary.processing || 0) === 0 && (summary.fulfilled || 0) === 0;

        if (canClose) {
            html += `<button onclick="dcCloseDay()" class="w-full bg-blue-500 text-white py-3 rounded-xl text-sm font-semibold hover:bg-blue-600 transition">
                Close ${summary.received} Received Requisition${summary.received > 1 ? 's' : ''}
            </button>`;
            if (!allDone) {
                html += `<p class="text-[10px] text-amber-600 text-center mt-2">Note: ${(summary.draft || 0) + (summary.submitted || 0) + (summary.processing || 0) + (summary.fulfilled || 0)} requisition(s) still in progress</p>`;
            }
        } else if (summary.closed === summary.total_sessions) {
            html += `<div class="text-center py-4"><span class="text-xs text-green-600 font-semibold">All requisitions closed for this day</span></div>`;
        } else {
            html += `<div class="text-center py-4"><span class="text-xs text-gray-400">No requisitions ready to close (must be received first)</span></div>`;
        }

        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = '<p class="text-center text-red-400 text-xs py-4">Failed to load</p>';
    }
}

async function dcCloseDay() {
    try {
        await api('api/requisitions.php?action=close', {
            method: 'POST', body: JSON.stringify({ date: dcDate, kitchen_id: DC_KID })
        });
        showToast('Day closed successfully', 'success');
        voice.dayClosed(formatDate(dcDate));
        dcLoad();
    } catch(e) { showToast(e.message || 'Failed', 'error'); }
}
</script>
