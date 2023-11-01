/**
 * Store the current search results when searching
 */

import { defineStore } from "pinia";
import type { Results } from "../../../js/types/Results";
import type { Result } from "../../../js/types/Result";
import { useHasMoreResults } from "../composable/useHasMoreResults";

export const RESULT_LIMIT = 10; // Number of results returned by the api on a single call (must match Search/Api/Controller::MAX_RESULT_LIMIT).
export const SUB_RESULT_LIMIT = 10; // Number of sub results returned by the api on a single call (must match Search/Api/Controller::MAX_RESULT_LIMIT).
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
    const { hasMoreResults } = useHasMoreResults(hasLoadedAllResults, allResults, moreResultsCount, RESULT_LIMIT);

    /**
     * Set the first results
     * @param {Results} firstResults - the results to set
     */
    function setFirstResults(firstResults: Results) {
        hasLoadedAllResults.value = false;
        allResults.value = firstResults.data;
        latestResults.value = firstResults;
        // if we have less results than the limit, we have loaded all results
        if (allResults.value.length < RESULT_LIMIT) {
            hasLoadedAllResults.value = true;
        }
    }

    /**
     * Set the next results
     * @param {Result[]} moreResults - the results to set
     */
    function setMoreResults(moreResults: Results) {
        allResults.value = [...allResults.value, ...moreResults.data];
        latestResults.value = moreResults;
        moreResultsCount.value += 1;
        // check if we have loaded all results yet
        if (allResults.value.length < RESULT_LIMIT * (moreResultsCount.value + 1)) {
            hasLoadedAllResults.value = true;
        }
    }

    /**
     * Add the loaded sub results into the results
     * @param {Result[]} moreSubResults - the sub results to set
     * @param {number} resultId - the parent id of the result the new sub results are from
     */
    function setMoreSubResults(moreSubResults: Result[], resultId: number) {
        const parentResult = allResults.value.find((result) => result.id === resultId) as Result;
        (parentResult._subResults as Results).data = [
            ...(parentResult._subResults?.data as Result[]),
            ...moreSubResults,
        ];
    }

    /**
     * clear the current results
     */
    function clear() {
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
