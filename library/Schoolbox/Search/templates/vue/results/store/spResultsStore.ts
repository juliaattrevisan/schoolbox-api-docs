/**
 * Store the current search results when searching
 */

import { defineStore } from "pinia";
import type { Results } from "../../../js/types/Results";
import type { Result } from "../../../js/types/Result";
import { useHasMoreResults } from "../composable/useHasMoreResults";

const EMPTY_RESULTS = { data: [], metadata: { cursor: { next: null } } }; // empty results to initialise the store

export const useResultsStore = defineStore("spResults", () => {
    const allResults = window.Vue.ref<Result[]>([]); // all returned search results
    const latestResults = window.Vue.ref<Results>(EMPTY_RESULTS); // latest loaded result group
    const moreResultsCount = window.Vue.ref(0); // number of subsequent result sets that have been loaded
    const hasLoadedAllResults = window.Vue.ref(false); // to determine whether all results have been loaded

    /**
     * To show that there is results that match the current search
     * @returns {boolean}
     */
    const hasResults = window.Vue.computed(() => {
        return allResults.value.length > 0 || latestResults.value.metadata.cursor.next !== null;
    });

    /**
     * To show that no results match the current search
     * @returns {boolean}
     */
    const hasNoResults = window.Vue.computed(() => {
        return allResults.value.length === 0 && latestResults.value.metadata.cursor.next === null;
    });

    /**
     * Return the total number of results.
     * Note that allResults.length will give you the total number of parent folders of results.
     * This is because results are grouped by parent folder before being passed to the client.
     * @returns {number}
     */
    const totalResultsCount = window.Vue.computed(() => {
        let n = 0;

        for (let i = 0; i < allResults.value.length; i += 1) {
            const result = allResults.value[i];

            // Note on the subtleties involved in counting search results
            // ----------------------------------------------------------
            // There is a subtlety here that prevents us from counting results in the same way as the server does.
            // At the server, a set of results is fetched (some of which may be folders).  This is the original set of results.
            // Then for each non-folder result, that result's parent folder is fetched.
            // Then all non-folder results are grouped under their parent folders before being sent to the client.  So
            // at the client, each result is either a lone folder, or a parent folder with sub-results.
            //
            // It follows that when counting results at the client:
            //  * if we count the parent folders as results, we might end up with more results than we started with
            //    (if one or more folders were fetched after the original set of results).
            //  * if we don't count the parent folders as results, we might end up with fewer results than we started with
            //    (if some of the original parent results ended up being parent folders of other results).
            //
            // We choose to count parent folders as results here, so we end up with a count that is equal to or greater
            // than the original server count.  Since this count is used to determine whether more results should be
            // automatically fetched in order to make a full page of results, this choice prevents unneeded requests to
            // 'load more results'.
            n += (result._subResults?.data.length ?? 0) + 1; // +1 ensures the parent folder counts as a result.
        }

        return n;
    });

    /**
     * To show that there are more results that can be loaded
     * @returns {boolean}
     */
    const { hasMoreResults } = useHasMoreResults(hasLoadedAllResults, latestResults);

    /**
     * Set the first results
     * @param firstResults - the results to set
     */
    function setFirstResults(firstResults: Results): void {
        hasLoadedAllResults.value = false;
        allResults.value = firstResults.data;
        latestResults.value = firstResults;
        // If cursor.next has not been supplied, we have loaded all results.
        if (firstResults.metadata.cursor.next === null) {
            hasLoadedAllResults.value = true;
        }
    }

    /**
     * Set the next results
     * @param moreResults - the results to set
     */
    function setMoreResults(moreResults: Results): void {
        allResults.value = [...allResults.value, ...moreResults.data];
        latestResults.value = moreResults;
        moreResultsCount.value += 1;
        // If cursor.next has not been supplied, we have loaded all results.
        if (moreResults.metadata.cursor.next === null) {
            hasLoadedAllResults.value = true;
        }
    }

    /**
     * Add the loaded sub results into the results, also updating
     * the meta data for future further sub result loads
     * @param moreSubResults - the sub results to set
     * @param parentIndex - the index of the parent result
     */
    function setMoreSubResults(moreSubResults: Results, parentIndex: number): void {
        const parentResult = allResults.value[parentIndex] as Result;
        const subResults = parentResult._subResults as Results;
        subResults.data = [...(parentResult._subResults?.data as Result[]), ...moreSubResults.data];
        subResults.metadata.cursor = moreSubResults.metadata.cursor; // update the cursor for future sub result loads
    }

    /**
     * clear the current results
     */
    function clear(): void {
        allResults.value = [];
        latestResults.value = EMPTY_RESULTS;
        moreResultsCount.value = 0;
        hasLoadedAllResults.value = false;
    }

    return {
        allResults,
        totalResultsCount,
        latestResults,
        hasLoadedAllResults,
        hasResults,
        hasNoResults,
        hasMoreResults,
        setFirstResults,
        setMoreResults,
        setMoreSubResults,
        clear,
    };
});
