// Martly PWA Service Worker - Enhanced Version
const CACHE_NAME = "martly-v3.0.0";
const RUNTIME_CACHE = "martly-runtime-v3.0.0";

// Core assets to cache immediately
const STATIC_ASSETS = [
  "/online-plaza/",
  "/online-plaza/index.php",
  "/online-plaza/pwa/manifest.json",
  "/online-plaza/pwa/offline.html",
  "https://cdn.tailwindcss.com",
  "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
];

// Install event - cache core assets
self.addEventListener("install", (event) => {
  console.log("[SW] Installing Martly Service Worker v3.0.0");
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log("[SW] Caching core assets");
        return cache.addAll(STATIC_ASSETS).catch((error) => {
          console.warn("[SW] Some assets failed to cache:", error);
        });
      })
      .then(() => {
        console.log("[SW] Installation complete, skipping waiting");
        return self.skipWaiting();
      })
  );
});

// Activate event - clean old caches
self.addEventListener("activate", (event) => {
  console.log("[SW] Activating Martly Service Worker v3.0.0");
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cache) => {
            if (cache !== CACHE_NAME && cache !== RUNTIME_CACHE) {
              console.log("[SW] Deleting old cache:", cache);
              return caches.delete(cache);
            }
          })
        );
      })
      .then(() => {
        console.log("[SW] Claiming clients");
        return self.clients.claim();
      })
  );
});

// Fetch event - Network first, fallback to cache
self.addEventListener("fetch", (event) => {
  const { request } = event;
  
  // Skip non-GET requests
  if (request.method !== "GET") return;
  
  // Skip chrome-extension and other non-http requests
  if (!request.url.startsWith('http')) return;

  event.respondWith(
    fetch(request)
      .then((response) => {
        // Check if valid response
        if (!response || response.status !== 200 || response.type === 'error') {
          return response;
        }

        // Clone the response
        const responseToCache = response.clone();

        // Cache successful responses in runtime cache
        caches.open(RUNTIME_CACHE).then((cache) => {
          cache.put(request, responseToCache);
        });

        return response;
      })
      .catch(() => {
        // Network failed, try cache
        return caches.match(request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }

          // If requesting a page, return offline page
          if (request.headers.get('accept').includes('text/html')) {
            return caches.match("/online-plaza/pwa/offline.html");
          }

          // For other requests, return a basic response
          return new Response('Offline - Resource not available', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({
              'Content-Type': 'text/plain'
            })
          });
        });
      })
  );
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag);
  if (event.tag === 'sync-data') {
    event.waitUntil(syncData());
  }
});

// Push notification handler
self.addEventListener('push', (event) => {
  console.log('[SW] Push notification received');
  const options = {
    body: event.data ? event.data.text() : 'New notification from Martly',
    icon: '/online-plaza/pwa/icons/icon-192x192.png',
    badge: '/online-plaza/pwa/icons/icon-72x72.png',
    vibrate: [200, 100, 200],
    tag: 'martly-notification',
    requireInteraction: false
  };

  event.waitUntil(
    self.registration.showNotification('Martly', options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked');
  event.notification.close();

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // If a window is already open, focus it
        for (let client of clientList) {
          if (client.url.includes('/online-plaza/') && 'focus' in client) {
            return client.focus();
          }
        }
        // Otherwise open a new window
        if (clients.openWindow) {
          return clients.openWindow('/online-plaza/');
        }
      })
  );
});

// Helper function for data sync
async function syncData() {
  console.log('[SW] Syncing offline data...');
  // Implement your sync logic here
  return Promise.resolve();
}

// Message handler for communication with pages
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);
  
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
  
  if (event.data.action === 'clearCache') {
    event.waitUntil(
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => caches.delete(cacheName))
        );
      })
    );
  }
});