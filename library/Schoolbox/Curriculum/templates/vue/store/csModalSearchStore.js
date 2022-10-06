/**
 * Store the current search results when searcing from cs-modal-header-search-input
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from './curriculumSelectorStore';
import { useSelectedNodesStore } from './csSelectedNodesStore';
import { useModalActiveNodesStore } from './csModalActiveNodesStore';


const MIN_QUERY_LENGTH = 2; //the amount of characters typed before the search becomes active


export const useModalSearchStore = defineStore(`csModalSearch`, {

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
            const re = /\s*(?:,|$)\s*/ //adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
            const commaSeparated = queryTrimmed.split(re) ?? []; //split the string into a comma separated list
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
                const modalActiveNodesStore = useModalActiveNodesStore();
                const response = await fetch(`${apiEndpoint}?parentId=${modalActiveNodesStore.current.id}&query=${this.query}&enabled=1`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                this.results = await response.json();
                const selectedNodesStore = useSelectedNodesStore();
                this.results.forEach((node) => node.isSelected = selectedNodesStore.isSelected(node)); //ensure checked results are reflected
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
            const selectedNodesStore = useSelectedNodesStore();
            const newSelectedNodes = this.results.filter((newSelectedNode) => { //check if the node isn't in the list already
                return !selectedNodesStore.nodes.some((selectedNode) => selectedNode.id === newSelectedNode.id); //don't add in those that match
            });
            selectedNodesStore.add(newSelectedNodes);
        },

        /**
         * remove all found results from the selection
         */
        removeAllResults () {
            const selectedNodesStore = useSelectedNodesStore();
            const matchedSelectedNodes = this.results.filter((matchedSelectedNode) => { //check if the node is in the list already
                return selectedNodesStore.nodes.some((selectedNode) => selectedNode.id === matchedSelectedNode.id); //add in those that match
            });
            selectedNodesStore.remove(matchedSelectedNodes);
        },

    },

});