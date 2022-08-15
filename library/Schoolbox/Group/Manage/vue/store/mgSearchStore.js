/**
 * To search for users to add 
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './mgMembersStore';
import Endpoints from '../mgEndpoints';


const MIN_QUERY_LENGTH = 3; //the amount of characters typed before the search becomes active
const CAPPED_RESULTS_LENGTH = 50; //the maximum amount of results to show

export const useSearchStore = defineStore('mgSearch', {

    state: () => ({
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        query: '', //current search query
        isResultsCapped: false, //to show a 'refine your search' note
        resultsNotShownCount: 0, //a total of the results not visible in the list
        results: [], //members returned in the current search results
        selection: [], //members to add on Add Members selection
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
         * Only if there's not already a process taking place and there's actually members to add
         * 
         * @returns { Boolean }
         */
        canAddMembers () {
            return this.selection.length > 0 && !this.isProcessing;
        },

    },

    actions: {

        /**
         * search for members
         * 
         * @returns { Array }
         */
        async searchForMembers () {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(`/ajax/user.php?groupID=0&query=${this.query}`, {
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
                this.isProcessing = true;
                store.isProcessing = false;
            }
        },

        /**
         * add a search result 
         * 
         * @param { Object } 
         */
        add (searchResult) {
            this.selection  = [...this.selection, searchResult];
        },

        /**
         * remove a search result 
         * 
         * @param { Object }
         */
        remove (searchResult) {
            this.selection = this.selection.filter((selectionItem) => selectionItem.id !== searchResult.id);
        },

        /**
         * clear all the currently selected search results
         */
        empty () {
            this.selection  = [];
        },

        /**
         * add all found results to the selection
         */
         addAllResults () {
            this.results.forEach((result) => {
                this.add(result);
            });
        },

        /**
         * remove all found results from the selection
         */
        removeAllResults () {
            this.empty()
        },

        /**
         * clear the current search
         */
        clear () {
            this.query = '';
        },


    },

})
