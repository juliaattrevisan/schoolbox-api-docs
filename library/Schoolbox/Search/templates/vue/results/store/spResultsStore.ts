/**
 * Store the current search results when searching from sp-input
 */

import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useStore } from "../../store/searchPageStore";
// import type { Node } from "@schoolboxCurriculum/types/Node";

const MIN_QUERY_LENGTH = 2; // the amount of characters typed before the search becomes active

export const useResultsStore = defineStore("spResults", () => {
    const hasSearched = ref(false); // to determine whether any searches have been made
    const isLoading = ref(false); // to notify that search results
    const hasLoadError = ref(false); // to notify of a search error
    const query = ref(""); // current search term
    const results = ref<Node[]>([]); // returned search results

    /**
     * To only start showing search results once enough characters
     * have been added
     * @returns {boolean}
     */
    const isActive = computed(() => query.value.length >= MIN_QUERY_LENGTH);

    /**
     * the search string broken into words, to search for each word
     * @returns {string[]} the search string broken into single words
     */
    const queryWordList = computed(() => {
        const queryTrimmed = query.value.trim(); // remove any front or back white space
        const regularExpression = /\s*(?:,|$)\s*/; // adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
        const commaSeparated = queryTrimmed.split(regularExpression) ?? []; // split the string into a comma separated list
        return commaSeparated.filter((word) => word.length > 0); // and ensure each contains a character
    });

    /**
     * To show that no results match the current search
     * @returns {boolean}
     */
    const hasNoResults = computed(() => {
        return hasSearched.value && results.value.length === 0 && !isLoading.value;
    });

    /**
     * search for nodes that match
     */
    async function search() {
        isLoading.value = true;
        try {
            const response = await fetch(useStore().endpoint, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                },
            });
            results.value = (await response.json()) as Node[];
        } catch (error) {
            hasLoadError.value = true;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * clear the current search
     */
    function clear() {
        query.value = "";
        results.value = [];
        isLoading.value = false;
    }

    return {
        isLoading,
        query,
        results,
        isActive,
        queryWordList,
        hasNoResults,
        search,
        clear,
    };
});
