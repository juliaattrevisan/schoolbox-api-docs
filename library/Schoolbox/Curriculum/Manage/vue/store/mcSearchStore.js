/**
 * Store the current search results when searcing from cs-modal-header-search-input
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from './manageCurriculumStore';
import { useSelectedNodesStore } from '../selectedNodes/store/mcSelectedNodesStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';


const MIN_QUERY_LENGTH = 3; //the amount of characters typed before the search becomes active


export const useSearchStore = defineStore(`mcSearch`, {

    state: () => ({
        isProcessing: false, //to notify that an action is taking place,
        query: '', //current search term to filter the active nodes
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
            const regularExpression = /\s*(?:,|$)\s*/ //adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
            const commaSeparated = queryTrimmed.split(regularExpression) ?? []; //split the string into a comma separated list
            return commaSeparated.filter(word => word.length > 0); //and ensure each contains a character
        },

        /**
         * To show that no nodes match the current search
         *
         * @return { Boolean }
         */
        hasNoResults () {
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
                const viewNodesStore = useViewNodesStore();
                const response = await fetch(`${apiEndpoint}?parentId=${viewNodesStore.current.id}&query=${this.query}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                this.results = await response.json();
                // const selectedNodesStore = useSelectedNodesStore();
                // this.results.forEach((node) => node.isSelected = selectedNodesStore.isSelected(node)); //ensure selected results are reflected in the search results
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

    },

});