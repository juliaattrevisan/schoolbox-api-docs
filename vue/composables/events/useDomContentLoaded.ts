/**
 * Sometimes we need to know when the DOM content is loaded, so we can wait for it before performing certain actions.
 * This was originally created to be used in the curriculum selector, so we can wait for Foundation to be ready before
 * the curriculum modal (which currently uses the foundation sequential modal) can be opened. This is an issue on slower connections
 * and when the curriculum selector is used on a page that has a lot of other JS to be loaded below (like CKEditor) the selector.
 */

import type { Ref } from "vue";

/**
 * To detect when the DOM content is loaded.
 * @returns - hasDomContentLoaded
 */
export function useDomContentLoaded(): {
    hasDomContentLoaded: Ref<boolean>;
} {
    const hasDomContentLoaded = window.Vue.ref(false);

    /**
     * To notify when the DOM content is loaded
     */
    function onDomContentLoaded(): void {
        void window.Vue.nextTick().then(() => {
            hasDomContentLoaded.value = true;
            window.removeEventListener("DOMContentLoaded", onDomContentLoaded);
        });
    }

    /**
     * Listens for the DOMContentLoaded event to set hasDomContentLoaded to true.
     */
    if (document.readyState === "loading") {
        // DOM is still loading, listen for the event
        window.addEventListener("DOMContentLoaded", onDomContentLoaded);
    } else {
        // DOM is already loaded (interactive or complete)
        void window.Vue.nextTick().then(() => {
            hasDomContentLoaded.value = true;
        });
    }

    return {
        hasDomContentLoaded,
    };
}
