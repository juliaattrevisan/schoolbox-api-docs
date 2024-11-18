/**
 * To notify of when the print dialog is opened/closed
 */

import type { Ref } from "vue";

/**
 * To notify of when the print dialog is opened/closed
 * @returns - isPrinting
 */
export function usePrintDialog(): {
    isPrinting: Ref<boolean>;
} {
    const isPrinting = window.Vue.ref(false);

    /**
     * Listen for the print dialog opening
     */
    window.addEventListener("beforeprint", () => {
        isPrinting.value = true;
    });

    /**
     * Listen for the print dialog closing
     */
    window.addEventListener("afterprint", () => {
        isPrinting.value = false;
    });

    return {
        isPrinting,
    };
}
