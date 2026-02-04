const CACHE_NAME = 'treasure-hunt-v1';
const API_CACHE = 'treasure-hunt-api-v1';

// Static assets to cache
const STATIC_CACHE_URLS = [
    '/treasure_hunt/pwa/',
    '/treasure_hunt/pwa/index.html',
    '/treasure_hunt/pwa/css/style.css',
    '/treasure_hunt/pwa/js/app.js',
    '/treasure_hunt/pwa/js/db.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];

/* =========================
   INSTALL
========================= */
self.addEventListener('install', (event) => {
    console.log('[SW] Installing');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_CACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

/* =========================
   ACTIVATE
========================= */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME && cache !== API_CACHE) {
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

/* =========================
   FETCH
========================= */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    /* 🚫 NEVER INTERCEPT NON-GET API REQUESTS (login, register, sync) */
    if (
        url.pathname.startsWith('/treasure_hunt/api/') &&
        request.method !== 'GET'
    ) {
        return;
    }

    /* ✅ API GET REQUESTS — network first, cache fallback */
    if (
        url.pathname.startsWith('/treasure_hunt/api/') &&
        request.method === 'GET'
    ) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(API_CACHE).then(cache => {
                            cache.put(request, clone);
                        });
                    }
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    /* ✅ STATIC FILES — cache first */
    event.respondWith(
        caches.match(request).then(cachedResponse => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then(response => {
                if (
                    request.method === 'GET' &&
                    response.status === 200 &&
                    response.type === 'basic'
                ) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(request, clone);
                    });
                }
                return response;
            });
        })
    );
});

/* =========================
   BACKGROUND SYNC
========================= */
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-offline-actions') {
        event.waitUntil(syncOfflineActions());
    }
});

async function syncOfflineActions() {
    console.log('[SW] Syncing offline actions');

    const db = await openDB();
    const actions = await getOfflineActions(db);

    for (const action of actions) {
        try {
            const response = await fetch(
                `${self.location.origin}/treasure_hunt/api/sync/queue`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${action.token}`
                    },
                    body: JSON.stringify(action.data)
                }
            );

            if (response.ok) {
                await removeOfflineAction(db, action.id);
            }
        } catch (err) {
            console.error('[SW] Sync failed:', err);
        }
    }
}

/* =========================
   INDEXED DB HELPERS
========================= */
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('TreasureHuntDB', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function getOfflineActions(db) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('offlineActions', 'readonly');
        const store = tx.objectStore('offlineActions');
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function removeOfflineAction(db, id) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('offlineActions', 'readwrite');
        const store = tx.objectStore('offlineActions');
        const req = store.delete(id);
        req.onsuccess = () => resolve();
        req.onerror = () => reject(req.error);
    });
}
