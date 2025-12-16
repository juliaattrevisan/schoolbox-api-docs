/**
 * Composable to track when the content height changes
 * so the grid row height can be updated accordingly
 */

import type { Ref } from "vue";
import type { useLazyGridRowsHeightStore } from "../store/lgRowsHeightStore";

/**
 * Tracks the content height and component action height of a grid cell
 * @param id - The row ID
 * @param headerKey - The key of the header
 * @param contentEl - The ref to the content element
 * @param componentActionEl - The ref to the component action element
 * @param lgRowsHeightStore - The lazy grid rows height store instance
 * @returns contentHeight, componentActionHeight - The heights of the content and component action elements
 */
export function useContentHeight(
    id: number,
    headerKey: string,
    contentEl: Ref<HTMLElement | null>,
    componentActionEl: Ref<HTMLElement | null>,
    lgRowsHeightStore: ReturnType<typeof useLazyGridRowsHeightStore>,
): {
    contentHeight: Ref<number>;
    componentActionHeight: Ref<number>;
} {
    const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
    const contentHeight = window.Vue.ref(0);
    const componentActionHeight = window.Vue.ref(0);

    /**
     * Update the content height in the store when the content height changes
     */
    function updateContentHeight(): void {
        contentHeight.value = contentEl.value?.offsetHeight || 0;
        componentActionHeight.value = componentActionEl.value?.offsetHeight || 0;
        lgRowsHeightStore.setContentHeight(id, headerKey, contentHeight.value + componentActionHeight.value);
    }

    /**
     * To detect when the dimensions of the cell changes,
     * like when a different tab is selected
     */
    function initResizeObserver(): void {
        resizeObserver.value = new ResizeObserver(() => updateContentHeight());
        resizeObserver.value.observe(contentEl.value as HTMLElement);
    }

    /**
     * When the component is mounted, initialise the resize observer
     * and update the content height after a tick to ensure the content is rendered
     */
    window.Vue.onMounted(() => {
        initResizeObserver();
        // Wait a tick to ensure the content has been rendered before measuring its height
        setTimeout(updateContentHeight, 0);
    });

    /**
     * Remove the resize observer when the component is unmounted
     */
    window.Vue.onUnmounted(() => {
        if (resizeObserver.value) {
            resizeObserver.value.disconnect();
            resizeObserver.value = null;
        }
    });

    return {
        contentHeight,
        componentActionHeight,
    };
}
