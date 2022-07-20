/** 
 * Handles the store of the current Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './manageGroupMembersStore';
import Endpoints from '../manageGroupEndpoints';


export const useUpdateMembersStore = defineStore('manageGroupUpdateMembers', {

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
                const responseJson = await response.json();
                if (responseJson.success) {
                    const updatedMember = membersStore.groupMembers.find((groupMember) => groupMember.id === member.id);
                    updateMember(updatedMember, accessType);
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
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
                        member.accessType = accessType; //the selectedMembers data is unique to the groupMembers data 
                        const updatedMember = membersStore.groupMembers.find((groupMember) => groupMember.id === member.id);
                        updateMember(updatedMember, accessType);
                    });
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
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
    const membersToUpdateIdList = selectedMembers.map(row => row.id);
    return JSON.stringify({
        groupId: store.groupId,
        members: membersToUpdateIdList.join(','),
        accessType: accessType,
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