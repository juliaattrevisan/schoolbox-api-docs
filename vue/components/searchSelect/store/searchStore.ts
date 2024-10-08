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
    const isLoading = window.Vue.ref(false); // to notify that an action is taking place,
    const query = window.Vue.ref(""); // current search term to filter the active nodes
    const results = window.Vue.ref<Result[]>([]); // nodes returned in the current search results
    const hasSearched = window.Vue.ref(false); // to show that a search has been made

    /**
     * To only start showing search results once enough characters
     * have been added
     * @returns { boolean }
     */
    const isActive = window.Vue.computed(() => query.value.length >= MIN_QUERY_LENGTH);

    /**
     * To show that no nodes match the current search
     * @returns { boolean }
     */
    const hasNoResults = window.Vue.computed(() => {
        return isActive.value && results.value.length === 0 && !isLoading.value;
    });

    /**
     * initialise the search store, just passing the endpoint
     * @param $endpoint - the endpoint to search for results
     */
    function init($endpoint: string): void {
        endpoint = $endpoint;
    }

    /**
     * when the search isActive, we should display the loading prompt straight away,
     * but the debounce holds on calling search() until we assume the user has stopped typing
     */
    function enableSearch(): void {
        isLoading.value = true;
    }

    /**
     * search for results that match the current query
     */
    async function search(): Promise<void> {
        try {
            hasSearched.value = true;
            const response = await fetch(`${endpoint}${query.value}`, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            });
            results.value = (await response.json()) as Result[];
        } catch (error) {
            console.error(error);
        } finally {
            isLoading.value = false;
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
        isLoading.value = false;
        hasSearched.value = false;
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
        isLoading,
        query,
        results,
        isActive,
        hasNoResults,
        init,
        enableSearch,
        search,
        clear,
    };
});
