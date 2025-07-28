/**
 *
 */

import type { Ref } from "vue";
import type { Metadata } from "@js/util/sbx/apiUtils";

/**
 *
 * @param perPage - number of items per page
 * @param metadata - metadata containing pagination information
 * @returns
 */
export function usePagination(
    perPage: number,
    metadata: Metadata,
): {
    hasPrevious: () => boolean;
    hasNext: () => boolean;
    toPrevious: () => void;
    toNext: () => void;
    reset: () => void;
    currentCursor: Ref<string>;
} {
    const cursorList = window.Vue.ref<string[]>([""]); // to distinguish between loading a new table and loading data for the same table
    const cursorIndex = window.Vue.ref(0); // to track the current cursor index
    const currentCursor = window.Vue.computed(() => cursorList.value[cursorIndex.value] || "");

    /**
     * Check if there is a previous page
     * @returns - true if there is a previous page, false otherwise
     */
    function hasPrevious(): boolean {
        return cursorIndex.value > 0;
    }

    /**
     * Check if there is a next page
     * @returns - true if there is a next page, false otherwise
     */
    function hasNext(): boolean {
        return cursorIndex.value < cursorList.value.length - 1;
    }

    /**
     * Move to the previous page
     */
    function toPrevious(): void {
        cursorIndex.value -= 1;
    }

    /**
     * Move to the next page
     */
    function toNext(): void {
        cursorIndex.value += 1;
    }

    /**
     * Reset the pagination state
     */
    function reset(): void {
        cursorList.value = [""];
        cursorIndex.value = 0;
    }

    /**
     *
     *
     */
    window.Vue.watch(metadata, () => {
        console.warn("Metadata changed ya:", metadata);
    });

    return {
        hasPrevious,
        hasNext,
        toPrevious,
        toNext,
        reset,
        currentCursor,
    };
}
