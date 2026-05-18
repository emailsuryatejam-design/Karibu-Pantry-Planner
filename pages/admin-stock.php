<?php if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; } ?>

<!-- Header -->
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Stock Control</h1>
        <p class="text-xs text-gray-400 mt-0.5" id="asCount">Loading...</p>
    </div>
    <button onclick="asExport()" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 compact-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
    </button>
</div>

<!-- Search -->
<div class="relative mb-3">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" id="asSearchInput" placeholder="Search by name or code…" oninput="asOnSearch(this.value)"
        class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-slate-300 bg-white">
</div>

<!-- Category pills -->
<div class="flex gap-1.5 mb-3 overflow-x-auto pb-1" id="asCatPills">
    <button onclick="asSetCategory('')" id="asCatAll" class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 text-white transition">All</button>
</div>

<!-- Low stock alert banner -->
<div id="asAlertBanner" class="hidden mb-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-2.5 text-xs font-semibold flex items-center gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
    <span id="asAlertText"></span>
</div>

<!-- Item list -->
<div id="asList" class="space-y-2">
    <div class="text-center py-10 text-gray-300 text-sm">Loading...</div>
</div>

<script>
let asItems    = [];
let asSearch   = '';
let asCategory = '';

// ── Boot ──────────────────────────────────────────
(async () => {
    try {
        await asLoad();
    } catch (e) {
        showToast('Failed to load items', 'error');
        document.getElementById('asList').innerHTML = '<div class="text-center py-10 text-red-400 text-sm">Failed to load items</div>';
    }
})();

// ── Data ──────────────────────────────────────────
async function asLoad() {
    const d = await api('api/items.php?action=list');
    asItems = (d.items || []).filter(i => i.is_active == 1);
    asBuildCategoryPills();
    asRender();
}

// ── Category pills ────────────────────────────────
function asBuildCategoryPills() {
    const cats = [...new Set(asItems.map(i => i.category || 'Uncategorized'))].sort();
    const container = document.getElementById('asCatPills');
    const allBtn    = document.getElementById('asCatAll');

    // Remove any previously-generated pills (keep the "All" button)
    Array.from(container.children).forEach(el => {
        if (el !== allBtn) el.remove();
    });

    cats.forEach(cat => {
        const btn = document.createElement('button');
        btn.id          = 'asCat_' + cat;
        btn.className   = 'shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition';
        btn.textContent = asCapFirst(cat);
        btn.onclick     = () => asSetCategory(cat);
        container.appendChild(btn);
    });
}

function asCapFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function asSetCategory(cat) {
    asCategory = cat;
    // Reset all pills
    document.querySelectorAll('[id^="asCat"]').forEach(b => {
        b.className = b.className.replace('bg-slate-800 text-white', 'bg-gray-100 text-gray-600');
    });
    const activeId = cat ? 'asCat_' + cat : 'asCatAll';
    const activeEl = document.getElementById(activeId);
    if (activeEl) activeEl.className = activeEl.className.replace('bg-gray-100 text-gray-600', 'bg-slate-800 text-white');
    asRender();
}

// ── Search ────────────────────────────────────────
function asOnSearch(val) {
    asSearch = val.trim().toLowerCase();
    asRender();
}

// ── Render ────────────────────────────────────────
function asRender() {
    let filtered = asItems;

    if (asSearch) {
        filtered = filtered.filter(i =>
            (i.name  || '').toLowerCase().includes(asSearch) ||
            (i.code  || '').toLowerCase().includes(asSearch)
        );
    }
    if (asCategory) {
        filtered = filtered.filter(i => (i.category || 'Uncategorized') === asCategory);
    }

    const lowStock      = filtered.filter(i => parseFloat(i.stock_qty) < 2);
    const criticalCount = asItems.filter(i => parseFloat(i.stock_qty) < 2).length;

    // Update subtitle
    const lowAll = asItems.filter(i => parseFloat(i.stock_qty) < 10).length;
    document.getElementById('asCount').textContent =
        `${asItems.length} item${asItems.length !== 1 ? 's' : ''} · ${lowAll} low stock`;

    // Banner
    const banner = document.getElementById('asAlertBanner');
    if (criticalCount > 0) {
        banner.classList.remove('hidden');
        document.getElementById('asAlertText').textContent =
            `${criticalCount} item${criticalCount !== 1 ? 's are' : ' is'} critically low`;
    } else {
        banner.classList.add('hidden');
    }

    if (!filtered.length) {
        document.getElementById('asList').innerHTML =
            '<div class="text-center py-10 text-gray-400 text-sm">No items found</div>';
        return;
    }

    document.getElementById('asList').innerHTML = filtered.map(item => {
        const qty = parseFloat(item.stock_qty ?? 0);
        const qtyColor = qty < 2 ? 'text-red-600' : qty < 10 ? 'text-amber-500' : 'text-green-600';
        const catLabel = asCapFirst(item.category || 'Uncategorized');

        return `
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3" id="asCard_${item.id}">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-800">${escHtml(item.name)}</span>
                        ${item.code ? `<span class="text-[10px] font-mono bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">${escHtml(item.code)}</span>` : ''}
                        <span class="text-[10px] font-semibold bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">${escHtml(catLabel)}</span>
                    </div>
                    <div class="text-[10px] text-gray-400 mt-0.5">${escHtml(item.uom || '—')}</div>
                </div>
                <div class="shrink-0 flex flex-col items-end gap-0.5">
                    <div id="asQtyDisplay_${item.id}" class="flex items-baseline gap-1 cursor-pointer group" onclick="asEditStock(${item.id}, ${qty})">
                        <span class="text-xl font-bold ${qtyColor} group-hover:opacity-70 transition">${qty % 1 === 0 ? qty.toFixed(0) : qty.toFixed(2)}</span>
                        <span class="text-[10px] text-gray-400">${escHtml(item.uom || '')}</span>
                    </div>
                    <div class="text-[9px] text-gray-300">tap to edit</div>
                </div>
            </div>
        </div>`;
    }).join('');
}

// ── Inline edit ───────────────────────────────────
function asEditStock(itemId, currentQty) {
    const displayEl = document.getElementById('asQtyDisplay_' + itemId);
    if (!displayEl) return;
    if (displayEl.querySelector('input')) return; // already editing

    const item = asItems.find(i => i.id == itemId);
    const uom  = item ? escHtml(item.uom || '') : '';

    const original = displayEl.innerHTML;

    displayEl.innerHTML = `
        <div class="flex items-center gap-1">
            <input type="number" id="asQtyInput_${itemId}"
                min="0" step="0.01"
                value="${currentQty % 1 === 0 ? currentQty.toFixed(0) : currentQty.toFixed(2)}"
                class="w-20 border border-slate-300 rounded-lg px-2 py-1 text-sm font-bold text-center focus:outline-none focus:ring-2 focus:ring-slate-400"
                onkeydown="asQtyKeydown(event, ${itemId})"
            >
            <span class="text-[10px] text-gray-400">${uom}</span>
        </div>`;

    const input = document.getElementById('asQtyInput_' + itemId);
    input.focus();
    input.select();

    input.addEventListener('blur', () => {
        // Small delay to allow Enter handler to fire first
        setTimeout(() => {
            const el = document.getElementById('asQtyInput_' + itemId);
            if (el) {
                const newQty = parseFloat(el.value);
                if (isNaN(newQty) || newQty < 0) {
                    displayEl.innerHTML = original;
                } else {
                    asSaveStock(itemId, newQty, displayEl, original);
                }
            }
        }, 100);
    });
}

function asQtyKeydown(e, itemId) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const input = document.getElementById('asQtyInput_' + itemId);
        if (!input) return;
        const newQty = parseFloat(input.value);
        const displayEl = input.closest('[id^="asQtyDisplay_"]');
        if (isNaN(newQty) || newQty < 0) {
            showToast('Enter a valid quantity', 'warning');
            return;
        }
        input.removeEventListener('blur', () => {});
        input._saved = true;
        asSaveStock(itemId, newQty, displayEl, null);
    } else if (e.key === 'Escape') {
        const input = document.getElementById('asQtyInput_' + itemId);
        if (!input) return;
        const displayEl = input.closest('[id^="asQtyDisplay_"]');
        // Re-render to restore original display
        const item = asItems.find(i => i.id == itemId);
        if (item && displayEl) {
            const qty = parseFloat(item.stock_qty ?? 0);
            const qtyColor = qty < 2 ? 'text-red-600' : qty < 10 ? 'text-amber-500' : 'text-green-600';
            displayEl.innerHTML = `
                <div class="flex items-baseline gap-1 cursor-pointer group" onclick="asEditStock(${item.id}, ${qty})">
                    <span class="text-xl font-bold ${qtyColor} group-hover:opacity-70 transition">${qty % 1 === 0 ? qty.toFixed(0) : qty.toFixed(2)}</span>
                    <span class="text-[10px] text-gray-400">${escHtml(item.uom || '')}</span>
                </div>`;
        }
    }
}

// ── Save stock ────────────────────────────────────
async function asSaveStock(itemId, newQty, displayEl, _unused) {
    try {
        const res = await api('api/items.php', {
            method: 'POST',
            body: { action: 'update_stock', item_id: itemId, stock_qty: newQty }
        });

        // Update local data
        const item = asItems.find(i => i.id == itemId);
        if (item) item.stock_qty = res.stock_qty ?? newQty;

        showToast('Stock updated', 'success');
        asRender(); // full re-render to update colors + banner
    } catch (e) {
        showToast(e.message || 'Failed to update stock', 'error');
        // Restore display from local data
        const item = asItems.find(i => i.id == itemId);
        if (item && displayEl) {
            const qty = parseFloat(item.stock_qty ?? 0);
            const qtyColor = qty < 2 ? 'text-red-600' : qty < 10 ? 'text-amber-500' : 'text-green-600';
            displayEl.innerHTML = `
                <div class="flex items-baseline gap-1 cursor-pointer group" onclick="asEditStock(${item.id}, ${qty})">
                    <span class="text-xl font-bold ${qtyColor} group-hover:opacity-70 transition">${qty % 1 === 0 ? qty.toFixed(0) : qty.toFixed(2)}</span>
                    <span class="text-[10px] text-gray-400">${escHtml(item.uom || '')}</span>
                </div>`;
        }
    }
}

// ── Export ────────────────────────────────────────
function asExport() {
    const rows = [['Name', 'Code', 'Category', 'UOM', 'Stock Qty']];
    asItems.forEach(i => {
        rows.push([
            i.name  || '',
            i.code  || '',
            i.category || 'Uncategorized',
            i.uom   || '',
            parseFloat(i.stock_qty ?? 0)
        ]);
    });
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'stock-' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
