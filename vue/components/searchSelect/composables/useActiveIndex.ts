/**
 * To highlight the active result in the search results,
 * tracking the activeIndex which changes with keyboard navigation,
 * and can also be altered via mouse hover
 */

import type { Ref } from "vue";
import { useElementVisibility, onKeyStroke } from "@vueuse/core";

/**
 * @param resultEl - the element to scroll into view
 * @param index - the index of the result
 * @param activeIndex - the index of the active result
 * @param onSelected - the function to call when the result is selected
 * @returns - isActive
 */
export function useActiveIndex(
    resultEl: Ref<HTMLElement | null>,
    index: Ref<number>,
    activeIndex: Ref<number>,
    onSelected: () => void,
): {
    isActive: Ref<boolean>;
} {
    const targetIsVisible = useElementVisibility(resultEl);
    const isActive = window.Vue.ref(false);

    /**
     * Check if the index is the active index and if so,
     * set active and scroll the result into view if
     * not already visible
     */
    function checkIndex(): void {
        if (index.value === activeIndex.value) {
            isActive.value = true;
            if (!targetIsVisible.value) {
                resultEl.value?.scrollIntoView({ block: "nearest", behavior: "smooth" });
            }
        } else {
            isActive.value = false;
        }
    }

    /**
     * Check on any change to the activeIndex (keyboard navigation)
     */
    window.Vue.watch(activeIndex, () => {
        checkIndex();
    });

    /**
     * Check on mount
     */
    window.Vue.onMounted(() => {
        checkIndex();
    });

    /**
     * To select the result on enter key press
     */
    onKeyStroke("Enter", () => {
        if (isActive.value) {
            onSelected();
        }
    });

    return { isActive };
}
