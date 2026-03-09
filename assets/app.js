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
    const config = {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', ...options.headers },
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
async function pushSubscribe() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        showToast('Push notifications not supported', 'warning');
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
        showToast('Failed to subscribe: ' + err.message, 'error');
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

// ── Voice Announcements (Text-to-Speech) ──
const voice = {
    enabled: localStorage.getItem('karibu_voice') !== 'off',
    rate: 1.0,
    pitch: 1.0,
    _queue: [],
    _speaking: false,

    /** Speak a message aloud */
    say(text, priority = 'normal') {
        if (!this.enabled || !('speechSynthesis' in window)) return;

        // High priority interrupts current speech
        if (priority === 'high') {
            speechSynthesis.cancel();
            this._queue = [];
        }

        this._queue.push(text);
        this._processQueue();
    },

    _processQueue() {
        if (this._speaking || this._queue.length === 0) return;
        this._speaking = true;

        const text = this._queue.shift();
        const utter = new SpeechSynthesisUtterance(text);
        utter.rate = this.rate;
        utter.pitch = this.pitch;
        utter.volume = 1;

        // Try to pick an English voice
        const voices = speechSynthesis.getVoices();
        const english = voices.find(v => v.lang.startsWith('en') && v.default)
                     || voices.find(v => v.lang.startsWith('en'))
                     || voices[0];
        if (english) utter.voice = english;

        utter.onend = () => {
            this._speaking = false;
            this._processQueue();
        };
        utter.onerror = () => {
            this._speaking = false;
            this._processQueue();
        };

        speechSynthesis.speak(utter);
    },

    /** Toggle voice on/off */
    toggle(on) {
        this.enabled = on;
        localStorage.setItem('karibu_voice', on ? 'on' : 'off');
        if (!on) speechSynthesis.cancel();
    },

    /** Convenience methods for common events */
    orderSubmitted(session, kitchen) {
        this.say(`Order submitted. Requisition ${session} for ${kitchen} sent to store.`, 'high');
    },
    orderFulfilled(session, kitchen) {
        this.say(`Order fulfilled. Requisition ${session} for ${kitchen} is ready for pickup.`, 'high');
    },
    orderReceived(session) {
        this.say(`Receipt confirmed for requisition ${session}.`);
    },
    newOrderAlert(chef, kitchen) {
        this.say(`Attention store. New order from ${chef} for ${kitchen}.`, 'high');
    },
    itemsSaved(count, kg) {
        this.say(`${count} items saved. Total order ${kg} kilograms.`);
    },
    dayClosed(date) {
        this.say(`Day closed for ${date}. All requisitions finalized.`);
    },
    requisitionCreated(session) {
        this.say(`Requisition ${session} created. Add items to continue.`);
    },
    error(msg) {
        this.say(`Error. ${msg}`, 'high');
    },
    welcome(name, role) {
        this.say(`Welcome ${name}. You are logged in as ${role}.`);
    }
};

// Preload voices (needed for some browsers)
if ('speechSynthesis' in window) {
    speechSynthesis.getVoices();
    speechSynthesis.onvoiceschanged = () => speechSynthesis.getVoices();
}

// ── Print Order ──
async function printOrder(reqId, kitchenNameOverride) {
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

        // Always show full flow: Requested → Sent → Received → Diff
        // Build table rows
        let tableRows = '';
        let totalOrdered = 0, totalFulfilled = 0, totalReceived = 0;
        lines.forEach((l, i) => {
            const orderQty = parseFloat(l.order_qty) || 0;
            const reqKg = parseFloat(l.required_kg) || 0;
            const fulfilledQty = parseFloat(l.fulfilled_qty) || 0;
            const receivedQty = parseFloat(l.received_qty) || 0;
            const diff = receivedQty - fulfilledQty;
            const hasDiff = Math.abs(diff) > 0.01;
            const diffStyle = diff < 0 ? 'color:#dc2626;font-weight:bold' : (diff > 0 ? 'color:#16a34a;font-weight:bold' : 'color:#6b7280');
            const rowBg = hasDiff ? 'background:#fef2f2;' : '';

            totalOrdered += orderQty;
            totalFulfilled += fulfilledQty;
            totalReceived += receivedQty;

            tableRows += `<tr style="border-bottom:1px solid #e5e7eb;${rowBg}">
                <td style="padding:6px 8px;text-align:center;color:#6b7280">${i + 1}</td>
                <td style="padding:6px 8px;font-weight:500">${escHtml(l.item_name)}</td>
                <td style="padding:6px 8px;text-align:center;color:#6b7280;font-size:11px">${escHtml(l.uom || 'kg')}</td>
                <td style="padding:6px 8px;text-align:center">${orderQty}</td>
                <td style="padding:6px 8px;text-align:center;font-weight:600;color:#2563eb">${fulfilledQty || '—'}</td>
                <td style="padding:6px 8px;text-align:center;font-weight:600;color:#16a34a">${receivedQty || '—'}</td>
                <td style="padding:6px 8px;text-align:center;${diffStyle}">${hasDiff ? (diff > 0 ? '+' : '') + diff : '—'}</td>
            </tr>`;
        });

        // Total row
        const totalDiff = totalReceived - totalFulfilled;
        tableRows += `<tr style="border-top:2px solid #374151;font-weight:700;background:#f9fafb">
            <td style="padding:8px" colspan="3">TOTAL</td>
            <td style="padding:8px;text-align:center">${totalOrdered.toFixed(1)}</td>
            <td style="padding:8px;text-align:center;color:#2563eb">${totalFulfilled.toFixed(1)}</td>
            <td style="padding:8px;text-align:center;color:#16a34a">${totalReceived.toFixed(1)}</td>
            <td style="padding:8px;text-align:center;${Math.abs(totalDiff) > 0.01 ? 'color:#dc2626;font-weight:bold' : ''}">${Math.abs(totalDiff) > 0.01 ? totalDiff.toFixed(1) : '—'}</td>
        </tr>`;

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
                <th style="width:36px;text-align:center">#</th>
                <th>Item</th>
                <th class="center" style="width:50px">UOM</th>
                <th class="center" style="width:70px">Requested</th>
                <th class="center" style="width:70px">Sent</th>
                <th class="center" style="width:70px">Received</th>
                <th class="center" style="width:60px">Diff</th>
            </tr>
        </thead>
        <tbody>
            ${tableRows}
        </tbody>
    </table>

    ${dishesHtml}
    ${disputeHtml}

    <!-- Signature area -->
    <div style="margin-top:32px;display:grid;grid-template-columns:1fr 1fr;gap:40px">
        <div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:32px"></div>
            <div style="font-size:11px;color:#6b7280">Chef Signature / Date</div>
        </div>
        <div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:32px"></div>
            <div style="font-size:11px;color:#6b7280">Store Signature / Date</div>
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
