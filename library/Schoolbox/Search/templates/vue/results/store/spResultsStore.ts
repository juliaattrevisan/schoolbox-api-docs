/**
 * Store the current search results when searching from sp-input
 */

import { defineStore } from "pinia";
import type { Result } from "../../../js/types/Result";

const RESULT_LIMIT = 20; // number of results returned by the api

export const useResultsStore = defineStore("spResults", () => {
    const results = window.Vue.ref<Result[]>([]); // returned search results
    const moreResultsCount = window.Vue.ref(0); // number of subsequent results that have been loaded
    const hasLoadedAllResults = window.Vue.ref(false); // to determine whether all results have been loaded

    /**
     * To show that there is results that match the current search
     * @returns {boolean}
     */
    const hasResults = window.Vue.computed(() => {
        return results.value.length > 0;
    });

    /**
     * To show that no results match the current search
     * @returns {boolean}
     */
    const hasNoResults = window.Vue.computed(() => {
        return results.value.length === 0;
    });

    /**
     * To show that there are more results that can be loaded
     * @returns {boolean}
     */
    const hasMoreResults = window.Vue.computed(() => {
        if (hasLoadedAllResults.value) {
            return false;
        }
        // if we are showing the current limit, its assumed we have more results to show
        if (results.value.length === RESULT_LIMIT * (moreResultsCount.value + 1)) {
            return true;
        }
        return false;
    });

    /**
     * Set the first results
     * @param {Result[]} firstResults - the results to set
     */
    function setFirstResults(firstResults: Result[]) {
        results.value = firstResults;
    }

    /**
     *  Set the next results
     * @param {Result[]} moreResults - the results to set
     */
    function setMoreResults(moreResults: Result[]) {
        results.value = [...results.value, ...moreResults];
        moreResultsCount.value += 1;
    }

    /**
     * clear the current results
     */
    function clear() {
        results.value = [];
        moreResultsCount.value = 0;
    }

    return {
        results,
        hasLoadedAllResults,
        hasResults,
        hasNoResults,
        hasMoreResults,
        setFirstResults,
        setMoreResults,
        clear,
    };
});
