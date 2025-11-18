/**
 *
 */

import type { Ref } from "vue";
import type { Profile, ProfileKeys } from "../../../js/types/manageDifferentiationProfilesTypes";
import { useRowsStore } from "../../store/mdpRowsStore";

/**
 *
 * @param profile - The profile object
 * @param headerKey - The key of the profile header
 * @param contentEl - The ref to the content element
 * @param componentActionEl - The ref to the component action element
 * @returns contentHeight, componentActionHeight - The heights of the content and component action elements
 *
 */
export function useContentHeight(
    profile: Profile,
    headerKey: ProfileKeys,
    contentEl: Ref<HTMLElement | null>,
    componentActionEl: Ref<HTMLElement | null>,
): {
    contentHeight: Ref<number>;
    componentActionHeight: Ref<number>;
} {
    const rowsStore = useRowsStore();
    const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
    const contentHeight = window.Vue.ref(0);
    const componentActionHeight = window.Vue.ref(0);

    /**
     * Update the content height in the store when the content height changes
     */
    function updateContentHeight(): void {
        contentHeight.value = contentEl.value?.offsetHeight || 0;
        componentActionHeight.value = componentActionEl.value?.offsetHeight || 0;
        rowsStore.setContentHeight(profile, headerKey, contentHeight.value + componentActionHeight.value);
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
