/**
 * Store the current search query
 */

import { defineStore } from "pinia";

export const SEARCH_PARAM = "keyword"; // the incoming param to check for initial search

export const useQueryStore = defineStore("spQuery", () => {
    /**
     * get the initial query from the url if it exists
     * @returns the initial query
     */
    function getInitialQuery(): string {
        const searchParams = new URLSearchParams(document.location.search);
        return searchParams.get(SEARCH_PARAM) ?? "";
    }

    const query = window.Vue.ref(getInitialQuery()); // current search input
    const activeQuery = window.Vue.ref(""); // the latest query that was searched

    /**
     * search has been triggered so set the active query
     */
    function setActive(): void {
        activeQuery.value = query.value;
    }

    /**
     * Whether a search can be performed, can be if there's a change of query or filters
     * @returns {boolean} - whether there's been a change in the date range
     */
    const canSearch = window.Vue.computed(() => {
        return activeQuery.value !== query.value;
    });

    /**
     * clear the current search
     */
    function clear(): void {
        query.value = "";
    }

    return {
        query,
        activeQuery,
        setActive,
        canSearch,
        clear,
    };
});
