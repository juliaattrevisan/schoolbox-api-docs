/**
 * To request a search based on the query and any filters set
 */

import { defineStore } from "pinia";
import { useStore } from "./searchPageStore";
import { useQueryStore } from "./spQueryStore";
import { useResultsStore } from "../results/store/spResultsStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";
import type { Results } from "../../js/types/Results";

/**
 * What is returned when there is a search error
 */
export type SearchError = {
    title: string;
    type: string;
    status: number;
};

export const useSearchStore = defineStore("spSearch", () => {
    const hasNotSearched = window.Vue.ref(true); // to prompt a search if the user hasn't searched yet
    const url = window.Vue.ref("");
    const isSearching = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);
    const searchError = window.Vue.ref<SearchError | null>(null);
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * Whether a search can be performed, can be if there's a change of query or filters
     * @returns {boolean} - whether a new search can be performed
     */
    const canSearch = window.Vue.computed(() => {
        return useQueryStore().canSearch || useFiltersStore().canSearch;
    });

    /**
     * search for nodes that match
     * @param simulateRedirect - whether to simulate a redirect
     */
    async function search(simulateRedirect: boolean): Promise<void> {
        useQueryStore().setActive();
        useFiltersStore().setActive();
        useResultsStore().clear();
        useStore().setQueryParams(simulateRedirect);
        hasNotSearched.value = false;
        url.value = useStore().getEndpointWithParams();
        // abort the previous search if it's still running
        if (isSearching.value) {
            controller.abort();
        }
        try {
            isSearching.value = true;
            hasLoadError.value = false;
            controller = new AbortController();
            const response = await fetch(url.value, {
                method: "GET",
                signal: controller.signal,
                headers: {
                    "Content-Type": "application/json",
                },
            });
            const responseJson = (await response.json()) as Results | SearchError;
            // if there's a status, it's an error
            if ((responseJson as SearchError).status) {
                hasLoadError.value = true;
                searchError.value = responseJson as SearchError;
            } else {
                useResultsStore().setFirstResults(responseJson as Results);
            }
            isSearching.value = false;
        } catch (error) {
            // if the error is a DOMException, it's because the search has been aborted to start a new one
            if (typeof error === "string" && !error.includes("DOMException")) {
                hasLoadError.value = true;
            }
        }
    }

    /**
     * clear the filters and search
     */
    async function clearFiltersAndSearch(): Promise<void> {
        useFiltersStore().clear();
        await search(true);
    }

    return {
        hasNotSearched,
        isSearching,
        hasLoadError,
        search,
        clearFiltersAndSearch,
        searchError,
        canSearch,
    };
});
