/**
 * Store the current search results when searcing from cs-search-input
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './csCodesStore';
import { useSelectedCodesStore } from './csSelectedCodesStore';
import cloneDeep from 'lodash/cloneDeep';

const MIN_QUERY_LENGTH = 3; //the amount of characters typed before the search becomes active
const CAPPED_RESULTS_LENGTH = 50; //the maximum amount of results to show

export const useSearchResultsStore = defineStore('csSearchResultsStore', {

    state: () => ({
        query: '', //current search term to filter the active codes
        isResultsCapped: false, //to show a 'refine your search' note
        resultsNotShownCount: 0, //a total of the results not visible in the list
    }),

    getters: {
        
        /**
         * To only start showing search results once enough characters
         * have been added 
         * 
         * @return { Boolean }
         */
        isSearchActive () {
            return this.query.length > MIN_QUERY_LENGTH;
        },

        /**
         * To search the curriculum codes
         * 
         * @return { Array }
         */
        codes () {
            return useCodesStore().codes;
        },

        /**
         * no children descendents, all put in an array one level deep
         * for search results
         * 
         * @returns { Array } 
         */
        codesFlattened () {
            if (this.isSearchActive) {
                let flatChildren = [];
                const codesClone = cloneDeep(this.codes); //cloneDeep is from lodash.js
                codesClone.forEach((obj) => flatChildren = [...flatChildren, ...flattenCodes(obj)]);
                return flatChildren;
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
         * When the search is active, will return any codes
         * that match the current search query
         * 
         * @return { Array }
         */
        results () {
            const fullResults = this.codesFlattened.filter(this.searchForMatches);
            if (fullResults.length > CAPPED_RESULTS_LENGTH) { //too many results, so cap them
                this.isResultsCapped = true; //to display the results are capped note
                this.resultsNotShownCount = fullResults.length - CAPPED_RESULTS_LENGTH; //and how many are actually capped
                return fullResults.slice(0, CAPPED_RESULTS_LENGTH);
            } else { //otherwise just return them all
                this.isResultsCapped = false; 
                return fullResults;
            }
        },

        /**
         * To show that no codes march the current search
         * 
         * @return { Boolean }
         */
        hasNoResults () {
            return this.results.length === 0 && this.isSearchActive;
        },

    },
   
    actions: {
    
        /**
         * find all those results that match any search words
         * within the the code, name or breadcrumb fields
         *
         * @params { Object }
         *
         * @return { Boolean } true if found
         */
        searchForMatches (code) {
            let wordMatchCount = 0;
            this.queryWordList.forEach(wordInSearch => {
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

        /**
         * clear the current search
         */
        clear () {
            this.query = '';
        },

    },
   
});

/**
 * The codes are nested with children:[], so we pull them all
 * out into a single level when searching 
 * 
 * @param { Object }
 */
function flattenCodes (obj) {
    let array = [obj];
    if (obj.children && obj.children.length > 0) {
        obj.children.forEach((child) => {
            array = [...array, ...flattenCodes(child)];
        });
        obj.children = []; 
    }
    return array;
}