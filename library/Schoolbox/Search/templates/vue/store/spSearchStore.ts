/**
 * To request a search based on the query and any filters set
 */

import { defineStore } from "pinia";
import { useStore } from "./searchPageStore";
import { useQueryStore } from "./spQueryStore";
import { useResultsStore } from "../results/store/spResultsStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";
import type { Results } from "../../js/types/Results";

export const useSearchStore = defineStore("spSearch", () => {
    const hasNotSearched = window.Vue.ref(true); // to determine whether any searches have been made
    const isSearching = window.Vue.ref(false); // to notify that search results
    const hasLoadError = window.Vue.ref(false); // to notify of a search error
    const hasSearchedWithActiveFilters = window.Vue.ref(false); // to determine whether the latest search has been made with filters

    /**
     * To collect all the params to send to the search api
     * @returns {string} - the query params
     */
    function getQueryParams() {
        const newQueryParams = new URLSearchParams();
        newQueryParams.append("keyword", useQueryStore().activeQuery);
        return newQueryParams.toString();
    }

    /**
     * search for nodes that match
     */
    async function search() {
        useQueryStore().setActiveQuery();
        hasSearchedWithActiveFilters.value = useFiltersStore().isActive;
        hasNotSearched.value = false;
        isSearching.value = true;
        try {
            const response = await fetch(`${useStore().endpoint}?${getQueryParams()}`, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            });
            const resultsData = (await response.json()) as Results;
            useResultsStore().results = resultsData.data;
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
        isSearching.value = false;
        useQueryStore().clear();
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
        hasSearchedWithActiveFilters,
        search,
        clear,
        clearFiltersAndSearch,
    };
});
