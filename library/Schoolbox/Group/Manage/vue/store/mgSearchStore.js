/**
 * To search for users to add
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './mgMembersStore';
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
        results: [], //members/groups/roles returned in the current search results
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
                [FromTypes.GROUP]: '/group/searchGroups?query=',
                [FromTypes.ROLE]: '/group/searchRoles?query=',
                [FromTypes.LIST]: '/group/searchUsers?query=',
            }
            const addMembersStore = useAddMembersStore();
            return searchEndpoints[addMembersStore.fromType];
        },

        /**
         * filter out any returned members that are already in the list
         *
         * @return { array }
         */
        filteredResults () {
            const addMembersStore = useAddMembersStore();
            if (addMembersStore.fromType === FromTypes.NAME || addMembersStore.fromType === FromTypes.LIST) {
                const membersStore = useMembersStore();
                const memberIdList = membersStore.members.map((member) => member.id); //create an array of just the existing member ids
                return this.results.filter((result) => !memberIdList.includes(result.id)); //to only keep those search results that aren't already in the group
            } else if (addMembersStore.fromType === FromTypes.GROUP) {
                const store = useStore();
                return this.results.filter((result) => result.id !== store.id); //don't return the group we are currently on in the search
            } else {
                return this.results;
            }
        },

        /**
         * there's no results to show currently
         *
         * @return { Boolean }
         */
        hasNoResults () {
            return this.isSearchActive && this.filteredResults.length === 0 && !this.isProcessing;
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
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                this.results = await response.json();
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
         * clear the currently selected results
         */
        clearSelectedResults () {
            this.selectedResults = [];
        },

        /**
         * add all found results to the selectedResults
         */
        addAllResults () {
            this.filteredResults.forEach((result) => {
                this.addResult(result);
            });
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
                // list returns search result in the same format as name
                fromType: addMembersStore.fromType === 'list' ? 'name' : addMembersStore.fromType,
            }
            const hasMatch = this.selectedResults.some((result) => { //confirm that the member is not in the list already
                return result.id === searchResult.id && result.fromType === searchResultWithFromType.fromType;
            });
            if (!hasMatch) {
                this.selectedResults = [...this.selectedResults, searchResultWithFromType];
            }
        },

        /**
         * remove all found results from the selectedResults
         */
        removeAllResults () {
            this.filteredResults.forEach((result) => {
                this.removeResult(result);
            });
        },

        /**
         * remove a search result
         *
         * @param { Object }
         */
        removeResult (searchResult) {
            this.selectedResults = this.selectedResults.filter((selectedResultsItem) => selectedResultsItem.id !== searchResult.id);
        },

    },

})
