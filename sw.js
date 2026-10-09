// sw.js - ManhwaFlow PWA Service Worker
const CACHE_NAME = 'manhwaflow-v2';
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/manifest.json',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
];

// Install Event - Pre-cache shell assets safely
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const asset of STATIC_ASSETS) {
                try {
                    await cache.add(asset);
                } catch (e) {
                    // Ignore individual cache failure to prevent install rejection
                }
            }
        }).then(() => self.skipWaiting())
    );
});

// Activate Event - Clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event - Network first with Cache fallback
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only handle GET requests
    if (request.method !== 'GET') return;

    // Skip API, analytics, or ad network requests
    if (request.url.includes('/api/') || request.url.includes('monetag') || request.url.includes('uplcm.com')) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                return caches.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    if (request.headers.get('accept') && request.headers.get('accept').includes('text/html')) {
                        return caches.match('/index.php');
                    }
                });
            })
    );
});
