/**
 * Search for results that match the current query
 * Currently assumes that the query string is identified with the key "query"
 */

import debounce from "lodash/debounce";
import type { Ref } from "vue";
import type { SearchSelectItem } from "./types/SearchSelectItem";

const MIN_QUERY_LENGTH = 2; // the amount of characters typed before the search becomes active
export const DEBOUNCE_AMOUNT = 350; // the amount in ms to hold off on searching, to allow the user to keep typing

/**
 * search for results that match the current query
 * @param endpoint - the endpoint to search for results
 * @param results - a reference to the results list
 * @param onSuccess - a callback to run when the search is successful
 * @param requiresQuery - whether the search requires a query to be active
 * @returns isSearching, query, results, isActive, hasNoResults,  search
 */
export function useSearch(
    endpoint: string,
    results: Ref<SearchSelectItem[]>,
    onSuccess: (json: unknown) => void,
    requiresQuery: boolean = true,
): {
    isSearching: Ref<boolean>;
    query: Ref<string>;
    results: Ref<SearchSelectItem[]>;
    isActive: Ref<boolean>;
    hasNoResults: Ref<boolean>;
    hasError: Ref<boolean>;
    search: () => Promise<void>;
} {
    const isSearching = window.Vue.ref(false); // to notify that an action is taking place,
    const query = window.Vue.ref(""); // current search term being typed
    const isActive = window.Vue.computed(() => query.value.length >= MIN_QUERY_LENGTH); // To only start showing search results once enough characters have been typed
    const url = window.Vue.computed(() => {
        if (!isActive.value && !requiresQuery) {
            // so can search without a query
            return endpoint;
        }
        return `${endpoint}?query=${query.value}`;
    });
    const hasSearched = window.Vue.ref(false); // to show that a search has been made
    const hasNoResults = window.Vue.computed(() => isActive.value && results.value.length === 0 && !isSearching.value); // To show that no results match the current search
    const hasError = window.Vue.ref(false); // To show that an error has occurred

    /**
     * when the search isActive, we should display the loading prompt straight away,
     * but the debounce holds on calling search() until we assume the user has stopped typing
     */
    function enableSearch(): void {
        isSearching.value = true;
    }

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
     * debounce to avoid constant polling while the user is typing
     */
    const debounceSearch = debounce(async () => {
        await search();
    }, DEBOUNCE_AMOUNT);

    /**
     * clear the current search
     */
    function clear(): void {
        results.value = [];
        isSearching.value = false;
        hasSearched.value = false;
        hasError.value = false;
    }

    /**
     * start searching once the search is active,
     * watching the query string for input
     * also bring up the results without a query
     * if requiresQuery is false
     */
    window.Vue.watch(
        () => query.value,
        async () => {
            if (isActive.value) {
                enableSearch();
                await debounceSearch();
            } else if (!requiresQuery) {
                // bring up the default results if we don't need a query
                void search();
            } else if (hasSearched.value) {
                // clear the search if we move to an empty query
                clear();
            }
        },
    );

    return {
        isSearching,
        query,
        results,
        isActive,
        hasNoResults,
        hasError,
        search,
    };
}
