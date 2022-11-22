/**
 * Store the current search results when searcing from mc-view-header-search-input
 */

import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { apiEndpoint } from './manageCurriculumStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';


const MIN_QUERY_LENGTH = 2; //the amount of characters typed before the search becomes active
export const DEBOUNCE_AMOUNT = 350; //the amount in ms to hold off on searching, to allow the user to keep typing

export const useSearchStore = defineStore(`mcSearch`, () => {

    const isLoading = ref(false); // to notify that an action is taking place,
    const query = ref(''); // current search term to filter the active nodes
    const results = ref([]); // nodes returned in the current search results

    /**
     * To only start showing search results once enough characters
     * have been added
     *
     * @return { Boolean }
     */
    const isActive = computed(() => {
        return query.value.length >= MIN_QUERY_LENGTH;
    });

    /**
     * add any filters, should we be showing existing results?
     *
     * @return { array }
     */
    const filteredResults = computed(() => {
        if (isActive.value) {
            return results.value;
        } else {
            return [];
        }
    });

    /**
     * the search string broken into words, to search for each word
     *
     * @returns { Array } the search string broken into single words
     */
    const queryWordList = computed(() => {
        const queryTrimmed = query.value.trim(); //remove any front or back white space
        const regularExpression = /\s*(?:,|$)\s*/ //adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
        const commaSeparated = queryTrimmed.split(regularExpression) ?? []; //split the string into a comma separated list
        return commaSeparated.filter(word => word.length > 0); //and ensure each contains a character
    });

    /**
     * To show that no nodes match the current search
     *
     * @return { Boolean }
     */
    const hasNoResults = computed(() => {
        return isActive.value && filteredResults.value.length === 0 && !isLoading.value;
    });

    /**
     * to pass through as a url parameter when searching
     *
     * @return { string }
     */
    const enabled = computed(() => {
        return useViewNodesStore().showDisabled ? '' : '&enabled=1';
    });

    /**
     * when the search isActive, we should display the loading prompt straight away,
     * but the debounce holds on calling search() until we assume the user has stopped typing
     */
    function enableSearch () {
        isLoading.value = true;
    }

    /**
     * search for nodes that match
     */
    async function search () {
        try {
            const viewNodesStore = useViewNodesStore();
            const response = await fetch(`${apiEndpoint}?parentId=${viewNodesStore.current.id}&query=${query.value}&limit=50${enabled.value}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            results.value = await response.json();
        } catch (error) {
            console.warn(error);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * clear the current search
     */
    function clear () {
        query.value = '';
        results.value = [];
        isLoading.value = false;
    }

    return {
        isLoading,
        query,
        results,
        isActive,
        filteredResults,
        queryWordList,
        hasNoResults,
        enabled,
        enableSearch,
        search,
        clear
    }

});