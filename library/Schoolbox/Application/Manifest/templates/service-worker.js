"use strict";

const CACHE_NAME = "static-cache-v1";

const FILES_TO_CACHE = ["/manifest/offline", "{{ CDN_PREFIX }}/images/core/fail-whale.png"];

self.addEventListener("install", (evt) => {
    {% if debug %}console.log("[ServiceWorker] Install");{% endif %}

    // CODELAB: Precache static resources here.
    evt.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            {% if debug %}console.log("[ServiceWorker] Pre-caching offline page");{% endif %}

            return cache.addAll(FILES_TO_CACHE);
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
    // CODELAB: Add fetch event handler here.
    if (evt.request.mode !== "navigate" || evt.request.mode !== "follow") {
        // Not a page navigation, bail.
        return;
    }
    evt.respondWith(
        fetch(evt.request).catch(() => {
            return caches.open(CACHE_NAME).then((cache) => {
                return cache.match("/manifest/offline");
            });
        })
    );
});
