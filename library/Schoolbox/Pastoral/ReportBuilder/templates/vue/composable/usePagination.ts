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
    metadata: Ref<Metadata>,
): {
    hasPrevious: Ref<boolean>;
    hasNext: Ref<boolean>;
    toPrevious: () => void;
    toNext: () => void;
    reset: () => void;
    currentCursor: Ref<string>;
    hasPaginate: Ref<boolean>;
} {
    const cursorList = window.Vue.ref<string[]>([""]); // to distinguish between loading a new table and loading data for the same table
    const cursorIndex = window.Vue.ref(0); // to track the current cursor index
    const currentCursor = window.Vue.computed(() => cursorList.value[cursorIndex.value] || "");
    const hasPaginate = window.Vue.ref<boolean>(false);
    const hasPrevious = window.Vue.computed(() => cursorIndex.value > 0);
    const hasNext = window.Vue.computed(() => cursorIndex.value < cursorList.value.length - 1);

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
        hasPaginate.value = metadata.value.count >= perPage;
        if (hasPaginate.value) {
            cursorList.value.push(metadata.value.cursor.next || "");
            console.warn("Pagination enabled:", cursorList.value);
        }
    }

    /**
     *
     */
    window.Vue.watch(metadata, () => {
        console.warn("Metadata changed ya:", metadata);
    });

    /**
     * Start setting the scrollable height on any window resize change
     */
    window.Vue.onMounted(() => {
        reset();
    });

    return {
        hasPrevious,
        hasNext,
        toPrevious,
        toNext,
        reset,
        currentCursor,
        hasPaginate,
    };
}
