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
        isProcessing: false,
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
            this.isProcessing = true;
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
                    membersStore.removeSelectedGroupMembers();
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                this.isProcessing = false;
                store.isProcessing = false;
                this.isPromptOpen = false;
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
    const store = useStore();
    const membersStore = useMembersStore();
    const membersToRemoveIdList = membersStore.selectedMembers.map(row => row.id);
    return JSON.stringify(
        {
            groupId: store.groupId,
            members: membersToRemoveIdList
        },
    );
}
