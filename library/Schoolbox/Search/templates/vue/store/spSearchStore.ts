/**
 * To request a search based on the query and any filters set
 */

import { defineStore } from "pinia";
import { useFetch } from "@vueuse/core";
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
    const searchError = window.Vue.ref<SearchError | null>(null);
    const {
        execute,
        data,
        isFetching: isSearching, // to notify of searching
        error: hasLoadError, // to notify of a search error
    } = useFetch(
        url,
        { headers: { Accept: "application/json" } },
        {
            immediate: false,
            onFetchError(ctx) {
                // we catch the Search error to display the error message
                if (ctx.data === null) {
                    searchError.value = {
                        title: "Unexpected response from server",
                        type: "Unexpected response from server",
                        status: 500,
                    } as SearchError;
                } else if ((ctx.data as SearchError).status) {
                    searchError.value = ctx.data as SearchError;
                }
                return ctx;
            },
        }
    )
        .get()
        .json();

    /**
     * search for nodes that match
     */
    async function search(simulateRedirect: boolean) {
        useQueryStore().setActive();
        useResultsStore().clear();
        useStore().setQueryParams(simulateRedirect);
        hasNotSearched.value = false;
        url.value = useStore().getEndpointWithParams();
        console.info("spSearchStore.ts search() url: ", url);
        await execute();
        try {
            useResultsStore().setFirstResults(data.value as Results);
        } catch (e) {
            hasLoadError.value = true;
        }
    }

    /**
     * clear the filters and search
     */
    async function clearFiltersAndSearch() {
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
    };
});
