/**
 * Store the current search results when searching
 */

import { defineStore } from "pinia";
import type { Results } from "../../../js/types/Results";
import type { Result } from "../../../js/types/Result";
import { useHasMoreResults } from "../composable/useHasMoreResults";

const EMPTY_RESULTS = { data: [], metadata: { cursor: { next: "" } } }; // empty results to initialise the store

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
        return allResults.value.length > 0;
    });

    /**
     * To show that no results match the current search
     * @returns {boolean}
     */
    const hasNoResults = window.Vue.computed(() => {
        return allResults.value.length === 0;
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
