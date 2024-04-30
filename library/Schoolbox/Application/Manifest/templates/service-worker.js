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
