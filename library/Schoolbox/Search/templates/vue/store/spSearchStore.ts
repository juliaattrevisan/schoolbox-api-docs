/**
 * Store the current search results when searching from sp-input
 */

import { defineStore } from "pinia";
import { useStore } from "./searchPageStore";
import { useResultsStore } from "../results/store/spResultsStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";

const MIN_QUERY_LENGTH = 2; // the amount of characters typed before the search becomes active

export const useSearchStore = defineStore("spSearch", () => {
    const hasNotSearched = window.Vue.ref(true); // to determine whether any searches have been made
    const isSearching = window.Vue.ref(false); // to notify that search results
    const hasLoadError = window.Vue.ref(false); // to notify of a search error
    const query = window.Vue.ref("biology"); // current search input
    const activeQuery = window.Vue.ref(""); // the latest query that was searched
    const hasSearchedWithActiveFilters = window.Vue.ref(false); // to determine whether the latest search has been made with filters

    /**
     * To only start showing search results once enough characters
     * have been added
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeQuery.value.length >= MIN_QUERY_LENGTH);

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
     * search for nodes that match
     */
    async function search() {
        activeQuery.value = query.value;
        hasSearchedWithActiveFilters.value = useFiltersStore().isActive;
        hasNotSearched.value = false;
        isSearching.value = true;
        try {
            const response = await fetch(`${useStore().endpoint}?query=${activeQuery.value}`, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            });
            useResultsStore().results = (await response.json()) as unknown[];
        } catch (error) {
            hasLoadError.value = true;
        } finally {
            isSearching.value = false;
        }
    }

    /**
     * clear the current search
     */
    function clear() {
        query.value = "";
        activeQuery.value = "";
        isSearching.value = false;
        useResultsStore().clear();
    }

    /**
     * clear the filters and search
     */
    async function clearFiltersAndSearch() {
        useFiltersStore().clear();
        await search();
    }

    return {
        hasNotSearched,
        isSearching,
        hasLoadError,
        query,
        activeQuery,
        hasSearchedWithActiveFilters,
        isActive,
        queryWordList,
        search,
        clear,
        clearFiltersAndSearch,
    };
});
