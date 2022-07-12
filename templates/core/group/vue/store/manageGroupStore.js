/** 
 * Handles the store of the current Manage Group page
 */

import { nextTick } from 'vue';
import { defineStore } from 'pinia';
import Endpoints from '../manageGroupEndpoints';
import AccessTypes from '../manageGroupAccessTypes';


export const useStore = defineStore('manageGroup', {
    
    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        groupMembers: [], //all the group members
        selectedMembers: [], //to track the current group member selection
        selectedFilters: [AccessTypes.WAITING, AccessTypes.READ, AccessTypes.WRITE, AccessTypes.ADMIN], //the list of table filters available
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
        async loadGroupMembers () {
            try {
                const response = await fetch(Endpoints.GET_MEMBERS);
                const responseJson = await response.json();
                const groupMembers = responseJson.map((groupMember) => { //add in some additional properties
                    return { 
                        ...groupMember,
                        displayPriority: groupMember.accessType === 'waiting' ? 1 : 0,
                        isNew: false, //to highlight new members just added 
                        justUpdated: false, //to highlight members that have just been changed
                    } 
                });
                this.groupMembers = groupMembers;
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
         * clear the current selected member selection
         */
        clearSelectedMembers () {
            this.selectedMembers = [];
        },

        /**
         * update the selected filters based on the latest selection change
         * 
         * @param { String }
         */
        async updateFilterSelection (accessType) {
            await this.refreshMembers();
            if (this.selectedFilters.includes(accessType)) { //pull the updated filter out of the list
                this.selectedFilters = this.selectedFilters.filter((filterType) => filterType !== accessType);
            } else { //otherwise add it back in
                this.selectedFilters = [...this.selectedFilters, accessType];
            }
        },

        /**
         * Perform on any further table interaction like updating filters, sorting or searching or 
         * when a members access is being changed 
         * Otherwise the animations used to clearly show where members have been added/updated
         * will be replayed on any table display change (like sorting)
         * Also, we don't want newly added or just approved members to go into the usual sort (displayPriority: 0) until 
         * the table display is changing, not when say a newly approved member has been changed from read to admin access
         * This is so members don't suddenly 'dissapear' when they are approved as this can be a bit confusing 
         * 
         * @param { Boolean }
         */
        async refreshMembers (isTableChange=false ) {
            await nextTick();
            this.groupMembers.forEach((member) => {
                member.isNew = false;
                member.justUpdated = false;
                if (isTableChange && member.displayPriority > 0 && member.accessType !== AccessTypes.WAITING) {
                    member.displayPriority = 0; //to only put the added/updated member in correct sort after a display table change: search, sort or pagination
                }
            });
        },

    },
    
});