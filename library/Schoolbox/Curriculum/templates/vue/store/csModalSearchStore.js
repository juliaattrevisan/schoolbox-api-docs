/**
 * Store the current search results when searcing from cs-search-input
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from './curriculumSelectorStore';
import { useSelectedCodesStore } from './csSelectedCodesStore';
import { useModalActiveCodesStore } from './csModalActiveCodesStore';


const MIN_QUERY_LENGTH = 3; //the amount of characters typed before the search becomes active
const CAPPED_RESULTS_LENGTH = 50; //the maximum amount of results to show

export const useModalSearchStore = defineStore('csModalSearch', {

    state: () => ({
        isProcessing: false, //to notify that an action is taking place,
        query: '', //current search term to filter the active codes
        isResultsCapped: false, //to show a 'refine your search' note
        resultsNotShownCount: 0, //a total of the results not visible in the list
        results: [], //nodes returned in the current search results
    }),

    getters: {

        /**
         * To only start showing search results once enough characters
         * have been added
         *
         * @return { Boolean }
         */
        isActive () {
            return this.query.length >= MIN_QUERY_LENGTH;
        },

        /**
         * add any filters, should we be showing existing results?
         *
         * @return { array }
         */
         filteredResults () {
            if (this.isActive) {
                return this.results;
            } else {
                return [];
            }
        },

        /**
         * the search string broken into words, to search for each word
         *
         * @returns { Array } the search string broken into single words
         */
        queryWordList () {
            const queryTrimmed = this.query.trim(); //remove any front or back white space
            const re = /\s*(?:,|$)\s*/ //adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
            const commaSeparated = queryTrimmed.split(re) ?? []; //split the string into a comma separated list
            return commaSeparated.filter(word => word.length > 0); //and ensure each contains a character
        },

        /**
         * To show that no codes match the current search
         *
         * @return { Boolean }
         */
        hasNoResults () {
            return this.filteredResults.length === 0 && !this.isProcessing;
        },

    },

    actions: {

        /**
         * search for nodes that match
         */
         async search () {
            this.isProcessing = true;
            try {
                const modalActiveCodesStore = useModalActiveCodesStore();
                const response = await fetch(`${apiEndpoint}?parentId=${modalActiveCodesStore.current.id}&query=${this.query}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                this.results = await response.json();
                const selectedCodesStore = useSelectedCodesStore();
                this.results.forEach((code) => code.isSelected = selectedCodesStore.isSelected(code)); //ensure checked results are reflected
            } catch (error) {
                console.warn(error);
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * clear the current search
         */
        clear () {
            this.query = '';
            this.results = [];
        },

        /**
         * add all found results to the selection
         */
        addAllResults () {
            const selectedCodesStore = useSelectedCodesStore();
            const newSelectedCodes = this.results.filter((newSelectedCode) => { //check if the code isn't in the list already
                return !selectedCodesStore.codes.some((selectedCode) => selectedCode.id === newSelectedCode.id); //don't add in those that match
            });
            selectedCodesStore.add(newSelectedCodes);
        },

        /**
         * remove all found results from the selection
         */
        removeAllResults () {
            const selectedCodesStore = useSelectedCodesStore();
            const matchedSelectedCodes = this.results.filter((matchedSelectedCode) => { //check if the code is in the list already
                return selectedCodesStore.codes.some((selectedCode) => selectedCode.id === matchedSelectedCode.id); //add in those that match
            });
            selectedCodesStore.remove(matchedSelectedCodes);
        },

    },

});

/**
 * find all those results that match any search words
 * within the the code, name or breadcrumb fields
 *
 * @params { Object }
 * @params { Array }
 *
 * @return { Boolean } true if found
 */
export function searchForMatches (code, queryWordList) {
    let wordMatchCount = 0;
    queryWordList.forEach(wordInSearch => {
        if (code.code?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
        if (code.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
        if (code.breadcrumb?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
    });
    return wordMatchCount > 0 ? true : false; //if there's any matches
}