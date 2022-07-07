/** 
 * Handles the store of the current Manage Group page
 */

import { defineStore } from 'pinia';
import Endpoints from './manageGroupEndpoints';
import FilterTypes from './manageGroupFilterTypes';


export const useStore = defineStore('manageGroup', {
    
    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        groupMembers: [], //all the group members
        selectedMembers: [], //to track the current group member selection
        selectedFilters: [FilterTypes.WAITING, FilterTypes.READ, FilterTypes.WRITE, FilterTypes.ADMIN], //the list of table filters available
        isRemoveSelectedMembersPromptOpen: false, //to notify that the prompt is opened / closed
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
        updateFilterSelection (accessType) {
            if (this.selectedFilters.includes(accessType)) { //pull the updated filter out of the list
                this.selectedFilters = this.selectedFilters.filter((filterType) => filterType !== accessType);
            } else { //otherwise add it back in
                this.selectedFilters = [...this.selectedFilters, accessType];
            }
        },

        /**
         * to display a prompt to confirm member removal
         */
        promptRemoveSelectedMembers () {
            this.isRemoveSelectedMembersPromptOpen = true;
        },

        /**
         * member removal has been confirmed so pull them out
         * 
         * @param { String }
         */
        async removeSelectedMembers () {
            try {
                const response = await fetch(Endpoints.REMOVE_MEMBERS, {
                    method: 'POST',
                    body: getRemoveMembersFormData(this.selectedMembers),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    this.groupMembers = this.groupMembers.filter((row) => { //pull out any deleted members, those rows that are in selectedMembers, which have just been deleted
                        return !this.selectedMembers.some(rowToDelete => rowToDelete.id === row.id);
                    });
                    this.selectedMembers = [];
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                this.isRemoveSelectedMembersPromptOpen = false;
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancelRemoveSelectedMembers () {
            this.isRemoveSelectedMembersPromptOpen = false;
        },

        /**
         * 
         */
         async updateMemberAccessType (member, accessType) {
            try {
                const response = await fetch(Endpoints.UPDATE_MEMBERS, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        'id': member.id,
                        'access': accessType,
                    }),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    return member; //return member so the table data can be updated in manage-group-table.vue
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                // 
            }
        },

    },
    
})


/**
 * Set up a deleted users array to POST
 *
 * @param { Array }
 * 
 * @returns { FormData }
 */
function getRemoveMembersFormData (selectedMembers) {
    const membersToRemoveIdList = selectedMembers.map(row => row.id);
    const formData = new FormData();
    membersToRemoveIdList.forEach((item) => {
        formData.append('users[]', item);
    });
    return formData;
}