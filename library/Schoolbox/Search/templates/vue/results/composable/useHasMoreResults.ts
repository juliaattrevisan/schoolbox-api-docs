/**
 * To determine if there's more search results to be shown
 * Is used by both results and sub results
 */

import type { Ref } from "vue";
import type { Result } from "../../../js/types/Result";

/**
 * To show that whether there are more results that can be loaded
 * @param {Ref<boolean>} hasLoadedAllResults - whether all results have been loaded
 * @param {Ref<Result[]>} results - the current results
 * @param {Ref<number>} moreResultsCount - the number of subsequent result sets that have been loaded
 * @param {number} resultLimit - the number of results returned by the api on a single call
 * @returns {boolean} - whether there are more results that can be loaded
 */
export function useHasMoreResults(
    hasLoadedAllResults: Ref<boolean>,
    results: Ref<Result[]>,
    moreResultsCount: Ref<number>,
    resultLimit: number
) {
    /**
     * To show that there are more results that can be loaded
     * @returns {boolean}
     */
    const hasMoreResults = window.Vue.computed(() => {
        if (hasLoadedAllResults.value) {
            return false;
        }
        // if we are showing the current limit, its assumed we have more results to show
        if (results.value.length === resultLimit * (moreResultsCount.value + 1)) {
            return true;
        }
        return false;
    });

    return { hasMoreResults };
}
