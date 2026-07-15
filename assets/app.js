/**
 * Karibu Pantry Planner — Vanilla JS Helpers
 */

// ── XSS-safe HTML escaping ──
function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ── Requisition label: "Breakfast" or "Breakfast (2)" for supplementary orders ──
function reqLabel(r) {
    const meal = escHtml((r.meals || '').replace(/^./, c => c.toUpperCase()));
    const supp = parseInt(r.supplement_number) || 0;
    return supp > 0 ? `${meal} (${supp + 1})` : meal;
}

// ── API Helper ──
async function api(endpoint, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const config = {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, ...options.headers },
    };
    if (options.body && method !== 'GET') {
        config.body = typeof options.body === 'string' ? options.body : JSON.stringify(options.body);
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

/**
 * Cached GET — sessionStorage with TTL (ms). Only for GET requests.
 */
async function cachedApi(endpoint, ttlMs = 300000) {
    const key = 'api_' + endpoint;
    try {
        const cached = sessionStorage.getItem(key);
        if (cached) {
            const { data, ts } = JSON.parse(cached);
            if (Date.now() - ts < ttlMs) return data;
        }
    } catch {}
    const data = await api(endpoint);
    try { sessionStorage.setItem(key, JSON.stringify({ data, ts: Date.now() })); } catch {}
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

// ── Custom Confirm Dialog (replaces native confirm) ──
function customConfirm(title, message) {
    return new Promise(resolve => {
        const backdrop = document.createElement('div');
        backdrop.className = 'fixed inset-0 bg-black/50 z-[300] flex items-center justify-center p-4 animate-fade-in';
        backdrop.innerHTML = `
            <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-2">${title}</h3>
                <p class="text-sm text-gray-600 mb-6 whitespace-pre-line">${message}</p>
                <div class="flex gap-3">
                    <button id="cfmCancel" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-medium text-sm">Cancel</button>
                    <button id="cfmOk" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-white font-medium text-sm">Confirm</button>
                </div>
            </div>`;
        document.body.appendChild(backdrop);
        const cleanup = (val) => { backdrop.remove(); resolve(val); };
        backdrop.querySelector('#cfmCancel').onclick = () => cleanup(false);
        backdrop.querySelector('#cfmOk').onclick = () => cleanup(true);
        backdrop.addEventListener('click', e => { if (e.target === backdrop) cleanup(false); });
    });
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

// ── Push Notification Helpers ──
function pushIsIOS() { return /iPad|iPhone|iPod/.test(navigator.userAgent); }
function pushIsStandalone() { return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true; }

async function pushSubscribe() {
    // Check basic support
    if (!('serviceWorker' in navigator)) {
        showToast('Service Workers not supported on this browser', 'warning');
        return false;
    }

    const isiOS = pushIsIOS();
    const standalone = pushIsStandalone();

    // iOS requires the app to be installed as PWA (Add to Home Screen) before push works
    if (isiOS && !standalone) {
        showToast('Install app first: tap Share ➜ Add to Home Screen, then enable notifications', 'warning');
        return false;
    }

    if (!('PushManager' in window)) {
        if (isiOS) {
            showToast('Push notifications require iOS 16.4 or later', 'warning');
        } else {
            showToast('Push notifications not supported on this browser', 'warning');
        }
        return false;
    }

    if (!('Notification' in window)) {
        showToast('Notifications not supported on this browser', 'warning');
        return false;
    }

    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            showToast('Notification permission denied', 'warning');
            return false;
        }

        const reg = await navigator.serviceWorker.ready;
        const keyRes = await api('api/push.php?action=vapid_key');
        const vapidKey = keyRes.key;

        if (!vapidKey) {
            showToast('Push configuration error — contact admin', 'error');
            return false;
        }

        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey)
        });

        const subJson = sub.toJSON();
        await api('api/push.php?action=subscribe', {
            method: 'POST',
            body: {
                endpoint: subJson.endpoint,
                p256dh: subJson.keys.p256dh,
                auth_key: subJson.keys.auth
            }
        });

        showToast('Notifications enabled!', 'success');
        return true;
    } catch (err) {
        console.error('Push subscribe error:', err);
        if (isiOS && !standalone) {
            showToast('Install app to Home Screen first for notifications', 'warning');
        } else if (err.name === 'NotAllowedError') {
            showToast('Notification permission was denied. Check browser settings.', 'warning');
        } else if (err.name === 'AbortError') {
            showToast('Subscription cancelled. Please try again.', 'warning');
        } else {
            showToast('Failed to enable notifications: ' + (err.message || 'Unknown error'), 'error');
        }
        return false;
    }
}

async function pushUnsubscribe() {
    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            await api('api/push.php?action=unsubscribe', {
                method: 'POST',
                body: { endpoint: sub.endpoint }
            });
            await sub.unsubscribe();
        }
        showToast('Notifications disabled', 'info');
        return true;
    } catch (err) {
        showToast('Failed to unsubscribe: ' + err.message, 'error');
        return false;
    }
}

async function isPushSubscribed() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        return !!sub;
    } catch { return false; }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// ── Print Order ──
// Animated "save paper — print the whole day on one sheet" nudge.
// Resolves 'whole-day' or 'single'. Injected so it works on any page.
function dayPrintSuggest(mealsCount, dateLabel) {
    return new Promise(resolve => {
        const ov = document.createElement('div');
        ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(17,24,39,.5);display:flex;align-items:center;justify-content:center;padding:16px;animation:kpFade .2s ease';
        ov.innerHTML = `<style>
            @keyframes kpFade{from{opacity:0}to{opacity:1}}
            @keyframes kpPop{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:scale(1) translateY(0)}}
            @keyframes kpSway{0%,100%{transform:rotate(-7deg)}50%{transform:rotate(7deg)}}
          </style>
          <div style="background:#fff;border-radius:20px;max-width:340px;width:100%;padding:26px 22px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.25);animation:kpPop .28s cubic-bezier(.2,.8,.3,1.25)">
            <div style="width:58px;height:58px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;animation:kpSway 1.8s ease-in-out infinite">
              <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/></svg>
            </div>
            <h3 style="margin:0 0 6px;font-size:17px;font-weight:700;color:#111827;font-family:-apple-system,Segoe UI,sans-serif">Save paper?</h3>
            <p style="margin:0 0 18px;font-size:13px;line-height:1.5;color:#6b7280;font-family:-apple-system,Segoe UI,sans-serif">You have <b style="color:#111827">${mealsCount} orders</b> for ${dateLabel}. Print them together on one day sheet instead of separate pages.</p>
            <button id="kpAll" style="width:100%;background:#16a34a;color:#fff;border:none;padding:12px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;margin-bottom:8px">Print whole day (${mealsCount} orders)</button>
            <button id="kpOne" style="width:100%;background:#f3f4f6;color:#374151;border:none;padding:11px;border-radius:12px;font-size:13px;font-weight:600;cursor:pointer">Just this one</button>
          </div>`;
        document.body.appendChild(ov);
        const done = (v) => { ov.style.animation = 'kpFade .15s ease reverse'; setTimeout(() => ov.remove(), 140); resolve(v); };
        ov.querySelector('#kpAll').onclick = () => done('whole-day');
        ov.querySelector('#kpOne').onclick = () => done('single');
        ov.onclick = (e) => { if (e.target === ov) done('single'); };
    });
}

async function printOrder(reqId, kitchenNameOverride, skipDaySuggest) {
    try {
        const data = await api(`api/requisitions.php?action=get&id=${reqId}`);
        const req = data.requisition;
        const lines = data.lines || [];

        // Also load dishes for this requisition
        let dishes = [];
        try {
            const dData = await api(`api/requisitions.php?action=get_dishes_with_ingredients&requisition_id=${reqId}`);
            dishes = dData.dishes || [];
        } catch {}

        const mealLabel = reqLabel(req);
        const chefName = req.chef_name || 'Chef';
        const date = formatDate(req.req_date);
        const guestCount = req.guest_count || 20;
        const status = (req.status || 'draft').toUpperCase();
        const kitchenName = kitchenNameOverride || '';

        // Save-paper nudge: if there are other orders the same day, offer the one-sheet day print.
        // Skipped when the caller explicitly asked for a single meal (skipDaySuggest).
        if (!skipDaySuggest) {
            try {
                const dp = await api(`api/requisitions.php?action=day_print&date=${req.req_date}&kitchen_id=${req.kitchen_id}`);
                const mealsCount = (dp.requisitions || []).length;
                if (mealsCount >= 2) {
                    const choice = await dayPrintSuggest(mealsCount, date);
                    if (choice === 'whole-day') { printWholeDay(req.req_date, req.kitchen_id, kitchenNameOverride); return; }
                }
            } catch {}
        }

        // Always show full flow: Requested → Sent → Received → Diff
        // Menu items in the main table; staples printed in their own section below.
        let totalUnusedKg = 0;
        lines.forEach(l => totalUnusedKg += parseFloat(l.unused_qty) || 0);
        const menuLines = lines.filter(l => !parseInt(l.is_staple));
        const stapleLines = lines.filter(l => parseInt(l.is_staple));

        const buildRow = (l, i) => {
            const orderQty = parseFloat(l.order_qty) || 0;
            const fulfilledQty = parseFloat(l.fulfilled_qty) || 0;
            const receivedQty = parseFloat(l.received_qty) || 0;
            const unusedQty = parseFloat(l.unused_qty) || 0;
            const diff = receivedQty - fulfilledQty;
            const hasDiff = Math.abs(diff) > 0.01;
            const diffStyle = diff < 0 ? 'color:#dc2626;font-weight:bold' : (diff > 0 ? 'color:#16a34a;font-weight:bold' : 'color:#6b7280');
            const rowBg = hasDiff ? 'background:#fef2f2;' : '';
            const itemCode = l.item_code || l.code || '';
            return `<tr style="border-bottom:1px solid #e5e7eb;${rowBg}">
                <td style="padding:6px 8px;text-align:center;color:#6b7280">${i + 1}</td>
                <td style="padding:6px 8px;font-size:11px;color:#9ca3af;font-family:monospace">${escHtml(itemCode) || '—'}</td>
                <td style="padding:6px 8px;font-weight:500">${escHtml(l.item_name)}</td>
                <td style="padding:6px 8px;text-align:center;color:#6b7280;font-size:11px">${escHtml(l.uom || 'kg')}</td>
                <td style="padding:6px 8px;text-align:center">${orderQty}</td>
                <td style="padding:6px 8px;text-align:center;font-weight:600;color:#2563eb">${fulfilledQty || '—'}</td>
                <td style="padding:6px 8px;text-align:center;font-weight:600;color:#16a34a">${receivedQty || '—'}</td>
                <td style="padding:6px 8px;text-align:center;${unusedQty > 0 ? 'color:#d97706;font-weight:bold' : 'color:#6b7280'}">${unusedQty > 0 ? unusedQty : '—'}</td>
                <td style="padding:6px 8px;text-align:center;${diffStyle}">${hasDiff ? (diff > 0 ? '+' : '') + diff : '—'}</td>
            </tr>`;
        };
        const tableRows = menuLines.map((l, i) => buildRow(l, i)).join('');

        const tableHead = `<thead><tr>
                <th style="width:30px;text-align:center">#</th>
                <th style="width:70px">Item No</th>
                <th>Item</th>
                <th class="center" style="width:45px">UOM</th>
                <th class="center" style="width:60px">Requested</th>
                <th class="center" style="width:60px">Sent</th>
                <th class="center" style="width:60px">Received</th>
                <th class="center" style="width:50px">Unused</th>
                <th class="center" style="width:50px">Diff</th>
            </tr></thead>`;
        const stapleSectionHtml = stapleLines.length ? `
            <div style="margin-top:18px">
                <div style="font-size:12px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.3px;border-bottom:2px solid #7c3aed;padding-bottom:5px;margin-bottom:6px">Staple items (${stapleLines.length})</div>
                <table>${tableHead}<tbody>${stapleLines.map((l, i) => buildRow(l, i)).join('')}</tbody></table>
            </div>` : '';

        // Dishes list with per-dish portions
        let dishesHtml = '';
        if (dishes.length > 0) {
            const dishItems = dishes.map(d => {
                const portions = d.guest_count || guestCount;
                return `<span style="display:inline-block;margin:2px 4px 2px 0;padding:3px 8px;background:#fef3c7;border-radius:6px;font-size:12px">
                    ${escHtml(d.recipe_name)} <strong style="color:#92400e">(${portions} pax)</strong>
                </span>`;
            }).join('');
            dishesHtml = `<div style="margin-top:16px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px">
                <div style="font-size:11px;font-weight:600;color:#92400e;margin-bottom:6px">DISHES (${dishes.length})</div>
                <div>${dishItems}</div>
            </div>`;
        }

        // Dispute flag
        let disputeHtml = '';
        if (req.has_dispute == 1) {
            disputeHtml = `<div style="margin-top:12px;padding:10px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px">
                <span style="font-size:12px;font-weight:600;color:#dc2626">⚠ DISPUTE: Quantity differences detected between issued and received items</span>
            </div>`;
        }

        const html = `<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>${mealLabel} — ${date}</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; padding: 24px; color: #1f2937; font-size: 13px; }
    @media print {
        body { padding: 12px; }
        .no-print { display: none !important; }
        @page { margin: 15mm; size: A4; }
    }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f3f4f6; text-align: left; padding: 8px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #374151; border-bottom: 2px solid #d1d5db; }
    th.center { text-align: center; }
</style>
</head><body>
    <!-- Print button -->
    <div class="no-print" style="margin-bottom:16px;text-align:right">
        <button onclick="window.print()" style="background:#ea580c;color:white;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">
            🖨 Print
        </button>
        <button onclick="window.close()" style="background:#e5e7eb;color:#374151;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-left:8px">
            ✕ Close
        </button>
    </div>

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #ea580c;padding-bottom:12px;margin-bottom:16px">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#ea580c">Karibu Pantry Planner</h1>
            ${kitchenName ? `<div style="font-size:12px;color:#6b7280;margin-top:2px">${escHtml(kitchenName)}</div>` : ''}
        </div>
        <div style="text-align:right">
            <div style="font-size:16px;font-weight:700;color:#1f2937">REQUISITION ORDER</div>
            <div style="font-size:11px;color:#6b7280">#${req.id}</div>
        </div>
    </div>

    <!-- Info Grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:16px">
        <div style="background:#f9fafb;padding:10px 12px;border-radius:8px;border:1px solid #e5e7eb">
            <div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px">Date</div>
            <div style="font-size:14px;font-weight:600;color:#1f2937;margin-top:2px">${date}</div>
        </div>
        <div style="background:#f9fafb;padding:10px 12px;border-radius:8px;border:1px solid #e5e7eb">
            <div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px">Meal Type</div>
            <div style="font-size:14px;font-weight:600;color:#1f2937;margin-top:2px">${mealLabel}</div>
        </div>
        <div style="background:#f9fafb;padding:10px 12px;border-radius:8px;border:1px solid #e5e7eb">
            <div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px">Chef</div>
            <div style="font-size:14px;font-weight:600;color:#1f2937;margin-top:2px">${escHtml(chefName)}</div>
        </div>
        <div style="background:#f9fafb;padding:10px 12px;border-radius:8px;border:1px solid #e5e7eb">
            <div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px">Guests</div>
            <div style="font-size:14px;font-weight:600;color:#1f2937;margin-top:2px">${guestCount}</div>
        </div>
    </div>

    <!-- Status badge -->
    <div style="margin-bottom:12px">
        <span style="display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:600;background:${
            req.status === 'draft' ? '#f3f4f6;color:#374151' :
            req.status === 'submitted' ? '#dbeafe;color:#1d4ed8' :
            req.status === 'fulfilled' ? '#dcfce7;color:#15803d' :
            req.status === 'received' ? '#dcfce7;color:#15803d' :
            req.status === 'closed' ? '#e5e7eb;color:#4b5563' :
            '#fef3c7;color:#92400e'
        }">${status}</span>
        <span style="font-size:12px;color:#6b7280;margin-left:8px">${lines.length} items</span>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width:30px;text-align:center">#</th>
                <th style="width:70px">Item No</th>
                <th>Item</th>
                <th class="center" style="width:45px">UOM</th>
                <th class="center" style="width:60px">Requested</th>
                <th class="center" style="width:60px">Sent</th>
                <th class="center" style="width:60px">Received</th>
                <th class="center" style="width:50px">Unused</th>
                <th class="center" style="width:50px">Diff</th>
            </tr>
        </thead>
        <tbody>
            ${tableRows}
        </tbody>
    </table>
    ${stapleSectionHtml}

    ${dishesHtml}
    ${disputeHtml}
    ${totalUnusedKg > 0 ? `<div style="margin-top:12px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px">
        <span style="font-size:12px;font-weight:600;color:#d97706">Unused: ${totalUnusedKg.toFixed(1)} kg returned to inventory</span>
    </div>` : ''}

    <!-- Signature area -->
    <div style="margin-top:32px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">
        <div>
            <div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Prepared by</div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div>
            <div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div>
        </div>
        <div>
            <div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Issued by</div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div>
            <div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div>
        </div>
        <div>
            <div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Received by (Manager)</div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div>
            <div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div>
        </div>
    </div>

    <div style="margin-top:24px;text-align:center;font-size:10px;color:#9ca3af">
        Printed on ${new Date().toLocaleString('en-GB')} — Karibu Pantry Planner
    </div>
</body></html>`;

        const printWin = window.open('', '_blank', 'width=800,height=900');
        if (printWin) {
            printWin.document.write(html);
            printWin.document.close();
            // Auto-print after a short delay
            setTimeout(() => printWin.print(), 400);
        } else {
            showToast('Please allow popups to print', 'warning');
        }
    } catch (e) {
        showToast('Failed to load order for printing: ' + (e.message || ''), 'error');
    }
}

// ── Print the WHOLE day: every requisition in one document, separated by meal headings ──
async function printWholeDay(date, kitchenId, kitchenNameOverride) {
    try {
        const kid = kitchenId || (typeof DB_KITCHEN_ID !== 'undefined' ? DB_KITCHEN_ID
                  : typeof ORD_KITCHEN_ID !== 'undefined' ? ORD_KITCHEN_ID
                  : typeof DC_KID !== 'undefined' ? DC_KID : '');
        const data = await api(`api/requisitions.php?action=day_print&date=${date}&kitchen_id=${kid}`);
        const reqs = data.requisitions || [];
        const kitchenName = kitchenNameOverride || data.kitchen_name || '';
        const dateLabel = formatDate(date);

        if (reqs.length === 0) { showToast('No orders to print for this day', 'warning'); return; }

        const mealName = (r) => (typeof reqLabel === 'function') ? reqLabel(r)
            : ((r.meals || 'Order').charAt(0).toUpperCase() + (r.meals || 'Order').slice(1).replace(/_/g, ' '));

        const statusColor = (s) => s === 'closed' ? '#e5e7eb;color:#4b5563'
            : s === 'fulfilled' || s === 'received' ? '#dcfce7;color:#15803d'
            : s === 'submitted' ? '#dbeafe;color:#1d4ed8' : '#fef3c7;color:#92400e';

        let sections = '';
        const stapleAgg = {}; // item|uom -> { item_name, uom, ordered, sent } — all staples across the day
        reqs.forEach((r, idx) => {
            const allLines = r.lines || [];
            const menuLines = allLines.filter(l => !parseInt(l.is_staple));
            const dishes = r.dishes || [];
            const guests = r.guest_count || 20;

            // collect staples into the day-wide consolidated list (printed once at the end)
            allLines.filter(l => parseInt(l.is_staple)).forEach(l => {
                const key = (l.item_name || '') + '|' + (l.uom || 'kg');
                if (!stapleAgg[key]) stapleAgg[key] = { item_name: l.item_name, uom: l.uom || 'kg', ordered: 0, sent: 0 };
                stapleAgg[key].ordered += parseFloat(l.order_qty) || 0;
                stapleAgg[key].sent += parseFloat(l.fulfilled_qty) || 0;
            });

            if (menuLines.length === 0) return; // staples-only meal — its items show in the day staples list

            let rows = '';
            menuLines.forEach((l, i) => {
                const reqKg = parseFloat(l.required_kg) || 0;
                const stockQ = parseFloat(l.stock_qty) || 0;
                const orderQ = parseFloat(l.order_qty) || 0;
                const sentQ = parseFloat(l.fulfilled_qty) || 0;
                const recvQ = parseFloat(l.received_qty) || 0;
                const unusedQ = parseFloat(l.unused_qty) || 0;
                rows += `<tr style="border-bottom:1px solid #e5e7eb">
                    <td style="padding:5px 8px;text-align:center;color:#6b7280">${i + 1}</td>
                    <td style="padding:5px 8px;font-weight:500">${escHtml(l.item_name)}</td>
                    <td style="padding:5px 8px;text-align:center;color:#6b7280;font-size:11px">${escHtml(l.uom || 'kg')}</td>
                    <td style="padding:5px 8px;text-align:center">${reqKg > 0 ? reqKg : '—'}</td>
                    <td style="padding:5px 8px;text-align:center;color:#b45309">${stockQ > 0 ? stockQ : '—'}</td>
                    <td style="padding:5px 8px;text-align:center;font-weight:600">${orderQ > 0 ? orderQ : '—'}</td>
                    <td style="padding:5px 8px;text-align:center;color:#2563eb">${sentQ > 0 ? sentQ : '—'}</td>
                    <td style="padding:5px 8px;text-align:center;color:#16a34a">${recvQ > 0 ? recvQ : '—'}</td>
                    <td style="padding:5px 8px;text-align:center;color:${unusedQ > 0 ? '#d97706' : '#6b7280'}">${unusedQ > 0 ? unusedQ : '—'}</td>
                </tr>`;
            });

            const dishesHtml = dishes.length > 0
                ? `<div style="margin:6px 0 10px">${dishes.map(d => `<span style="display:inline-block;margin:2px 4px 2px 0;padding:2px 8px;background:#fef3c7;border-radius:6px;font-size:11px">${escHtml(d.recipe_name)} <strong style="color:#92400e">(${d.guest_count || guests} pax)</strong></span>`).join('')}</div>`
                : '';

            sections += `<div class="day-section" style="margin-bottom:18px">
                <div style="display:flex;align-items:center;gap:10px;border-bottom:2px solid #ea580c;padding-bottom:6px;margin-bottom:8px;page-break-after:avoid">
                    <h2 style="font-size:16px;font-weight:700;color:#1f2937;margin:0">${escHtml(mealName(r))}</h2>
                    <span style="padding:2px 10px;border-radius:999px;font-size:10px;font-weight:600;background:${statusColor(r.status)}">${(r.status || '').toUpperCase()}</span>
                    <span style="font-size:11px;color:#6b7280">${guests} pax &bull; ${escHtml(r.chef_name || 'Chef')} &bull; ${menuLines.length} items</span>
                </div>
                ${dishesHtml}
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead><tr style="background:#f3f4f6">
                        <th style="padding:6px 8px;text-align:center;width:28px">#</th>
                        <th style="padding:6px 8px;text-align:left">Item</th>
                        <th style="padding:6px 8px;text-align:center">UOM</th>
                        <th style="padding:6px 8px;text-align:center">Req</th>
                        <th style="padding:6px 8px;text-align:center">Stock</th>
                        <th style="padding:6px 8px;text-align:center">Order</th>
                        <th style="padding:6px 8px;text-align:center">Sent</th>
                        <th style="padding:6px 8px;text-align:center">Recv</th>
                        <th style="padding:6px 8px;text-align:center">Unused</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        });

        // Consolidated "Staples for the day" — all staple items across every meal, combined
        const staples = Object.values(stapleAgg).sort((a, b) => a.item_name.localeCompare(b.item_name));
        if (staples.length) {
            const round2 = v => Math.round(v * 100) / 100;
            const srows = staples.map((s, i) => `<tr style="border-bottom:1px solid #e5e7eb">
                <td style="padding:5px 8px;text-align:center;color:#6b7280">${i + 1}</td>
                <td style="padding:5px 8px;font-weight:500">${escHtml(s.item_name)}</td>
                <td style="padding:5px 8px;text-align:center;color:#6b7280;font-size:11px">${escHtml(s.uom)}</td>
                <td style="padding:5px 8px;text-align:center;font-weight:600">${s.ordered > 0 ? round2(s.ordered) : '—'}</td>
                <td style="padding:5px 8px;text-align:center;color:#16a34a">${s.sent > 0 ? round2(s.sent) : '—'}</td>
            </tr>`).join('');
            sections += `<div class="day-section" style="margin-bottom:18px">
                <div style="border-bottom:2px solid #7c3aed;padding-bottom:6px;margin-bottom:8px;page-break-after:avoid">
                    <h2 style="font-size:16px;font-weight:700;color:#1f2937;margin:0">Staples for the day <span style="font-size:11px;color:#6b7280;font-weight:400">— ${staples.length} item${staples.length > 1 ? 's' : ''}, all meals combined</span></h2>
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead><tr style="background:#f3f4ff">
                        <th style="padding:6px 8px;text-align:center;width:28px">#</th>
                        <th style="padding:6px 8px;text-align:left">Staple item</th>
                        <th style="padding:6px 8px;text-align:center">UOM</th>
                        <th style="padding:6px 8px;text-align:center">Order</th>
                        <th style="padding:6px 8px;text-align:center">Sent</th>
                    </tr></thead>
                    <tbody>${srows}</tbody>
                </table>
            </div>`;
        }

        const html = `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Day Requisitions — ${dateLabel}</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Segoe UI',Arial,sans-serif; padding:24px; color:#1f2937; font-size:13px; }
    th { font-size:10px; text-transform:uppercase; letter-spacing:.3px; color:#374151; border-bottom:2px solid #d1d5db; }
    @media print { body { padding:12px; } .no-print { display:none !important; } @page { margin:14mm; size:A4; } .day-section { page-break-inside:auto; } }
</style></head><body>
    <div class="no-print" style="margin-bottom:16px;text-align:right">
        <button onclick="window.print()" style="background:#ea580c;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer">🖨 Print</button>
        <button onclick="window.close()" style="background:#e5e7eb;color:#374151;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-left:8px">✕ Close</button>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #ea580c;padding-bottom:12px;margin-bottom:18px">
        <div><h1 style="font-size:20px;font-weight:700;color:#ea580c">Karibu Pantry Planner</h1>
        ${kitchenName ? `<div style="font-size:12px;color:#6b7280;margin-top:2px">${escHtml(kitchenName)}</div>` : ''}</div>
        <div style="text-align:right"><div style="font-size:16px;font-weight:700">DAY REQUISITIONS</div>
        <div style="font-size:12px;color:#6b7280">${dateLabel} &bull; ${reqs.length} meal${reqs.length > 1 ? 's' : ''}</div></div>
    </div>
    ${sections}
    <div style="margin-top:28px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;page-break-inside:avoid">
        <div><div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Prepared by (Chef)</div><div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div><div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div></div>
        <div><div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Issued by (Store)</div><div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div><div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div></div>
        <div><div style="font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Approved by (Manager)</div><div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div><div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div></div>
    </div>
    <div style="margin-top:24px;text-align:center;font-size:10px;color:#9ca3af">Printed ${new Date().toLocaleString('en-GB')} — Karibu Pantry Planner</div>
</body></html>`;

        const win = window.open('', '_blank', 'width=820,height=920');
        if (win) { win.document.write(html); win.document.close(); setTimeout(() => win.print(), 400); }
        else showToast('Please allow popups to print', 'warning');
    } catch (e) {
        showToast('Failed to build day print: ' + (e.message || ''), 'error');
    }
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
