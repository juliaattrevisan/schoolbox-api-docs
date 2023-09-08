/**
 * Store the current search query
 */

import { defineStore } from "pinia";

export const useQueryStore = defineStore("spQuery", () => {
    /**
     * get the initial query from the url if it exists
     * @returns {string} the initial query
     */
    function getInitialQuery() {
        const searchParams = new URLSearchParams(document.location.search);
        return searchParams.get("keyword") ?? "";
    }

    const query = window.Vue.ref(getInitialQuery()); // current search input
    const activeQuery = window.Vue.ref(""); // the latest query that was searched

    /**
     * the search string broken into words, to search for each word
     * @returns {string[]} the search string broken into single words
     */
    const queryWordList = window.Vue.computed(() => {
        const queryTrimmed = activeQuery.value.trim(); // remove any front or back white space
        const regularExpression = /\s*(?:,|$)\s*/; // adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
        const commaSeparated = queryTrimmed.split(regularExpression) ?? []; // split the string into a comma separated list
        return commaSeparated.filter((word) => word.length > 0); // and ensure each contains a character
    });

    /**
     * search has been triggered so set the active query
     */
    function setActive() {
        activeQuery.value = query.value;
    }

    /**
     * clear the current search
     */
    function clear() {
        query.value = "";
        activeQuery.value = "";
    }

    return {
        query,
        activeQuery,
        queryWordList,
        setActive,
        clear,
    };
});
