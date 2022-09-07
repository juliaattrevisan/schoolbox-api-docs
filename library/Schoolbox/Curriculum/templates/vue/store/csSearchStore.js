/**
 * Store the current search results when searcing from cs-search-input
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from './curriculumSelectorStore';
// import { useCodesStore } from './csCodesStore';
import { useSelectedCodesStore } from './csSelectedCodesStore';


const MIN_QUERY_LENGTH = 3; //the amount of characters typed before the search becomes active
const CAPPED_RESULTS_LENGTH = 50; //the maximum amount of results to show

export const useSearchStore = defineStore('csSearch', {

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
         * filter out any returned members that are already in the list
         *
         * @return { array }
         */
         filteredResults () {
            if (this.isActive) {
                return this.results;
            } else {
                return [];
            }
            // const addMembersStore = useAddMembersStore();
            // if (addMembersStore.fromType === FromTypes.NAME || addMembersStore.fromType === FromTypes.LIST) {
            //     const membersStore = useMembersStore();
            //     const memberIdList = membersStore.members.map((member) => member.id); //create an array of just the existing member ids
            //     return this.results.filter((result) => !memberIdList.includes(result.id)); //to only keep those search results that aren't already in the group
            // } else if (addMembersStore.fromType === FromTypes.GROUP) {
            //     const store = useStore();
            //     return this.results.filter((result) => result.id !== store.id); //don't return the group we are currently on in the search
            // } else {
            //     return this.results;
            // }
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
            // return this.filteredResults.length === 0 && !this.isProcessing;
            return this.isActive && this.filteredResults.length === 0 && !this.isProcessing;
        },

    },

    actions: {

        /**
         * search for nodes that match
         */
         async search () {
            this.isProcessing = true;
            try {
                // const viewNodesStore = useViewNodesStore();
                const response = await fetch(`${apiEndpoint}?query=${this.query}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                this.results = await response.json();
                // const selectedNodesStore = useSelectedNodesStore();
                // this.results.forEach((node) => node.isSelected = selectedNodesStore.isNodeSelected(node)); //ensure checked results are reflected
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