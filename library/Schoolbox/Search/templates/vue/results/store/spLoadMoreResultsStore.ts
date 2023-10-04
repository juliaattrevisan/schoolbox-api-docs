/**
 * Load more results, adding to the end of the existing results.
 * There's currently no method to detect the total result count for a query,
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
        data,
        isFetching: isLoading,
        error: hasLoadError,
    } = useFetch(url, { immediate: false }).get().json();

    /**
     * load more results
     */
    async function loadMore() {
        const resultsStore = useResultsStore();
        url.value = `${useStore().getEndpointWithParams()}&cursor=${resultsStore.latestResults.metadata.cursor.next}`;
        await execute();
        // if no results are returned, we've loaded all results already
        const moreResults = data.value as Results;
        if (moreResults.data.length === 0) {
            resultsStore.hasLoadedAllResults = true;
        } else {
            resultsStore.setMoreResults(moreResults);
        }
    }

    return {
        isLoading,
        hasLoadError,
        loadMore,
    };
});
