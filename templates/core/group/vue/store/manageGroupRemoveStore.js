/** 
 * Handles the store to remove members from the Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore'
import Endpoints from '../manageGroupEndpoints';


export const useRemoveStore = defineStore('manageGroupRemove', {
    
    state: () => ({
        isRemoveSelectedMembersPromptOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

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
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(Endpoints.REMOVE_MEMBERS, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(store.selectedMembers),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    store.groupMembers = store.groupMembers.filter((row) => { //pull out any removed members, those rows that are in selectedMembers, which have just been deleted
                        return !store.selectedMembers.some(rowToDelete => rowToDelete.id === row.id);
                    });
                    store.selectedMembers = []; //can't keep selecting members that have just been removed
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                this.isRemoveSelectedMembersPromptOpen = false;
                store.isProcessing = false;
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancelRemoveSelectedMembers () {
            this.isRemoveSelectedMembersPromptOpen = false;
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
function getFormData (selectedMembers) {
    const membersToRemoveIdList = selectedMembers.map(row => row.id);
    const formData = new FormData();
    membersToRemoveIdList.forEach((item) => {
        formData.append('users[]', item);
    });
    return formData;
}