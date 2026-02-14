<!-- Daily Overview — All activity for a given date -->
<div id="overviewApp">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-green-600"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                Daily Overview
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">Stock, orders & kitchen usage</p>
        </div>
    </div>

    <!-- Date Picker -->
    <div class="flex items-center justify-between bg-white rounded-xl border border-gray-100 px-4 py-3 mb-3">
        <button onclick="oNavDate(-1)" class="p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="text-center">
            <p class="text-sm font-semibold text-gray-900" id="oDateDisplay"></p>
            <span id="oTodayBadge" class="text-[10px] text-green-600 font-medium hidden">Today</span>
            <button id="oGoTodayBtn" onclick="oGoToday()" class="text-[10px] text-green-600 font-medium hidden">Go to Today</button>
        </div>
        <button onclick="oNavDate(1)" class="p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200 compact-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-600"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>

    <!-- Error -->
    <div id="oErrorBox" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-3">
        <p class="text-sm text-red-700" id="oErrorText"></p>
    </div>

    <!-- Summary Cards -->
    <div id="oSummary" class="hidden grid grid-cols-3 gap-2 mb-3">
        <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-blue-500 mx-auto mb-1"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
            <p class="text-sm font-bold text-gray-900" id="oTotalItems">0</p>
            <p class="text-[10px] text-gray-500">Items</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-green-500 mx-auto mb-1"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            <p class="text-sm font-bold text-gray-900" id="oTotalOrdered">0</p>
            <p class="text-[10px] text-gray-500">Ordered</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-orange-500 mx-auto mb-1"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
            <p class="text-sm font-bold text-gray-900" id="oTotalKitchen">0</p>
            <p class="text-[10px] text-gray-500">Kitchen</p>
        </div>
    </div>

    <!-- Search -->
    <div class="relative mb-3" id="oSearchBox" style="display:none">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="oSearchInput" oninput="oFilter()" placeholder="Filter items..."
            class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-green-200">
    </div>

    <!-- Loading -->
    <div id="oLoading" class="flex flex-col items-center justify-center py-16">
        <svg class="animate-spin text-green-500 mb-3" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        <p class="text-sm text-gray-500">Loading overview...</p>
    </div>

    <!-- Empty -->
    <div id="oEmpty" class="hidden bg-white rounded-xl border border-gray-100 p-6 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-300 mx-auto mb-2"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
        <p class="text-sm text-gray-500 mb-1">No activity for this date</p>
        <p class="text-xs text-gray-400">Items will show when menu plans have ingredients</p>
    </div>

    <!-- Items Table -->
    <div id="oContent" class="hidden bg-white rounded-xl border border-gray-100 overflow-hidden">
        <!-- Column Headers -->
        <div class="grid grid-cols-[1fr_50px_50px_50px] gap-1 px-3 py-2 bg-gray-50 text-[9px] font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
            <span>Item</span>
            <span class="text-center">Stock</span>
            <span class="text-center">Order</span>
            <span class="text-center">Kitchen</span>
        </div>
        <div id="oItems"></div>
    </div>

    <p id="oFooter" class="hidden text-xs text-gray-400 text-center mt-3"></p>
</div>

<script>
let oDate = todayStr();
let oItems = [];
let oFiltered = [];

oLoadData();

function oNavDate(days) { oDate = changeDate(oDate, days); oRenderDate(); oLoadData(); }
function oGoToday() { oDate = todayStr(); oRenderDate(); oLoadData(); }

function oRenderDate() {
    document.getElementById('oDateDisplay').textContent = formatDate(oDate);
    const isToday = oDate === todayStr();
    document.getElementById('oTodayBadge').classList.toggle('hidden', !isToday);
    document.getElementById('oGoTodayBtn').classList.toggle('hidden', isToday);
}

async function oLoadData() {
    oRenderDate();
    document.getElementById('oLoading').classList.remove('hidden');
    document.getElementById('oContent').classList.add('hidden');
    document.getElementById('oEmpty').classList.add('hidden');
    document.getElementById('oSummary').classList.add('hidden');
    document.getElementById('oErrorBox').classList.add('hidden');
    document.getElementById('oSearchBox').style.display = 'none';
    document.getElementById('oFooter').classList.add('hidden');

    try {
        const data = await api(`api/daily-overview.php?date=${oDate}`);
        oItems = data.items || [];
        oFiltered = [...oItems];
        oRender();
    } catch (err) {
        document.getElementById('oErrorText').textContent = err.message;
        document.getElementById('oErrorBox').classList.remove('hidden');
    } finally {
        document.getElementById('oLoading').classList.add('hidden');
    }
}

function oFilter() {
    const q = (document.getElementById('oSearchInput').value || '').toLowerCase().trim();
    oFiltered = q ? oItems.filter(i => i.name.toLowerCase().includes(q)) : [...oItems];
    oRenderItems();
}

function oRender() {
    if (oItems.length === 0) {
        document.getElementById('oEmpty').classList.remove('hidden');
        return;
    }

    // Summary
    document.getElementById('oSummary').classList.remove('hidden');
    document.getElementById('oTotalItems').textContent = oItems.length;
    document.getElementById('oTotalOrdered').textContent = Math.round(oItems.reduce((s, i) => s + (i.ordered || 0), 0));
    document.getElementById('oTotalKitchen').textContent = Math.round(oItems.reduce((s, i) => s + (i.kitchen_qty || 0), 0));

    // Search + table
    document.getElementById('oSearchBox').style.display = 'block';
    document.getElementById('oContent').classList.remove('hidden');
    document.getElementById('oFooter').classList.remove('hidden');

    oRenderItems();
}

function oRenderItems() {
    const container = document.getElementById('oItems');
    document.getElementById('oFooter').textContent = `${oFiltered.length} items shown`;

    if (oFiltered.length === 0) {
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">No matching items</p>';
        return;
    }

    container.innerHTML = oFiltered.map(item => {
        const stockColor = item.stock <= 0 ? 'bg-red-100 text-red-700'
            : item.stock < (item.kitchen_qty || 5) ? 'bg-amber-100 text-amber-700'
            : 'bg-blue-50 text-blue-700';

        return `
        <div class="grid grid-cols-[1fr_50px_50px_50px] gap-1 px-3 py-2.5 border-b border-gray-50 items-center">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">${item.name}</p>
                <p class="text-[10px] text-gray-400">${item.uom}</p>
            </div>
            <div class="text-center">
                <span class="inline-block text-[11px] font-bold rounded-md py-0.5 px-1.5 ${stockColor}">${Math.round(item.stock * 10) / 10 || 0}</span>
            </div>
            <div class="text-center">
                ${item.ordered > 0
                    ? `<span class="inline-block text-[11px] font-bold rounded-md py-0.5 px-1.5 bg-green-100 text-green-700">${Math.round(item.ordered * 10) / 10}</span>`
                    : '<span class="text-[10px] text-gray-300">\u2014</span>'}
            </div>
            <div class="text-center">
                ${item.kitchen_qty > 0
                    ? `<span class="inline-block text-[11px] font-bold rounded-md py-0.5 px-1.5 bg-orange-100 text-orange-700">${Math.round(item.kitchen_qty * 10) / 10}</span>`
                    : '<span class="text-[10px] text-gray-300">\u2014</span>'}
            </div>
        </div>`;
    }).join('');
}
</script>
