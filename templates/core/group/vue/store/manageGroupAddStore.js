/** 
 * Handles the store to remove members from the Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore'
import Endpoints from '../manageGroupEndpoints';


export const useAddStore = defineStore('manageGroupAdd', {

    actions: {

        /**
         * member removal has been confirmed so pull them out
         */
        async addMembers () {
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(Endpoints.ADD_MEMBERS, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    const newMembers = responseJson.newMembers.map((newMember) => { //add in some additional properties
                        return { 
                            ...newMember,
                            displayPriority: 2, //to show at the very top of the display table
                            isNew: true, //to highlight new members just added 
                            justUpdated: false, //to highlight members that have just been changed
                        } 
                    });
                    store.groupMembers = [...store.groupMembers, ...newMembers]; //add in the new members to the table
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
 * 
 * @returns { FormData }
 */
function getFormData () {
    return JSON.stringify([
        {"userId":200,},
        {"userId":201,},
        {"userId":202,},
        {"userId":203,},
    ]);
    // const membersToRemoveIdList = selectedMembers.map(row => row.id);
    // const formData = new FormData();
    // membersToRemoveIdList.forEach((item) => {
    //     formData.append('users[]', item);
    // });
    // return formData;
}