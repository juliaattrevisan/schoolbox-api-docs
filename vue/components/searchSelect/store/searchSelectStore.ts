/**
 * Store the current search results when searching from search-select-input
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import debounce from "lodash/debounce";
import { defineStore } from "pinia";
import type { Result } from "../types/Result";

const MIN_QUERY_LENGTH = 2; // the amount of characters typed before the search becomes active
export const DEBOUNCE_AMOUNT = 350; // the amount in ms to hold off on searching, to allow the user to keep typing

export const useSearchStore = defineStore(`searchStore`, () => {
    let endpoint = "";
    let isSingleSelect = false;

    /**
     * initialise the search store, just passing the endpoint
     * @param $endpoint - the endpoint to search for results
     * @param $selectedResults - the selected results
     * @param $defaultResults - the default results to display
     * @param $isSingleSelect - whether the search is for a single select
     */
    function init(
        $endpoint: string,
        $selectedResults: Result[],
        $defaultResults: Result[],
        $isSingleSelect: boolean,
    ): void {
        endpoint = $endpoint;
        selectedResults.value = $selectedResults;
        defaultResults.value = $defaultResults;
        isSingleSelect = $isSingleSelect;
    }

    return {
        isLoading,
        query,
        currentResults,
        isActive,
        hasNoResults,
        init,
        enableSearch,
        search,
        clear,
    };
});
