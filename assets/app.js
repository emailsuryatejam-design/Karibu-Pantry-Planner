/**
 * Karibu Pantry Planner — Vanilla JS Helpers
 */

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
        this.say(`Order submitted. Session ${session} for ${kitchen} sent to store.`, 'high');
    },
    orderFulfilled(session, kitchen) {
        this.say(`Order fulfilled. Session ${session} for ${kitchen} is ready for pickup.`, 'high');
    },
    orderReceived(session) {
        this.say(`Receipt confirmed for session ${session}.`);
    },
    newOrderAlert(chef, kitchen) {
        this.say(`Attention store. New order from ${chef} for ${kitchen}.`, 'high');
    },
    itemsSaved(count, kg) {
        this.say(`${count} items saved. Total order ${kg} kilograms.`);
    },
    dayClosed(date) {
        this.say(`Day closed for ${date}. All sessions finalized.`);
    },
    sessionCreated(session) {
        this.say(`Session ${session} created. Add items to continue.`);
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
