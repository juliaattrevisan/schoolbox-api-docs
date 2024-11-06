/**
 * Search for results at an endpoint
 */

import type { Ref } from "vue";
import type { SearchSelectItem } from "./types/SearchSelectItem";

/**
 * search for results that match the current query
 * @param endpoint - the endpoint to search for results
 * @param results - a reference to the results list
 * @param onSuccess - a callback to run when the search is successful
 * @returns isSearching, query, results, isActive, hasNoResults,  search
 */
export function useSearch(
    endpoint: string,
    results: Ref<SearchSelectItem[]>,
    onSuccess: (json: unknown) => void,
): {
    isSearching: Ref<boolean>;
    results: Ref<SearchSelectItem[]>;
    url: Ref<string>;
    hasNoResults: Ref<boolean>;
    hasError: Ref<boolean>;
    search: () => Promise<void>;
    clear: () => void;
} {
    const isSearching = window.Vue.ref(false); // to notify that an action is taking place,
    const url = window.Vue.computed(() => `${endpoint}`);
    const hasSearched = window.Vue.ref(false); // to show that a search has been made
    const hasNoResults = window.Vue.computed(() => results.value.length === 0 && !isSearching.value); // To show that no results match the current search
    const hasError = window.Vue.ref(false); // To show that an error has occurred

    /**
     * search for results that match the current query
     */
    async function search(): Promise<void> {
        try {
            hasSearched.value = true;
            hasError.value = false;
            const response = await fetch(url.value, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            });
            if (!response.ok) {
                hasError.value = true;
                return;
            }
            const json: unknown = await response.json();
            onSuccess(json);
        } catch (error) {
            console.error(error);
            hasError.value = true;
            results.value = [];
        } finally {
            isSearching.value = false;
        }
    }

    /**
     * clear the current search
     */
    function clear(): void {
        results.value = [];
        isSearching.value = false;
        hasSearched.value = false;
        hasError.value = false;
    }

    return {
        isSearching,
        results,
        url,
        hasNoResults,
        hasError,
        search,
        clear,
    };
}
