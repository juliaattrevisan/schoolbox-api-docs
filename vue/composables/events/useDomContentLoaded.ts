/**
 * Sometimes we need to know when the DOM content is loaded, so we can wait for it before performing certain actions.
 * This was originally created to be used in the curriculum selector, so we can wait for Foundation to be ready before
 * the curriculum modal can be opened
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
        });
    }

    /**
     *  Listens for the DOMContentLoaded event to set hasDomContentLoaded to true
     */
    window.addEventListener("DOMContentLoaded", onDomContentLoaded);

    return {
        hasDomContentLoaded,
    };
}
