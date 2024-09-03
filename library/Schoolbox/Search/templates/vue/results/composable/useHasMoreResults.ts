/**
 * To determine if there's more search results to be shown
 * Is used by both results and sub results
 */

import type { Ref } from "vue";
import type { Results } from "../../../js/types/Results";

/**
 * To show that whether there are more results that can be loaded
 * @param hasLoadedAllResults - whether all results have been loaded
 * @param results - the current results
 * @returns - whether there are more results that can be loaded
 */
export function useHasMoreResults(
    hasLoadedAllResults: Ref<boolean>,
    results: Ref<Results>,
): { hasMoreResults: Ref<boolean> } {
    /**
     * To show that there are more results that can be loaded
     * @returns {boolean}
     */
    const hasMoreResults = window.Vue.computed(() => {
        if (hasLoadedAllResults.value) {
            return false;
        }
        // If the latest result set included a cursor, there are still more results.
        if (results.value.metadata.cursor.next !== null) {
            return true;
        }
        return false;
    });

    return { hasMoreResults };
}
