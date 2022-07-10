/** 
 * Handles the store of the current Manage Group page
 */

import { nextTick } from 'vue';
import { defineStore } from 'pinia';
import Endpoints from '../manageGroupEndpoints';
import FilterTypes from '../manageGroupAccessTypes';


export const useStore = defineStore('manageGroup', {
    
    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        groupMembers: [], //all the group members
        selectedMembers: [], //to track the current group member selection
        selectedFilters: [FilterTypes.WAITING, FilterTypes.READ, FilterTypes.WRITE, FilterTypes.ADMIN], //the list of table filters available
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
         * Perform on any further user interaction, like updating filters, sorting or searching
         * Otherwise the animation used to clearly show where new files have been added will be
         * replayed on any state change
         */
        async refreshMembers () {
            await nextTick();
            this.groupMembers.forEach((member) => {
                member.isNew = false;
                if (member.justUpdated) {
                    member.displayPriority = 0; //to only put the updated member in its correct sort after a display table change, like a sort or filter
                }
                member.justUpdated = false;
            });
        },

    },
    
});