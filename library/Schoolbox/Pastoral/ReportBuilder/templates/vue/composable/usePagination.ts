/**
 * To handle pagination logic for the pastoral report table
 * We currently have to do a bit of wrangling for pagination,
 * as we just rely on metadata.cursor.next to determine if there is a next page,
 * and there's no metadata.cursor.previous.
 * So we have to keep track of the cursor list and the current index.
 * This allows us to navigate through pages without relying on the backend to provide previous cursors.
 */

import type { Ref } from "vue";
import type { Metadata } from "@js/util/sbx/apiUtils";
import { ViewUrlParam } from "../../js/types/pastoralReportTypes";

/**
 *
 * @param url -
 * @param limit - number of items per page
 * @param metadata - metadata containing pagination information
 * @returns -
 */
export function usePagination(
    url: Ref<string>,
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
    const hasNext = window.Vue.computed(() => cursorIndex.value < pageCount.value - 1);
    const pageCount = window.Vue.ref(0);

    /**
     * Pagination should currently be reset if sorting on the url changes,
     * or if the url URL changes in a way that does not include the cursor.
     * @param oldUrl - the previous url URL
     * @param newUrl - the new url URL
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
            oldParams.delete(ViewUrlParam.CURSOR as string);
            newParams.delete(ViewUrlParam.CURSOR as string);

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
     * watch url and if anything other than cursor changes, it means the pagination needs to reset
     */
    window.Vue.watch(url, (newUrl, oldUrl) => {
        if (url.value !== "" && newUrl !== oldUrl) {
            // Check if this is a URL change that should trigger a reset
            if (shouldResetPagination(oldUrl, newUrl)) {
                reset();
            }
        }
    });

    /**
     * Check if the metadata has enough items to paginate
     */
    function checkMetadata(): void {
        hasPaginate.value = metadata.value.count > limit;
        // if the count is higher than the limit, we can paginate
        if (hasPaginate.value) {
            // get the next cursor from metadata
            cursorList.value.push(metadata.value.cursor.next || "");
            // and update the page count
            pageCount.value = Math.ceil(metadata.value.count / limit);
        }
    }

    /**
     * Watch for changes in the metadata and see if we can add a new cursor
     */
    window.Vue.watch(metadata, () => {
        // only add a new cursor if the metadata has a next cursor
        if (cursorList.value.length !== pageCount.value) {
            checkMetadata();
        }
    });

    /**
     * Initialize the pagination state on component mount
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
