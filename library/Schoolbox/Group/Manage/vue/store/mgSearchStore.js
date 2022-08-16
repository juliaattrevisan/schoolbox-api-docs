/**
 * To search for users to add 
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useAddMembersStore } from './mgAddMembersStore';
import FromTypes from '../addMembers/mgAddMembersFromTypes';


const MIN_QUERY_LENGTH = 3; //the amount of characters typed before the search becomes active
const CAPPED_RESULTS_LENGTH = 50; //the maximum amount of results to show

export const useSearchStore = defineStore('mgSearch', {

    state: () => ({
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        query: '', //current search query
        isResultsCapped: false, //to show a 'refine your search' note
        resultsNotShownCount: 0, //a total of the results not visible in the list
        results: [], //members returned in the current search results
        selectedResults: [], //members to add on Add Members selectedResults
    }),

    getters: {

        /**
         * To only start showing search results once enough characters
         * have been added 
         * 
         * @return { Boolean }
         */
        isSearchActive () {
            return this.query.length >= MIN_QUERY_LENGTH;
        },

        /**
         * Changes depending on where we are searching for users
         * 
         * @return { String }
         */
        searchEndpoint () {
            const searchEndpoints = {
                [FromTypes.NAME]: '/ajax/user.php?groupID=0&query=',
                [FromTypes.GROUP]: '/ajax/addUserByGroup.php?type=individual&query=',
                [FromTypes.ROLE]: '/ajax/addUserByGroup.php?type=system&query=',
                [FromTypes.LIST]: '',
            }
            const addMembersStore = useAddMembersStore();
            return searchEndpoints[addMembersStore.fromType];
        },

        /**
         * If any results have been selected
         * 
         * @return { Boolean }
         */
        hasSelectedResults () {
            return this.selectedResults.length > 0;
        },

        /**
         * Only if there's not already a process taking place and there's actually members to add
         * 
         * @returns { Boolean }
         */
        canAddMembers () {
            return this.hasSelectedResults && !this.isProcessing;
        },

    },

    actions: {

        /**
         * search for members
         * 
         * @returns { Array }
         */
        async search () {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(`${this.searchEndpoint}${this.query}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                const responseJson = await response.json();
                this.results = responseJson;
            } catch (error) {
                console.warn(error);
            } finally {
                this.isProcessing = false;
                store.isProcessing = false;
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
         * add a search result 
         * 
         * @param { Object } 
         */
        addResult (searchResult) {
            const addMembersStore = useAddMembersStore();
            const searchResultWithFromType = {
                ...searchResult,
                fromType: addMembersStore.fromType,
            }
            this.selectedResults  = [...this.selectedResults, searchResultWithFromType];
        },

        /**
         * remove a search result 
         * 
         * @param { Object }
         */
        removeResult (searchResult) {
            this.selectedResults = this.selectedResults.filter((selectedResultsItem) => selectedResultsItem.id !== searchResult.id);
        },

        /**
         * add all found results to the selectedResults
         */
        addAllResults () {
            this.results.forEach((result) => {
                this.add(result);
            });
        },

        /**
         * remove all found results from the selectedResults
         */
        removeAllResults () {
            this.selectedResults  = [];
        },

    },

})
