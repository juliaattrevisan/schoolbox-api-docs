/**
 * Load more results, adding to the end of the existing results
 */

import { defineStore } from "pinia";
import { useStore } from "../../store/searchPageStore";
import { useQueryStore } from "../../store/spQueryStore";
import { useResultsStore } from "./spResultsStore";
import type { Results } from "../../../js/types/Results";

export const useLoadMoreResultsStore = defineStore("spLoadMoreResults", () => {
    const isLoading = window.Vue.ref(false); // to notify of loading
    const hasLoadError = window.Vue.ref(false); // to notify of a load error

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
     * load more results
     */
    async function loadMore() {
        isLoading.value = true;
        try {
            const response = await fetch(`${useStore().endpoint}?${getQueryParams()}`, {
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
