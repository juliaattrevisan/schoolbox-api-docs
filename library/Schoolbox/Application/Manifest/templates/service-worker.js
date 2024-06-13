"use strict";

const CACHE_NAME = "static-cache-v1";

// Add site version to ensure the cache is invalidated when we update the site
const OFFLINE_CACHE_PAGE = "/manifest/offline?v={{ config.site_version }}";
const OFFLINE_CACHE_IMAGE = "{{ CDN_PREFIX }}/images/core/fail-whale.png?v={{ config.site_version }}";

const CACHE_FILES = [
    OFFLINE_CACHE_IMAGE,
    OFFLINE_CACHE_PAGE,
]

self.addEventListener("install", (evt) => {
    {% if debug %}console.log("[ServiceWorker] Install");{% endif %}

    // CODELAB: Precache static resources here.
    evt.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            {% if debug %}console.log("[ServiceWorker] Pre-caching offline page");{% endif %}

            return cache.addAll(CACHE_FILES);
        })
    );
    self.skipWaiting();
});

self.addEventListener("activate", (evt) => {
    {% if debug %}console.log("[ServiceWorker] Activate");{% endif %}

    // CODELAB: Remove previous cached data from disk.
    evt.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(
                keyList.map((key) => {
                    if (key !== CACHE_NAME) {
                        {% if debug %}console.log("[ServiceWorker] Removing old cache", key);{% endif %}

                        return caches.delete(key);
                    }
                })
            );
        })
    );

    self.clients.claim();
});

self.addEventListener("fetch", (evt) => {
    // If the request matches an item in CACHE_FILES, return it
    // This allows access to things like images when offline despite the evt.request.mode not being "navigate"
    if (CACHE_FILES.includes(evt.request.url)) {
        evt.respondWith(
            caches.open(CACHE_NAME).then((cache) => {
                return cache.match(evt.request.url);
            })
        );
        return;
    }

    // The offline page should only be returned if:
    // - The request is a page navigation (evt.request.mode === "navigate")
    // - AND the request is not a redirect (evt.request.redirect !== "follow")
    // https://fetch.spec.whatwg.org/#concept-request-mode is a good reference for what these actually mean
    //
    // The redirect check is to avoid triggering the offline page if we make a request to a page that fails to load (e.g. see SA-16458)
    if (evt.request.mode !== "navigate" && evt.request.redirect !== "follow") {
        // Not a page navigation, bail.
        return;
    }

    evt.respondWith(
        fetch(evt.request).catch(() => {
            return caches.open(CACHE_NAME).then((cache) => {
                return cache.match(OFFLINE_CACHE_PAGE);
            });
        })
    );
});

// Listen for messages from the main script
self.addEventListener('message', (event) => {
    // iOS requires notifications to be dispatched from inside a service worker
    // This provides a way for a script to show a notification after the user has granted permissions
    if (event.data.type === 'SHOW_NOTIFICATION') {
        const { title, options } = event.data;
        self.registration.showNotification(title, options);
    }
});

// example from: https://developer.mozilla.org/en-US/docs/Web/API/Clients/openWindow
// Notification click event listener
// Also in service-worker.js.twig (firebase) to handle notifications created from firebase
self.addEventListener("notificationclick", (e) => {
    // Close the notification popout
    e.notification.close();
    // Get all the Window clients
    e.waitUntil(
        clients.matchAll({ type: "window" }).then((clientsArr) => {
            let href = "/";
            // if no href or undefined then cast to user's dashboard
            if (e.notification.data.href !== '' && e.notification.data.href !== undefined) {
                href = e.notification.data.href;
            }
            // If a Window tab matching the targeted URL already exists, focus that;
            const hadWindowToFocus = clientsArr.some((windowClient) =>
                windowClient.url === href
                    ? (windowClient.focus(), true)
                    : false,
            );
            // Otherwise, open a new tab to the applicable URL and focus it.
            if (!hadWindowToFocus)
                clients
                    .openWindow(href)
                    .then((windowClient) => (windowClient ? windowClient.focus() : null));
        }),
    );
});
