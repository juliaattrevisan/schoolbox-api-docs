/** 
 * Handles the store of the current Manage Group page
 *
 */

import { defineStore } from 'pinia';


export const useStore = defineStore('manageGroup', {
    
    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        groupMembers: [], //all the group members
        selectedMembers: [], //to track the current group member selection
    }),

    getters: {
       
        /**
         * When the table is ready and able to be displayed with group members
         * 
         * @returns { Boolean } 
         */
        hasGroupMembers () {
            return !this.isLoading && !this.hasLoadError && this.groupMembers.length > 0;
        },

        /**
         * When we've loaded the group members but there's none in the group yet
         * 
         * @returns { Boolean } 
         */
        hasNoGroupMembers () {
            return !this.isLoading && !this.hasLoadError && this.groupMembers.length === 0;
        },
        
    },

    actions: {

        /**
         * load all the group members for tabular display
         * 
         * @param { String }
         */
        async loadGroupMembers (endpoint) {
            try {
                const response = await fetch(endpoint);
                const responseJson = await response.json();
                this.groupMembers = responseJson;
                this.groupMembers.forEach((groupMember) => { //to highlight new members just added
                    groupMember = { ...groupMember, isNew: false };
                });
            } catch (error) {
                console.warn(error);
                this.hasLoadError = true; //show the load error display state 
            } finally {
                this.isLoading = false; //otherwise show the table
            }
        },

        /**
         * pass in the latest selected members
         * 
         * @param { Array }
         */
        updateSelectedMembers (selectedMembers) {
            this.selectedMembers = selectedMembers;
        },

        /**
         * clear the selected member selection
         */
        clearSelectedMembers () {
            this.selectedMembers = [];
        }

    },
    
})