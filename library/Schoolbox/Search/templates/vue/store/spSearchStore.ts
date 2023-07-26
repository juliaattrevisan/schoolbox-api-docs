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

export const useSearchStore = defineStore("spSearch", () => {
    const hasNotSearched = window.Vue.ref(true); // to prompt a search if the user hasn't searched yet
    const url = window.Vue.ref("");
    const {
        execute,
        data: results,
        isFetching: isSearching, // to notify of searching
        error: hasLoadError, // to notify of a search error
    } = useFetch(url, { immediate: false }).get().json();

    /**
     * To record the active query and filters at the time of the search
     */
    function setActive() {
        useQueryStore().setActive();
        useFiltersStore().setActive();
    }

    /**
     * search for nodes that match
     */
    async function search() {
        setActive();
        hasNotSearched.value = false;
        url.value = useStore().getEndpointWithParams();
        await execute();
        useResultsStore().setFirstResults((results.value as Results).data);
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
        search,
        clear,
        clearFiltersAndSearch,
    };
});
