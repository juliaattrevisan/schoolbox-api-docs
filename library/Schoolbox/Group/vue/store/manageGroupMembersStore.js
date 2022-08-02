/**
 * Handles the store of the current Manage Group page
 */

import { nextTick } from 'vue';
import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import AccessTypes from '../manageGroupAccessTypes';


export const useMembersStore = defineStore('manageGroupMembers', {

    state: () => ({
        groupMembers: [], //all the group members
        selectedMemberIds: [], //to track the current group member selection
        selectedFilters: [AccessTypes.WAITING, AccessTypes.READ, AccessTypes.WRITE, AccessTypes.ADMIN], //the list of table filters available
    }),

    getters: {

        /**
         * When the table is ready and able to be displayed with group members
         *
         * @returns { Boolean }
         */
        hasGroupMembers () {
            const store = useStore();
            return !store.isLoading && !store.hasLoadError && this.groupMembers.length > 0;
        },

        /**
         * When we've loaded the group members but there's none in the group yet
         *
         * @returns { Boolean }
         */
        hasNoGroupMembers () {
            const store = useStore();
            return !store.isLoading && !store.hasLoadError && this.groupMembers.length === 0;
        },

        /**
         * To display an alert note there's currently no group admins
         *
         * @returns { Boolean }
         */
        hasNoGroupAdmins () {
            return this.groupMembers.filter((member) => member.accessType === AccessTypes.ADMIN).length === 0
                    && this.hasGroupMembers;
        },

        /**
         * Retrieve the selected members based on the selectedMemberIds
         * We do this so we are referencing the groupMembers directly,
         * so any data changes are kept on groupMembers, not a copy if we created
         * a seperate selectedMembers array
         *
         * @returns { Array }
         */
        selectedMembers () {
            return this.groupMembers.filter((member) => this.selectedMemberIds.includes(member.id));
        },
    },

    actions: {

        /**
         * load all the group members for tabular display
         *
         * @param { Object }
         */
        loadMembers (members) {
            const groupMembers = members.map((groupMember) => { //add in some additional properties
                return {
                    ...groupMember,
                    displayPriority: groupMember.accessType === 'none' ? 1 : 0, //waiting list members are put at the top of the table
                    isNew: false, //to highlight new members just added
                    justUpdated: false, //to highlight members that have just been changed
                }
            });
            this.groupMembers = groupMembers;
        },

        /**
         * pass in the latest selected members
         *
         * @param { Array }
         */
        updateSelectedMembers (selectedMembers) {
            this.selectedMemberIds = selectedMembers.map((member) => member.id);
        },

        /**
         * pull out a specific member
         *
         * @param { Object }
         */
        removeMember (removedMember) {
            this.groupMembers = this.groupMembers.filter((member) => member.id !== removedMember.id);
            this.selectedMemberIds = this.selectedMemberIds.filter((memberId) => memberId !== removedMember.id);; //ensure the member wasn't also a selected member
        },

        /**
         * pull out any removed/rejected members, those rows that are in selectedMembers,
         * which have just been deleted or rejected
         */
        removeSelectedGroupMembers () {
            this.groupMembers = this.groupMembers.filter((member) => !this.selectedMemberIds.includes(member.id));
            this.clearSelectedMembers(); //can't keep selecting members that have just been removed/rejected
        },

        /**
         * clear the current selected member selection
         */
        clearSelectedMembers () {
            this.selectedMemberIds = [];
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
