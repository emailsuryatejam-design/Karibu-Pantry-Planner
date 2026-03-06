<?php
/**
 * Karibu Pantry Planner — Requisition Page
 * Chef creates KG-based or direct-KG orders
 */
$user = currentUser();
$kitchenId = $user['kitchen_id'] ?? 0;
$kitchenName = $user['kitchen_name'] ?? 'No Kitchen';
?>

<!-- Date Strip -->
<div class="flex items-center gap-2 mb-3">
    <button onclick="rqNavDate(-1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button>
    <div class="flex-1 text-center">
        <button onclick="rqShowDatePicker()" class="text-sm font-semibold text-gray-800" id="rqDateDisplay"></button>
        <div class="text-[10px] text-gray-400" id="rqDateRelative"></div>
    </div>
    <button onclick="rqNavDate(1)" class="p-2 bg-white border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50" id="rqNextBtn">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>
</div>

<!-- Session Tabs -->
<div class="flex gap-2 mb-3 overflow-x-auto pb-1" id="rqSessionTabs">
    <button class="text-xs font-semibold px-3 py-1.5 rounded-full bg-orange-500 text-white whitespace-nowrap" id="rqNewSessionBtn" onclick="rqCreateSession()">+ New Session</button>
</div>

<!-- Active Session Card -->
<div id="rqSessionCard" class="hidden">
    <!-- Meal Selector -->
    <div class="bg-white rounded-xl border border-gray-200 p-3 mb-3">
        <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2 block">Meals</label>
        <div class="flex flex-wrap gap-2" id="rqMealPills">
            <button onclick="rqToggleMeal('full_day')" data-meal="full_day" class="meal-pill text-xs font-medium px-3 py-1.5 rounded-full border border-gray-200 text-gray-500 transition">Full Day</button>
            <button onclick="rqToggleMeal('breakfast')" data-meal="breakfast" class="meal-pill text-xs font-medium px-3 py-1.5 rounded-full border border-gray-200 text-gray-500 transition">Breakfast</button>
            <button onclick="rqToggleMeal('lunch')" data-meal="lunch" class="meal-pill text-xs font-medium px-3 py-1.5 rounded-full border border-gray-200 text-gray-500 transition">Lunch</button>
            <button onclick="rqToggleMeal('dinner')" data-meal="dinner" class="meal-pill text-xs font-medium px-3 py-1.5 rounded-full border border-gray-200 text-gray-500 transition">Dinner</button>
            <button onclick="rqToggleMeal('picnic')" data-meal="picnic" class="meal-pill text-xs font-medium px-3 py-1.5 rounded-full border border-gray-200 text-gray-500 transition">Picnic</button>
        </div>
    </div>

    <!-- Guest Count -->
    <div class="bg-white rounded-xl border border-gray-200 p-3 mb-3 flex items-center justify-between">
        <div>
            <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Guest Count</div>
            <div class="text-lg font-bold text-gray-800" id="rqGuestCount">20</div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="rqAdjGuests(-5)" class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 font-bold text-lg flex items-center justify-center hover:bg-gray-200">-</button>
            <button onclick="rqAdjGuests(-1)" class="w-9 h-9 rounded-lg bg-gray-50 text-gray-500 font-medium flex items-center justify-center hover:bg-gray-100">-1</button>
            <button onclick="rqAdjGuests(1)" class="w-9 h-9 rounded-lg bg-gray-50 text-gray-500 font-medium flex items-center justify-center hover:bg-gray-100">+1</button>
            <button onclick="rqAdjGuests(5)" class="w-9 h-9 rounded-lg bg-orange-100 text-orange-600 font-bold text-lg flex items-center justify-center hover:bg-orange-200">+</button>
        </div>
    </div>

    <!-- Search -->
    <div class="relative mb-3">
        <input type="text" id="rqSearch" placeholder="Search items..." oninput="rqFilterItems()"
            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
        <svg class="absolute left-3 top-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    </div>

    <!-- Items by Category -->
    <div id="rqItemList"></div>

    <!-- Status Banner (for submitted/fulfilled sessions) -->
    <div id="rqStatusBanner" class="hidden"></div>
</div>

<!-- Empty State -->
<div id="rqEmptyState" class="text-center py-12">
    <div class="w-14 h-14 bg-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
    </div>
    <p class="text-sm text-gray-500 mb-3">No sessions for this date</p>
    <button onclick="rqCreateSession()" class="bg-orange-500 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-orange-600 transition">
        + New Requisition
    </button>
</div>

<!-- Sticky Bottom Bar -->
<div id="rqBottomBar" class="fixed bottom-16 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2.5 z-40 hidden">
    <div class="max-w-2xl mx-auto flex items-center justify-between">
        <div>
            <span class="text-xs text-gray-500">Items: <strong id="rqSummaryItems" class="text-gray-800">0</strong></span>
            <span class="mx-2 text-gray-300">|</span>
            <span class="text-xs text-gray-500">Order: <strong id="rqSummaryKg" class="text-orange-600">0 kg</strong></span>
        </div>
        <button onclick="rqSaveAndSubmit()" id="rqSubmitBtn" class="bg-orange-500 text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-orange-600 transition">
            Save & Submit
        </button>
    </div>
</div>

<script>
// ── State ──
let rqDate = todayStr();
let rqSessions = [];
let rqActiveSession = null;
let rqItems = [];
let rqGrouped = {};
let rqLines = {}; // { itemId: { portions, direct_kg, meal } }
let rqSelectedMeals = ['lunch'];
let rqGuestCount = 20;
let rqCollapsed = {}; // category collapse state

const RQ_MAX_DAYS_AHEAD = 7;
const RQ_KITCHEN_ID = <?= (int)$kitchenId ?>;

// ── Init ──
rqRenderDate();
rqLoadSessions();
rqLoadItems();

// ── Date Navigation ──
function rqNavDate(days) {
    const next = changeDate(rqDate, days);
    const today = todayStr();
    const maxDate = changeDate(today, RQ_MAX_DAYS_AHEAD);
    if (next < today || next > maxDate) return;
    rqDate = next;
    rqRenderDate();
    rqLoadSessions();
}

function rqRenderDate() {
    document.getElementById('rqDateDisplay').textContent = formatDate(rqDate);
    const today = todayStr();
    if (rqDate === today) {
        document.getElementById('rqDateRelative').textContent = 'Today';
    } else {
        const diff = Math.round((new Date(rqDate) - new Date(today)) / 86400000);
        document.getElementById('rqDateRelative').textContent = diff > 0 ? `+${diff} day${diff > 1 ? 's' : ''}` : `${diff} day${diff < -1 ? 's' : ''}`;
    }
    // Disable forward if at max
    const maxDate = changeDate(today, RQ_MAX_DAYS_AHEAD);
    document.getElementById('rqNextBtn').disabled = rqDate >= maxDate;
    document.getElementById('rqNextBtn').style.opacity = rqDate >= maxDate ? '0.3' : '1';
}

function rqShowDatePicker() {
    const today = todayStr();
    const maxDate = changeDate(today, RQ_MAX_DAYS_AHEAD);
    let html = '<div class="p-4"><h3 class="text-sm font-semibold text-gray-800 mb-3">Select Date</h3><div class="grid grid-cols-4 gap-2">';
    for (let i = 0; i <= RQ_MAX_DAYS_AHEAD; i++) {
        const d = changeDate(today, i);
        const isActive = d === rqDate;
        const dayName = i === 0 ? 'Today' : (i === 1 ? 'Tomorrow' : new Date(d + 'T00:00:00').toLocaleDateString('en', {weekday: 'short'}));
        const dayNum = new Date(d + 'T00:00:00').getDate();
        html += `<button onclick="rqDate='${d}';rqRenderDate();rqLoadSessions();closeSheet()"
            class="flex flex-col items-center p-2 rounded-xl border ${isActive ? 'bg-orange-500 text-white border-orange-500' : 'bg-white border-gray-200 text-gray-700 hover:bg-orange-50'}">
            <span class="text-[10px] font-medium">${dayName}</span>
            <span class="text-lg font-bold">${dayNum}</span>
        </button>`;
    }
    html += '</div></div>';
    openSheet(html);
}

// ── Sessions ──
async function rqLoadSessions() {
    try {
        const data = await api(`api/requisitions.php?action=list&date=${rqDate}&kitchen_id=${RQ_KITCHEN_ID}`);
        rqSessions = data.requisitions || [];
        rqRenderSessionTabs();

        if (rqSessions.length > 0) {
            // Load last session
            rqLoadSession(rqSessions[rqSessions.length - 1].id);
        } else {
            rqActiveSession = null;
            rqLines = {};
            document.getElementById('rqSessionCard').classList.add('hidden');
            document.getElementById('rqEmptyState').classList.remove('hidden');
            document.getElementById('rqBottomBar').classList.add('hidden');
        }
    } catch (e) {
        showToast('Failed to load sessions', 'error');
    }
}

function rqRenderSessionTabs() {
    const container = document.getElementById('rqSessionTabs');
    let html = '';
    rqSessions.forEach((s, i) => {
        const isActive = rqActiveSession && rqActiveSession.id === s.id;
        const statusColors = {
            draft: 'bg-gray-100 text-gray-700',
            submitted: 'bg-blue-100 text-blue-700',
            processing: 'bg-amber-100 text-amber-700',
            fulfilled: 'bg-green-100 text-green-700',
            received: 'bg-green-100 text-green-700',
            closed: 'bg-gray-200 text-gray-500'
        };
        const color = isActive ? 'bg-orange-500 text-white' : (statusColors[s.status] || 'bg-gray-100 text-gray-700');
        html += `<button onclick="rqLoadSession(${s.id})" class="text-xs font-semibold px-3 py-1.5 rounded-full ${color} whitespace-nowrap transition">
            Session ${s.session_number}
            ${s.status !== 'draft' ? `<span class="text-[9px] opacity-75 ml-1">${s.status}</span>` : ''}
        </button>`;
    });
    html += `<button class="text-xs font-semibold px-3 py-1.5 rounded-full bg-orange-100 text-orange-600 whitespace-nowrap hover:bg-orange-200 transition" onclick="rqCreateSession()">+ New</button>`;
    container.innerHTML = html;
}

async function rqCreateSession() {
    try {
        const data = await api('api/requisitions.php?action=create', {
            method: 'POST',
            body: JSON.stringify({
                req_date: rqDate,
                kitchen_id: RQ_KITCHEN_ID,
                guest_count: rqGuestCount,
                meals: rqSelectedMeals
            })
        });
        showToast('Session created', 'success');
        voice.sessionCreated(data.session_number);
        await rqLoadSessions();
        rqLoadSession(data.requisition_id);
    } catch (e) {
        showToast(e.message || 'Failed to create session', 'error');
    }
}

async function rqLoadSession(sessionId) {
    try {
        const data = await api(`api/requisitions.php?action=get&id=${sessionId}`);
        rqActiveSession = data.requisition;
        const lines = data.lines || [];

        // Restore state from session
        rqGuestCount = rqActiveSession.guest_count || 20;
        document.getElementById('rqGuestCount').textContent = rqGuestCount;

        rqSelectedMeals = (rqActiveSession.meals || 'lunch').split(',').map(m => m.trim());
        rqUpdateMealPills();

        // Restore lines to local state
        rqLines = {};
        lines.forEach(l => {
            rqLines[l.item_id] = {
                portions: l.portions || 0,
                direct_kg: parseFloat(l.required_kg) || 0,
                meal: l.meal || 'lunch'
            };
        });

        document.getElementById('rqSessionCard').classList.remove('hidden');
        document.getElementById('rqEmptyState').classList.add('hidden');
        rqRenderSessionTabs();
        rqRenderItems();
        rqUpdateSummary();
        rqRenderStatusBanner();

        // Show/hide bottom bar based on status
        const isDraft = rqActiveSession.status === 'draft';
        document.getElementById('rqBottomBar').classList.toggle('hidden', !isDraft);

    } catch (e) {
        showToast('Failed to load session', 'error');
    }
}

// ── Meals ──
function rqToggleMeal(meal) {
    if (!rqActiveSession || rqActiveSession.status !== 'draft') return;
    const idx = rqSelectedMeals.indexOf(meal);
    if (idx >= 0) {
        if (rqSelectedMeals.length <= 1) return; // must have at least 1
        rqSelectedMeals.splice(idx, 1);
    } else {
        rqSelectedMeals.push(meal);
    }
    rqUpdateMealPills();
}

function rqUpdateMealPills() {
    document.querySelectorAll('.meal-pill').forEach(btn => {
        const meal = btn.dataset.meal;
        const active = rqSelectedMeals.includes(meal);
        btn.className = `meal-pill text-xs font-medium px-3 py-1.5 rounded-full border transition ${active ? 'bg-orange-500 text-white border-orange-500' : 'border-gray-200 text-gray-500'}`;
    });
}

// ── Guests ──
function rqAdjGuests(delta) {
    if (!rqActiveSession || rqActiveSession.status !== 'draft') return;
    rqGuestCount = Math.max(1, rqGuestCount + delta);
    document.getElementById('rqGuestCount').textContent = rqGuestCount;
    rqRecalcAll();
}

// ── Items ──
async function rqLoadItems() {
    try {
        const data = await cachedApi('api/requisitions.php?action=get_items', 300000);
        rqItems = data.items || [];
        rqGrouped = data.grouped || {};
        rqRenderItems();
    } catch (e) {
        showToast('Failed to load items', 'error');
    }
}

function rqFilterItems() {
    const q = document.getElementById('rqSearch').value.toLowerCase();
    document.querySelectorAll('.rq-item-row').forEach(row => {
        const name = row.dataset.name.toLowerCase();
        row.style.display = name.includes(q) ? '' : 'none';
    });
    // Show/hide category headers with no visible items
    document.querySelectorAll('.rq-cat-group').forEach(group => {
        const visibleItems = group.querySelectorAll('.rq-item-row:not([style*="display: none"])');
        group.style.display = visibleItems.length > 0 ? '' : 'none';
    });
}

function rqRenderItems() {
    const container = document.getElementById('rqItemList');
    if (!rqActiveSession) { container.innerHTML = ''; return; }

    const isDraft = rqActiveSession.status === 'draft';
    let html = '';

    for (const [cat, items] of Object.entries(rqGrouped)) {
        const isCollapsed = rqCollapsed[cat] ?? false;
        const activeCount = items.filter(i => rqLines[i.id] && (rqLines[i.id].portions > 0 || rqLines[i.id].direct_kg > 0)).length;

        html += `<div class="rq-cat-group mb-2">
            <button onclick="rqToggleCat('${cat}')" class="w-full flex items-center justify-between bg-white border border-gray-200 rounded-xl px-3 py-2.5 mb-1">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform ${isCollapsed ? '' : 'rotate-90'}"><path d="m9 18 6-6-6-6"/></svg>
                    <span class="text-xs font-semibold text-gray-700">${cat}</span>
                    <span class="text-[10px] text-gray-400">(${items.length})</span>
                </div>
                ${activeCount > 0 ? `<span class="text-[10px] bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded-full font-semibold">${activeCount} selected</span>` : ''}
            </button>
            <div class="${isCollapsed ? 'hidden' : ''}" id="rqCat_${cat.replace(/[^a-zA-Z0-9]/g, '_')}">`;

        items.forEach(item => {
            const line = rqLines[item.id] || { portions: 0, direct_kg: 0 };
            const isPortion = item.order_mode === 'portion';
            const portionWeight = parseFloat(item.portion_weight) || 0.25;
            const stockQty = parseFloat(item.stock_qty) || 0;

            let requiredKg = 0;
            if (isPortion) {
                requiredKg = line.portions * portionWeight;
            } else {
                requiredKg = line.direct_kg || 0;
            }
            requiredKg = Math.ceil(requiredKg * 2) / 2;
            const orderQty = Math.max(0, Math.ceil((requiredKg - stockQty) * 2) / 2);

            // Stock badge color
            let stockBadge = '';
            if (requiredKg > 0) {
                if (stockQty >= requiredKg) {
                    stockBadge = '<span class="text-[9px] bg-green-100 text-green-700 px-1 py-0.5 rounded">In Stock</span>';
                } else if (stockQty > 0) {
                    stockBadge = `<span class="text-[9px] bg-amber-100 text-amber-700 px-1 py-0.5 rounded">Partial ${stockQty}${item.uom}</span>`;
                } else {
                    stockBadge = '<span class="text-[9px] bg-red-100 text-red-700 px-1 py-0.5 rounded">No Stock</span>';
                }
            }

            html += `<div class="rq-item-row bg-white border border-gray-100 rounded-lg px-3 py-2 mb-1" data-name="${item.name}" data-id="${item.id}">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium text-gray-800 truncate block">${item.name}</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            ${isPortion ? `<span class="text-[9px] text-gray-400">${portionWeight * 1000}g/portion</span>` : '<span class="text-[9px] bg-blue-50 text-blue-600 px-1 py-0.5 rounded">Direct KG</span>'}
                            ${stockBadge}
                        </div>
                    </div>
                    ${requiredKg > 0 && orderQty > 0 ? `<div class="text-right ml-2">
                        <div class="text-xs font-bold text-orange-600">${orderQty} ${item.uom}</div>
                        <div class="text-[9px] text-gray-400">to order</div>
                    </div>` : ''}
                </div>`;

            if (isDraft) {
                if (isPortion) {
                    html += `<div class="flex items-center gap-2 mt-1">
                        <button onclick="rqAdjItem(${item.id},-1)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-500 font-bold flex items-center justify-center hover:bg-gray-200 text-sm">-</button>
                        <input type="number" value="${line.portions}" min="0" onchange="rqSetPortions(${item.id}, this.value)"
                            class="w-16 text-center border border-gray-200 rounded-lg py-1 text-sm font-semibold focus:outline-none focus:ring-1 focus:ring-orange-300">
                        <button onclick="rqAdjItem(${item.id},1)" class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 font-bold flex items-center justify-center hover:bg-orange-200 text-sm">+</button>
                        <span class="text-xs text-gray-400 ml-1">portions</span>
                        ${requiredKg > 0 ? `<span class="text-xs text-gray-500 ml-auto">= ${requiredKg} ${item.uom}</span>` : ''}
                    </div>`;
                } else {
                    html += `<div class="flex items-center gap-2 mt-1">
                        <button onclick="rqAdjItemKg(${item.id},-0.5)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-500 font-bold flex items-center justify-center hover:bg-gray-200 text-sm">-</button>
                        <input type="number" value="${line.direct_kg || 0}" min="0" step="0.5" onchange="rqSetDirectKg(${item.id}, this.value)"
                            class="w-20 text-center border border-gray-200 rounded-lg py-1 text-sm font-semibold focus:outline-none focus:ring-1 focus:ring-orange-300">
                        <button onclick="rqAdjItemKg(${item.id},0.5)" class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 font-bold flex items-center justify-center hover:bg-orange-200 text-sm">+</button>
                        <span class="text-xs text-gray-400 ml-1">${item.uom}</span>
                    </div>`;
                }
            } else {
                // Read-only for non-draft
                if (isPortion && line.portions > 0) {
                    html += `<div class="text-xs text-gray-500 mt-1">${line.portions} portions = ${requiredKg} ${item.uom}</div>`;
                } else if (!isPortion && requiredKg > 0) {
                    html += `<div class="text-xs text-gray-500 mt-1">${requiredKg} ${item.uom}</div>`;
                }
            }

            html += `</div>`;
        });

        html += `</div></div>`;
    }

    container.innerHTML = html;
}

function rqToggleCat(cat) {
    rqCollapsed[cat] = !rqCollapsed[cat];
    rqRenderItems();
}

// ── Item quantity changes ──
function rqAdjItem(itemId, delta) {
    if (!rqLines[itemId]) rqLines[itemId] = { portions: 0, direct_kg: 0, meal: rqSelectedMeals[0] || 'lunch' };
    rqLines[itemId].portions = Math.max(0, (rqLines[itemId].portions || 0) + delta);
    rqRenderItems();
    rqUpdateSummary();
}

function rqSetPortions(itemId, val) {
    if (!rqLines[itemId]) rqLines[itemId] = { portions: 0, direct_kg: 0, meal: rqSelectedMeals[0] || 'lunch' };
    rqLines[itemId].portions = Math.max(0, parseInt(val) || 0);
    rqRenderItems();
    rqUpdateSummary();
}

function rqAdjItemKg(itemId, delta) {
    if (!rqLines[itemId]) rqLines[itemId] = { portions: 0, direct_kg: 0, meal: rqSelectedMeals[0] || 'lunch' };
    rqLines[itemId].direct_kg = Math.max(0, parseFloat(((rqLines[itemId].direct_kg || 0) + delta).toFixed(1)));
    rqRenderItems();
    rqUpdateSummary();
}

function rqSetDirectKg(itemId, val) {
    if (!rqLines[itemId]) rqLines[itemId] = { portions: 0, direct_kg: 0, meal: rqSelectedMeals[0] || 'lunch' };
    rqLines[itemId].direct_kg = Math.max(0, parseFloat(val) || 0);
    rqRenderItems();
    rqUpdateSummary();
}

function rqRecalcAll() {
    // Re-render to recalculate with new guest count (portions stay same, KG changes in display)
    rqRenderItems();
    rqUpdateSummary();
}

// ── Summary ──
function rqUpdateSummary() {
    let totalItems = 0;
    let totalKg = 0;

    for (const [itemId, line] of Object.entries(rqLines)) {
        const item = rqItems.find(i => i.id == itemId);
        if (!item) continue;

        let requiredKg = 0;
        if (item.order_mode === 'portion') {
            requiredKg = (line.portions || 0) * (parseFloat(item.portion_weight) || 0.25);
        } else {
            requiredKg = line.direct_kg || 0;
        }
        requiredKg = Math.ceil(requiredKg * 2) / 2;
        if (requiredKg <= 0) continue;

        const stockQty = parseFloat(item.stock_qty) || 0;
        const orderQty = Math.max(0, Math.ceil((requiredKg - stockQty) * 2) / 2);

        if (orderQty > 0) {
            totalItems++;
            totalKg += orderQty;
        }
    }

    document.getElementById('rqSummaryItems').textContent = totalItems;
    document.getElementById('rqSummaryKg').textContent = totalKg.toFixed(1) + ' kg';
}

// ── Status Banner ──
function rqRenderStatusBanner() {
    const banner = document.getElementById('rqStatusBanner');
    if (!rqActiveSession || rqActiveSession.status === 'draft') {
        banner.classList.add('hidden');
        return;
    }

    const statusConfig = {
        submitted: { bg: 'bg-blue-50 border-blue-200', text: 'text-blue-700', label: 'Submitted — Waiting for store', icon: '&#9203;' },
        processing: { bg: 'bg-amber-50 border-amber-200', text: 'text-amber-700', label: 'Being Processed', icon: '&#9881;' },
        fulfilled: { bg: 'bg-green-50 border-green-200', text: 'text-green-700', label: 'Fulfilled — Ready to confirm receipt', icon: '&#9989;', action: true },
        received: { bg: 'bg-green-50 border-green-200', text: 'text-green-700', label: 'Received', icon: '&#10004;' },
        closed: { bg: 'bg-gray-50 border-gray-200', text: 'text-gray-500', label: 'Closed', icon: '&#128274;' }
    };

    const cfg = statusConfig[rqActiveSession.status];
    if (!cfg) { banner.classList.add('hidden'); return; }

    let html = `<div class="${cfg.bg} border rounded-xl px-4 py-3 mb-3">
        <div class="flex items-center gap-2">
            <span class="text-lg">${cfg.icon}</span>
            <span class="text-sm font-semibold ${cfg.text}">${cfg.label}</span>
        </div>`;

    if (cfg.action && rqActiveSession.status === 'fulfilled') {
        html += `<button onclick="rqShowReceiptSheet()" class="mt-2 w-full bg-green-500 text-white py-2 rounded-lg text-sm font-semibold hover:bg-green-600 transition">Confirm Receipt</button>`;
    }

    html += '</div>';
    banner.innerHTML = html;
    banner.classList.remove('hidden');
}

// ── Receipt Confirmation ──
async function rqShowReceiptSheet() {
    if (!rqActiveSession) return;
    const data = await api(`api/requisitions.php?action=get&id=${rqActiveSession.id}`);
    const lines = data.lines || [];

    let html = `<div class="p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Confirm Receipt — Session ${rqActiveSession.session_number}</h3>
        <div class="space-y-2 max-h-[55vh] overflow-y-auto">`;

    lines.forEach(l => {
        const fulfilledQty = parseFloat(l.fulfilled_qty) || 0;
        html += `<div class="bg-gray-50 rounded-lg px-3 py-2">
            <div class="text-sm font-medium text-gray-800">${l.item_name}</div>
            <div class="flex items-center justify-between mt-1">
                <span class="text-xs text-gray-500">Sent: ${fulfilledQty} ${l.uom}</span>
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-500">Got:</span>
                    <input type="number" value="${fulfilledQty}" min="0" step="0.5" data-line-id="${l.id}"
                        class="recv-qty w-16 text-center border border-gray-200 rounded py-1 text-sm font-semibold focus:outline-none focus:ring-1 focus:ring-green-300">
                    <span class="text-xs text-gray-400">${l.uom}</span>
                </div>
            </div>
        </div>`;
    });

    html += `</div>
        <button onclick="rqConfirmReceipt()" class="mt-3 w-full bg-green-500 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-green-600 transition">Confirm Receipt</button>
    </div>`;
    openSheet(html);
}

async function rqConfirmReceipt() {
    const inputs = document.querySelectorAll('.recv-qty');
    const lines = [];
    inputs.forEach(inp => {
        lines.push({ id: parseInt(inp.dataset.lineId), received_qty: parseFloat(inp.value) || 0 });
    });

    try {
        const data = await api('api/requisitions.php?action=confirm_receipt', {
            method: 'POST',
            body: JSON.stringify({ requisition_id: rqActiveSession.id, lines })
        });
        closeSheet();
        showToast(data.has_dispute ? 'Receipt confirmed with disputes' : 'Receipt confirmed', data.has_dispute ? 'warning' : 'success');
        voice.orderReceived(rqActiveSession.session_number);
        if (data.has_dispute) voice.say('Note: There are quantity disputes on this order.');
        rqLoadSession(rqActiveSession.id);
    } catch (e) {
        showToast(e.message || 'Failed to confirm', 'error');
    }
}

// ── Save & Submit ──
async function rqSaveAndSubmit() {
    if (!rqActiveSession) return;
    const btn = document.getElementById('rqSubmitBtn');
    setLoading(btn, true);

    try {
        // Build lines from rqLines
        const linesToSave = [];
        for (const [itemId, line] of Object.entries(rqLines)) {
            if ((line.portions || 0) <= 0 && (line.direct_kg || 0) <= 0) continue;
            linesToSave.push({
                item_id: parseInt(itemId),
                meal: rqSelectedMeals[0] || 'lunch',
                portions: line.portions || 0,
                direct_kg: line.direct_kg || 0
            });
        }

        if (linesToSave.length === 0) {
            showToast('Add items before submitting', 'warning');
            setLoading(btn, false);
            return;
        }

        // Update session meals & guest count
        await api('api/requisitions.php?action=save_lines', {
            method: 'POST',
            body: JSON.stringify({ requisition_id: rqActiveSession.id, lines: linesToSave })
        });

        // Submit
        await api('api/requisitions.php?action=submit', {
            method: 'POST',
            body: JSON.stringify({ requisition_id: rqActiveSession.id })
        });

        showToast('Requisition submitted!', 'success');
        voice.orderSubmitted(rqActiveSession.session_number, '<?= addslashes($kitchenName) ?>');
        rqLoadSessions();

    } catch (e) {
        showToast(e.message || 'Failed to submit', 'error');
        voice.error('Failed to submit order');
    } finally {
        setLoading(btn, false);
    }
}
</script>
