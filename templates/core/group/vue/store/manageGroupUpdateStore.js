/** 
 * Handles the store of the current Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import Endpoints from '../manageGroupEndpoints';


export const useUpdateStore = defineStore('manageGroupUpdate', {

    actions: {

        /**
         * update an individual members access 
         * 
         * @param { Object } the members data
         * @param { String } a FilterTypes
         */
        async updateMemberAccess (member, accessType) {
            const store = useStore();
            store.isProcessing = true;
            await store.refreshMembers();
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
                    const updatedMember = store.groupMembers.find((groupMember) => groupMember.id === member.id);
                    updateMember(updatedMember, accessType);
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                store.isProcessing = false;
            }
        },

        /**
         * update the selected members access type
         * 
         * @param { Object } the members data
         * @param { String } a FilterTypes
         */
        async updateSelectedMembersAccess (accessType) {
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(Endpoints.UPDATE_MEMBERS, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(store.selectedMembers, accessType)
                });
                const responseJson = await response.json();
                if (responseJson.success) { //update the state only after the DB change is a success
                    store.selectedMembers.forEach((member) => {
                        member.accessType = accessType; //the selectedMembers data is unique to the groupMembers data 
                        const updatedMember = store.groupMembers.find((groupMember) => groupMember.id === member.id);
                        updateMember(updatedMember, accessType);
                    });
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
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
    const membersToUpdateIdList = selectedMembers.map(row => row.id);
    const formData = new FormData();
    membersToUpdateIdList.forEach((item) => {
        formData.append('users[]', item);
    });
    formData.append('access', accessType);
    return formData;
}

/**
 * Set the new data on the just updated member
 *
 * @param { Object }
 * @param { String }
 */
function updateMember (member, accessType) {
    member.accessType = accessType; //only once the change has occured in the database, do we reflect this change in the display table
    member.accessTypeCode = 0; //ensure the member doesn't always appear at the top of the table once they are off the waitlist
    member.justUpdated = true; //to add a class in the display table to help show the member has just been updated
}