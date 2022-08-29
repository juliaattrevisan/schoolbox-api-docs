/**
 * Handles the store of the current Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './mgMembersStore';
import Endpoints from '../mgEndpoints';


export const useUpdateMembersStore = defineStore('mgUpdateMembers', {

    state: () => ({
        isProcessing: false,
        isSelectedProcessing: false, //need to distinguish between processing a single member via updateMemberAccess() and updateSelectedMembersAccess()
    }),

    actions: {

        /**
         * update an individual members access
         *
         * @param { Object } the members data
         * @param { String } a FilterTypes
         */
        async updateMemberAccess (member, accessType) {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            const membersStore = useMembersStore();
            membersStore.refreshMembers(); //to set any previously isNew or justUpdated to false
            try {
                const response = await fetch(Endpoints.UPDATE_MEMBERS, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData([member], accessType)
                });
                const updatedMember = membersStore.members.find((groupMember) => groupMember.id === member.id);
                const responseJson = await response.json();
                if (responseJson.success) {
                    updateMember(updatedMember, accessType);
                } else {
                    updateMember(updatedMember, updatedMember.accessType); //we revert back to original accessType if the change has failed
                    throw new Error(responseJson.title, { ...responseJson } );
                }
            } catch (error) {
                throw new Error(error.message, error.options);
            } finally {
                this.isProcessing = false;
                store.isProcessing = false;
            }
        },

        /**
         * update the selected members access type
         *
         * @param { String } a FilterTypes
         */
        async updateSelectedMembersAccess (accessType) {
            this.isSelectedProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            const membersStore = useMembersStore();
            membersStore.refreshMembers(); //to set any previously isNew or justUpdated to false
            try {
                const response = await fetch(Endpoints.UPDATE_MEMBERS, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(membersStore.selectedMembers, accessType)
                });
                const responseJson = await response.json();
                if (responseJson.success) { //update the state only after the DB change is a success
                    membersStore.selectedMembers.forEach((member) => {
                        member.accessType = accessType; //the selectedMembers data is unique to the members data
                        const updatedMember = membersStore.members.find((groupMember) => groupMember.id === member.id);
                        updateMember(updatedMember, accessType);
                    });
                } else {
                    throw new Error(responseJson.title, { ...responseJson } );
                }
            } catch (error) {
                throw new Error(error.message, error.options);
            } finally {
                this.isSelectedProcessing = false;
                store.isProcessing = false;
            }
        },

    },

})

/**
 * Set up a deleted users array to POST
 *
 * @param { Array }
 * @param { String }
 *
 * @returns { FormData }
 */
function getFormData (selectedMembers, accessType) {
    const store = useStore();
    const membersToUpdateIdList = selectedMembers.map(row => ({userId: row.id, accessType: accessType}));
    return JSON.stringify({
        groupId: store.id,
        accessTypes: membersToUpdateIdList,
    });
}

/**
 * Set the new data on the just updated member
 *
 * @param { Object }
 * @param { String }
 */
function updateMember (member, accessType) {
    member.accessType = accessType; //only once the change has occured in the database, do we reflect this change in the display table
    member.isNew = false; //no longer considered new once updated
    member.justUpdated = true; //to add a class in the display table to help show the member has just been updated
}
