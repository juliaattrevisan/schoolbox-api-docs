/**
 * Search for results that match the current query
 */

import debounce from "lodash/debounce";
import type { Ref } from "vue";
import type { SearchSelectItem } from "../types/searchSelectTypes";

const MIN_QUERY_LENGTH = 2; // the amount of characters typed before the search becomes active
export const DEBOUNCE_AMOUNT = 350; // the amount in ms to hold off on searching, to allow the user to keep typing

/**
 * search for results that match the current query
 * @param endpoint - the endpoint to search for results
 * @param $results - to optionally pass in a reference to the results list
 * @returns isSearching, query, results, isActive, hasNoResults,  search
 */
export function useSearch(
    endpoint: string,
    $results: Ref<SearchSelectItem[]> | undefined = undefined,
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
    const results = $results === undefined ? window.Vue.ref<SearchSelectItem[]>([]) : $results; // create a new results list if one is not passed in
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
            const response = await fetch(`${endpoint}${query.value}`, {
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
            // if json is an array, set results to json
            if (json instanceof Array) {
                results.value = json as SearchSelectItem[];
                return;
            }
            // if json.data exists, set results to json.data
            if (typeof json === "object" && json !== null && "data" in json) {
                results.value = (json as { data: SearchSelectItem[] }).data;
            }
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
        query.value = "";
        results.value = [];
        isSearching.value = false;
        hasSearched.value = false;
        hasError.value = false;
    }

    /**
     * start searching once the search is active, watching the query string for input
     */
    window.Vue.watch(
        () => query.value,
        async () => {
            if (isActive.value) {
                enableSearch();
                await debounceSearch();
            } else if (hasSearched.value) {
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
