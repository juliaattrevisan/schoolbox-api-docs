/**
 *
 */

import type { Ref } from "vue";
import type { Metadata } from "@js/util/sbx/apiUtils";

/**
 *
 * @param endpoint -
 * @param limit - number of items per page
 * @param metadata - metadata containing pagination information
 * @returns -
 */
export function usePagination(
    endpoint: Ref<string>,
    limit: number,
    metadata: Ref<Metadata>,
): {
    hasPrevious: Ref<boolean>;
    hasNext: Ref<boolean>;
    toPrevious: () => void;
    toNext: () => void;
    reset: () => void;
    cursorIndex: Ref<number>;
    currentCursor: Ref<string>;
    hasPaginate: Ref<boolean>;
} {
    const cursorList = window.Vue.ref<string[]>([""]); // to distinguish between loading a new table and loading data for the same table
    const cursorIndex = window.Vue.ref(0); // to track the current cursor index
    const currentCursor = window.Vue.computed(() => cursorList.value[cursorIndex.value] || "");
    const hasPaginate = window.Vue.ref<boolean>(false);
    const hasPrevious = window.Vue.computed(() => cursorIndex.value > 0);
    const hasNext = window.Vue.computed(() => cursorIndex.value < cursorList.value.length - 1);
    const pageCount = window.Vue.ref(0);

    /**
     * Pagination should currently be reset if sorting on the endpoint changes,
     * or if the endpoint URL changes in a way that does not include the cursor.
     * @param oldUrl - the previous endpoint URL
     * @param newUrl - the new endpoint URL
     * @returns - true if pagination should be reset
     */
    function shouldResetPagination(oldUrl: string, newUrl: string): boolean {
        try {
            const oldUrlObj = new URL(oldUrl, window.location.origin);
            const newUrlObj = new URL(newUrl, window.location.origin);

            // If pathname or hash changed, definitely reset
            if (oldUrlObj.pathname !== newUrlObj.pathname || oldUrlObj.hash !== newUrlObj.hash) {
                return true;
            }

            // Compare query parameters excluding cursor
            const oldParams = new URLSearchParams(oldUrlObj.search);
            const newParams = new URLSearchParams(newUrlObj.search);

            // Remove cursor parameter from both for comparison
            oldParams.delete("cursor");
            newParams.delete("cursor");

            // Remove selected parameter from both for comparison
            oldParams.delete("selected");
            newParams.delete("selected");

            // If the remaining parameters are different, reset pagination
            return oldParams.toString() !== newParams.toString();
        } catch (error) {
            // If URL parsing fails, be safe and reset
            return true;
        }
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
     * watch endpoint and if anything other than cursor changes, it means the pagination needs to reset
     */
    window.Vue.watch(endpoint, (newEndpoint, oldEndpoint) => {
        if (endpoint.value !== "" && newEndpoint !== oldEndpoint) {
            // Check if this is a URL change that should trigger a reset
            if (shouldResetPagination(oldEndpoint, newEndpoint)) {
                reset();
            }
        }
    });

    /**
     *
     */
    function checkMetadata(): void {
        hasPaginate.value = metadata.value.count >= limit;
        if (hasPaginate.value) {
            cursorList.value.push(metadata.value.cursor.next || "");
            pageCount.value = Math.ceil(metadata.value.count / limit);
            console.warn("Pagination enabled:", cursorList.value);
            console.warn("Page count:", pageCount.value);
        }
    }

    /**
     *
     */
    window.Vue.watch(metadata, () => {
        // only add a new cursor if the metadata has a next cursor
        if (cursorList.value.length !== pageCount.value) {
            checkMetadata();
        }
    });

    /**
     * Start setting the scrollable height on any window resize change
     */
    window.Vue.onMounted(() => {
        reset();
        checkMetadata();
    });

    return {
        hasPrevious,
        hasNext,
        toPrevious,
        toNext,
        reset,
        cursorIndex,
        currentCursor,
        hasPaginate,
    };
}
