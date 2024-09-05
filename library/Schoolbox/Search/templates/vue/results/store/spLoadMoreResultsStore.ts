/**
 * Load more results, adding to the end of the existing results.
 * There's currently no method to detect the total result count for a query,
 * so we just keep loading until the results do not exceed the limit or no results are returned.
 */

import { defineStore } from "pinia";
import { useStore } from "../../store/searchPageStore";
import { useResultsStore } from "./spResultsStore";
import type { Results } from "../../../js/types/Results";

export const useLoadMoreResultsStore = defineStore("spLoadMoreResults", () => {
    const url = window.Vue.ref("");
    const isLoading = window.Vue.ref(false);
    const hasLoadError = window.Vue.ref(false);

    /**
     * load more results
     */
    async function loadMore(): Promise<void> {
        const resultsStore = useResultsStore();
        url.value = `${useStore().getEndpointWithParams()}&cursor=${resultsStore.latestResults.metadata.cursor.next}`;
        try {
            isLoading.value = true;
            hasLoadError.value = false;
            const response = await fetch(url.value, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            });
            const moreResults = (await response.json()) as Results;
            if (moreResults.data.length === 0) {
                resultsStore.hasLoadedAllResults = true;
            } else {
                resultsStore.setMoreResults(moreResults);
            }
        } catch (error) {
            hasLoadError.value = true;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        isLoading,
        hasLoadError,
        loadMore,
    };
});
