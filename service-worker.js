/**
 * Karibu Pantry Planner — Service Worker
 * Cache-first for static assets, network-first for API/PHP, offline fallback
 */

const CACHE_NAME = 'karibu-v2';
const STATIC_ASSETS = [
    '/app.php',
    '/assets/app.css',
    '/assets/app.js',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
    '/offline.html',
    '/manifest.json'
];

// Install — cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Activate — clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch — network-first for PHP/API, cache-first for static
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // API and PHP pages — network first, fallback to offline
    if (url.pathname.endsWith('.php') || url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Cache successful page responses
                    if (response.ok && url.pathname.endsWith('.php') && !url.pathname.includes('/api/')) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(event.request).then(cached => cached || caches.match('/offline.html'))
                )
        );
        return;
    }

    // Static assets — cache first
    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached) return cached;
            return fetch(event.request).then(response => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            });
        }).catch(() => caches.match('/offline.html'))
    );
});

// Push notification handler
self.addEventListener('push', event => {
    let data = { title: 'Karibu Pantry', body: 'New notification' };
    try {
        data = event.data.json();
    } catch (e) {
        data.body = event.data ? event.data.text() : 'New notification';
    }

    event.waitUntil(
        Promise.all([
            // Show system notification
            self.registration.showNotification(data.title || 'Karibu Pantry', {
                body: data.body || '',
                icon: '/assets/icons/icon-192.png',
                badge: '/assets/icons/icon-192.png',
                tag: data.tag || 'karibu-notification',
                data: { url: data.url || '/app.php' }
            }),
            // Forward to open windows for voice announcement
            clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
                windowClients.forEach(client => {
                    client.postMessage({ type: 'push-notification', payload: data });
                });
            })
        ])
    );
});

// Notification click — open the relevant page
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = event.notification.data?.url || '/app.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            for (const client of windowClients) {
                if (client.url.includes('app.php') && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
