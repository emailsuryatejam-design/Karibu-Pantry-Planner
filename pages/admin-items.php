<?php
/**
 * Karibu Pantry Planner — Admin Item Management
 */
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
?>

<!-- Header -->
<div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-bold text-gray-800">Item Management</h2>
    <button onclick="aiShowCreate()" class="bg-orange-500 text-white px-3 py-2 rounded-xl text-xs font-semibold hover:bg-orange-600 transition flex items-center gap-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Add Item
    </button>
</div>

<!-- Search -->
<div class="relative mb-3">
    <input type="text" id="aiSearch" placeholder="Search items..." oninput="aiDebounceSearch()"
        class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
    <svg class="absolute left-3 top-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
</div>

<!-- Category Filter -->
<div class="flex gap-2 overflow-x-auto pb-2 mb-3" id="aiCatFilter">
    <button onclick="aiFilterCat('')" class="ai-cat-btn text-xs font-medium px-3 py-1.5 rounded-full bg-orange-500 text-white whitespace-nowrap">All</button>
</div>

<!-- Items List -->
<div id="aiItemList" class="space-y-1"></div>

<script>
let aiItems = [];
let aiCategories = [];
let aiCurrentCat = '';
const aiSearchDebounce = debounce(() => aiLoad(), 300);
function aiDebounceSearch() { aiSearchDebounce(); }

aiLoad();
aiLoadCategories();

async function aiLoadCategories() {
    try {
        const data = await api('api/items.php?action=categories');
        aiCategories = data.categories || [];
        let html = `<button onclick="aiFilterCat('')" class="ai-cat-btn text-xs font-medium px-3 py-1.5 rounded-full ${!aiCurrentCat ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600'} whitespace-nowrap">All</button>`;
        aiCategories.forEach(c => {
            html += `<button onclick="aiFilterCat('${c}')" class="ai-cat-btn text-xs font-medium px-3 py-1.5 rounded-full ${aiCurrentCat === c ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600'} whitespace-nowrap">${c}</button>`;
        });
        document.getElementById('aiCatFilter').innerHTML = html;
    } catch(e) {}
}

function aiFilterCat(cat) {
    aiCurrentCat = cat;
    aiLoadCategories();
    aiLoad();
}

async function aiLoad() {
    const q = document.getElementById('aiSearch').value;
    let url = `api/items.php?action=list&active=0`;
    if (q) url += `&q=${encodeURIComponent(q)}`;
    if (aiCurrentCat) url += `&category=${encodeURIComponent(aiCurrentCat)}`;

    try {
        const data = await api(url);
        aiItems = data.items || [];
        aiRender();
    } catch(e) {
        showToast('Failed to load items', 'error');
    }
}

function aiRender() {
    const container = document.getElementById('aiItemList');
    if (aiItems.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-8 text-sm">No items found</p>';
        return;
    }

    let html = '';
    aiItems.forEach(item => {
        const pw = parseFloat(item.portion_weight) || 0.25;
        const isDirect = item.order_mode === 'direct_kg';
        html += `<div class="bg-white border border-gray-200 rounded-xl px-3 py-2.5 ${item.is_active ? '' : 'opacity-50'}">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-800 truncate">${item.name}</span>
                        ${!item.is_active ? '<span class="text-[9px] bg-red-100 text-red-600 px-1 py-0.5 rounded">Inactive</span>' : ''}
                    </div>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="text-[10px] text-gray-400">${item.code || '—'}</span>
                        <span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">${item.category || 'Uncategorized'}</span>
                        <span class="text-[10px] ${isDirect ? 'bg-blue-50 text-blue-600' : 'bg-orange-50 text-orange-600'} px-1.5 py-0.5 rounded font-medium">
                            ${isDirect ? 'Direct KG' : `${(pw * 1000).toFixed(0)}g/portion`}
                        </span>
                        <span class="text-[10px] text-gray-400">${item.uom}</span>
                    </div>
                </div>
                <button onclick="aiShowEdit(${item.id})" class="p-2 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
                </button>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function aiShowCreate() { aiOpenForm(null); }

function aiShowEdit(id) {
    const item = aiItems.find(i => i.id === id);
    if (item) aiOpenForm(item);
}

function aiOpenForm(item) {
    const isEdit = !!item;
    const html = `<div class="p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">${isEdit ? 'Edit' : 'New'} Item</h3>
        <div class="space-y-3">
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Name *</label>
                <input type="text" id="aiFormName" value="${item?.name || ''}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Code</label>
                    <input type="text" id="aiFormCode" value="${item?.code || ''}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                </div>
                <div>
                    <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Category</label>
                    <input type="text" id="aiFormCat" value="${item?.category || ''}" list="aiCatList" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <datalist id="aiCatList">${aiCategories.map(c => `<option value="${c}">`).join('')}</datalist>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">UOM</label>
                    <select id="aiFormUom" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <option value="kg" ${(item?.uom || 'kg') === 'kg' ? 'selected' : ''}>kg</option>
                        <option value="grams" ${item?.uom === 'grams' ? 'selected' : ''}>grams</option>
                        <option value="ltr" ${item?.uom === 'ltr' ? 'selected' : ''}>ltr</option>
                        <option value="ml" ${item?.uom === 'ml' ? 'selected' : ''}>ml</option>
                        <option value="pcs" ${item?.uom === 'pcs' ? 'selected' : ''}>pcs</option>
                        <option value="tins" ${item?.uom === 'tins' ? 'selected' : ''}>tins</option>
                        <option value="box" ${item?.uom === 'box' ? 'selected' : ''}>box</option>
                        <option value="pkt" ${item?.uom === 'pkt' ? 'selected' : ''}>pkt</option>
                        <option value="bottle" ${item?.uom === 'bottle' ? 'selected' : ''}>bottle</option>
                        <option value="bunch" ${item?.uom === 'bunch' ? 'selected' : ''}>bunch</option>
                        <option value="unit" ${item?.uom === 'unit' ? 'selected' : ''}>unit</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Portion Weight (kg)</label>
                    <input type="number" id="aiFormPW" step="0.001" min="0.001" value="${item?.portion_weight || '0.250'}" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200">
                </div>
            </div>
            <div>
                <label class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1 block">Order Mode</label>
                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('aiFormMode').value='portion';this.className=this.className.replace('bg-gray-100 text-gray-600','bg-orange-500 text-white');this.nextElementSibling.className=this.nextElementSibling.className.replace('bg-orange-500 text-white','bg-gray-100 text-gray-600')"
                        class="flex-1 py-2 rounded-lg text-xs font-semibold text-center transition ${(!item || item.order_mode === 'portion') ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600'}">By Portions</button>
                    <button type="button" onclick="document.getElementById('aiFormMode').value='direct_kg';this.className=this.className.replace('bg-gray-100 text-gray-600','bg-orange-500 text-white');this.previousElementSibling.className=this.previousElementSibling.className.replace('bg-orange-500 text-white','bg-gray-100 text-gray-600')"
                        class="flex-1 py-2 rounded-lg text-xs font-semibold text-center transition ${item?.order_mode === 'direct_kg' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600'}">Direct KG</button>
                </div>
                <input type="hidden" id="aiFormMode" value="${item?.order_mode || 'portion'}">
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            ${isEdit ? `<button onclick="aiToggleActive(${item.id})" class="flex-1 py-2.5 rounded-xl text-xs font-semibold ${item.is_active ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100'} transition">${item.is_active ? 'Deactivate' : 'Activate'}</button>` : ''}
            <button onclick="aiSaveItem(${item?.id || 0})" class="flex-1 bg-orange-500 text-white py-2.5 rounded-xl text-xs font-semibold hover:bg-orange-600 transition">Save</button>
        </div>
    </div>`;
    openSheet(html);
}

async function aiSaveItem(id) {
    const data = {
        id: id || undefined,
        name: document.getElementById('aiFormName').value,
        code: document.getElementById('aiFormCode').value,
        category: document.getElementById('aiFormCat').value,
        uom: document.getElementById('aiFormUom').value,
        portion_weight: parseFloat(document.getElementById('aiFormPW').value) || 0.25,
        order_mode: document.getElementById('aiFormMode').value
    };
    try {
        await api('api/items.php?action=save', { method: 'POST', body: JSON.stringify(data) });
        closeSheet();
        showToast(id ? 'Item updated' : 'Item created', 'success');
        aiLoad();
    } catch(e) {
        showToast(e.message || 'Failed to save', 'error');
    }
}

async function aiToggleActive(id) {
    try {
        await api('api/items.php?action=toggle_active', { method: 'POST', body: JSON.stringify({ id }) });
        closeSheet();
        showToast('Status changed', 'success');
        aiLoad();
    } catch(e) {
        showToast(e.message || 'Failed', 'error');
    }
}
</script>
