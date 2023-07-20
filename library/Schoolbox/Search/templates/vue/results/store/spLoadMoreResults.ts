/**
 * Load more results, adding to the end of the existing results
 */

import { defineStore } from "pinia";
import { useStore } from "../../store/searchPageStore";
import { useResultsStore } from "./spResultsStore";
import type { Results } from "../../../js/types/Results";

export const useLoadMoreResultsStore = defineStore("spLoadMoreResults", () => {
    const isLoading = window.Vue.ref(false); // to notify of loading
    const hasLoadError = window.Vue.ref(false); // to notify of a load error

    /**
     * load more results
     */
    async function loadMore() {
        isLoading.value = true;
        try {
            const response = await fetch(useStore().getEndpointWithParams(), {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            });
            const resultsData = (await response.json()) as Results;
            if (resultsData.data.length === 0) {
                useResultsStore().hasLoadedAllResults = true;
            } else {
                useResultsStore().setMoreResults(resultsData.data);
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
