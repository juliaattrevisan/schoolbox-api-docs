/** 
 * Handles the store to remove members from the Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './manageGroupMembersStore';
import Endpoints from '../manageGroupEndpoints';


export const useRemoveMembersStore = defineStore('manageGroupRemoveMembers', {
    
    state: () => ({
        isPromptOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm member removal
         */
        promptRemoveSelectedMembers () {
            this.isPromptOpen = true;
        },

        /**
         * member removal has been confirmed so pull them out
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
                    body: getFormData(),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    const membersStore = useMembersStore();
                    membersStore.groupMembers = membersStore.groupMembers.filter((row) => { //pull out any removed members, those rows that are in selectedMembers, which have just been deleted
                        return !membersStore.selectedMembers.some(rowToDelete => rowToDelete.id === row.id);
                    });
                    membersStore.selectedMembers = []; //can't keep selecting members that have just been removed
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                this.isPromptOpen = false;
                store.isProcessing = false;
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancelRemoveSelectedMembers () {
            this.isPromptOpen = false;
        },

    },
    
})

/**
 * Set up a deleted users array to POST
 *
 * @returns { FormData }
 */
function getFormData () {
    const membersStore = useMembersStore();
    const membersToRemoveIdList = membersStore.selectedMembers.map(row => row.id);
    return JSON.stringify([
        { mmebers: membersToRemoveIdList.join(',') },
    ]);
}