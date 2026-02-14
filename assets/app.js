/**
 * Karibu Pantry Planner — Vanilla JS Helpers
 */

// ── API Helper ──
async function api(endpoint, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const config = {
        method,
        headers: { 'Content-Type': 'application/json', ...options.headers },
    };
    if (options.body && method !== 'GET') {
        config.body = JSON.stringify(options.body);
    }
    const response = await fetch(endpoint, config);
    const text = await response.text();
    let data;
    try {
        data = text ? JSON.parse(text) : {};
    } catch {
        throw new Error('Invalid server response');
    }
    if (!response.ok) {
        throw new Error(data.error || data.message || 'Request failed');
    }
    return data;
}

// ── Date Helpers ──
function toDateStr(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function todayStr() {
    return toDateStr(new Date());
}

function formatDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

function changeDate(currentDate, days) {
    const d = new Date(currentDate + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return toDateStr(d);
}

// ── Toast Notification ──
function showToast(message, type = 'success') {
    const existing = document.getElementById('toast');
    if (existing) existing.remove();

    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        warning: 'bg-amber-600',
        info: 'bg-blue-600',
    };

    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = `fixed top-16 left-1/2 -translate-x-1/2 ${colors[type] || colors.info} text-white px-4 py-2.5 rounded-xl shadow-lg text-sm font-medium z-[200] animate-fade-in`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('animate-fade-out');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

// ── Bottom Sheet ──
function openSheet(contentHtml) {
    closeSheet();
    const backdrop = document.createElement('div');
    backdrop.className = 'sheet-backdrop animate-fade-in';
    backdrop.id = 'sheetBackdrop';
    backdrop.onclick = closeSheet;

    const sheet = document.createElement('div');
    sheet.className = 'sheet-content';
    sheet.id = 'sheetContent';
    sheet.innerHTML = contentHtml;
    sheet.onclick = (e) => e.stopPropagation();

    document.body.appendChild(backdrop);
    document.body.appendChild(sheet);
    document.body.style.overflow = 'hidden';
}

function closeSheet() {
    const backdrop = document.getElementById('sheetBackdrop');
    const sheet = document.getElementById('sheetContent');
    if (sheet) {
        sheet.classList.add('closing');
        if (backdrop) backdrop.classList.add('animate-fade-out');
        setTimeout(() => {
            backdrop?.remove();
            sheet?.remove();
            document.body.style.overflow = '';
        }, 280);
    }
}

// ── Debounce ──
function debounce(fn, delay = 300) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// ── Loading State ──
function setLoading(el, loading) {
    if (loading) {
        el.disabled = true;
        el.dataset.originalText = el.textContent;
        el.innerHTML = '<svg class="animate-spin inline-block mr-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Loading...';
    } else {
        el.disabled = false;
        el.textContent = el.dataset.originalText || 'Done';
    }
}
