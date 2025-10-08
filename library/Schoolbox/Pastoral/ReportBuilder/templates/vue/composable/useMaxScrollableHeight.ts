/**
 * Manages the maximum scrollable height for containers to fit within the browser window.
 * Automatically adjusts when the window resizes or container size changes.
 */

import type { Ref } from "vue";

/**
 * Provides reactive maximum scrollable height calculation for table containers.
 * @param scrollableEl - Ref to the scrollable HTML element
 * @returns maxScrollableHeight - reactive ref containing the calculated max height as a CSS string
 */
export function useMaxScrollableHeight(scrollableEl: Ref<HTMLElement | null>): {
    maxScrollableHeight: Ref<string>;
} {
    const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
    const maxScrollableHeight = window.Vue.ref<string>(`${window.innerHeight}px`);

    /**
     * Set the max height to allow the table to sit within the browser window
     */
    function setScrollableHeight(): void {
        const scrollableTop = scrollableEl.value?.getBoundingClientRect().top || 0;
        const footerHeight = document.getElementById("footer")?.getBoundingClientRect().height || 0;
        const mainSBContainerEl = document.getElementById("container");
        const mainSBContainerPaddingBtm = mainSBContainerEl
            ? parseInt(window.getComputedStyle(mainSBContainerEl).paddingBottom, 10)
            : 0;
        const newHeight = window.innerHeight - scrollableTop - footerHeight - mainSBContainerPaddingBtm - 1;
        maxScrollableHeight.value = `${newHeight}px`;
    }

    /**
     * Initialize the ResizeObserver to adjust the scrollable height
     * when the container changes size
     */
    function initSetScrollableHeight(): void {
        resizeObserver.value = new ResizeObserver(setScrollableHeight);
        const containerEl = document.querySelector("#container");
        if (containerEl) {
            resizeObserver.value.observe(containerEl);
        }
        // Set the initial height, and update it again when the DOM is fully loaded to ensure the footer is included
        setScrollableHeight();
        window.addEventListener("DOMContentLoaded", setScrollableHeight, { once: true });
    }

    /**
     * Clean up the ResizeObserver when the component is unmounted
     */
    function cleanup(): void {
        if (resizeObserver.value) {
            resizeObserver.value.disconnect();
            resizeObserver.value = null;
        }
    }

    // Initialize on mount
    window.Vue.onMounted(() => {
        initSetScrollableHeight();
    });

    // Cleanup on unmount
    window.Vue.onUnmounted(() => {
        cleanup();
    });

    return {
        maxScrollableHeight,
    };
}
