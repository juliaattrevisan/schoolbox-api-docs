/**
 * Store the current search results when searching from sp-input
 */

import { defineStore } from "pinia";

export const useResultsStore = defineStore("spResults", () => {
    const results = window.Vue.ref<unknown[]>([]); // returned search results
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
     * clear the current search
     */
    function clear() {
        results.value = [];
    }

    return {
        results,
        hasResults,
        hasNoResults,
        clear,
    };
});
