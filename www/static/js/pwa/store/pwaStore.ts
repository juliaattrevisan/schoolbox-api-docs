/**
 * Handles the state of the PWA and notifications across all pwa components on the page
 */

import { defineStore } from "pinia";

export const usePwaStore = defineStore(`pwa`, () => {
    // The deferred PWA prompt event that can be used to trigger the install prompt
    // Depending on the browser, this may never be set to a value
    const deferredPrompt = window.Vue.ref<Event | null>(null);

    // If the browser supports the beforeinstallprompt event, store it
    // https://web.dev/articles/codelab-make-installable
    window.addEventListener("beforeinstallprompt", (event) => {
        // Prevent the mini-infobar from appearing on mobile
        event.preventDefault();

        // Stash the event so it can be triggered by the user later
        deferredPrompt.value = event;
    });

    // When the app is installed, clear the deferred prompt
    window.addEventListener("appinstalled", () => {
        deferredPrompt.value = null;
    });

    return {
        deferredPrompt,
    };
});
