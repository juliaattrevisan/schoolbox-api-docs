/**
 * Load more results, adding to the end of the existing results.
 * There's currently no method to detect the total query result for a query,
 * so we just keep loading until the results do not exceed the limit or no results are returned.
 */

import { defineStore } from "pinia";
import { useFetch } from "@vueuse/core";
import { useStore } from "../../store/searchPageStore";
import { useResultsStore } from "./spResultsStore";
import type { Results } from "../../../js/types/Results";

export const useLoadMoreResultsStore = defineStore("spLoadMoreResults", () => {
    const url = window.Vue.ref("");
    const {
        execute,
        data: moreResults,
        isFetching: isLoading,
        error: hasLoadError,
    } = useFetch(url, { immediate: false }).get().json();

    /**
     * load more results
     */
    async function loadMore() {
        url.value = useStore().getEndpointWithParams();
        await execute();
        // if no results are returned, we've loaded all results already
        if ((moreResults.value as Results).data.length === 0) {
            useResultsStore().hasLoadedAllResults = true;
        } else {
            useResultsStore().setMoreResults((moreResults.value as Results).data);
        }
    }

    return {
        isLoading,
        hasLoadError,
        loadMore,
    };
});
