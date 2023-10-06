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
    } = useFetch(url, {
        immediate: false,
        onFetchError(ctx) {
            // we catch the Search error to display the error message
            if ((ctx.data as SearchError).status) {
                searchError.value = ctx.data as SearchError;
            }
            return ctx;
        },
    })
        .get()
        .json();

    /**
     * To record the active query and filters at the time of the search
     */
    function setActive() {
        useQueryStore().setActive();
    }

    /**
     * search for nodes that match
     */
    async function search() {
        setActive();
        useResultsStore().clear();
        hasNotSearched.value = false;
        url.value = useStore().getEndpointWithParams();
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
        await search();
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
