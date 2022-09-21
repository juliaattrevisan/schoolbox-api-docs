/**
 * Handles the store to remove members from the Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './mgMembersStore';
import { useSearchStore } from './mgSearchStore';
import Endpoints from '../mgEndpoints';
import FromTypes from '../addMembers/mgAddMembersFromTypes';


export const useAddMembersStore = defineStore('mgAddMembers', {

    state: () => ({
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        fromType: FromTypes.NAME, //the current way to search for members to add
    }),

    actions: {

        /**
         * add the members to the group
         */
        async add () {
            this.isProcessing = true;
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
                if (response.ok) {
                    const newMembers = responseJson.newMembers.map((newMember) => { //add in some additional properties
                        return {
                            ...newMember,
                            displayPriority: 2, //to show at the very top of the display table
                            isNew: true, //to highlight new members just added
                            justUpdated: false, //to highlight members that have just been changed
                        }
                    });
                    useSearchStore().clearSelectedResults(); //the members have been added so clear the selection
                    const membersStore = useMembersStore();
                    membersStore.members = [...membersStore.members, ...newMembers]; //add in the new members to the table
                } else {
                    throw new Error();
                }
            } catch (error) {
                throw new Error(error);
            } finally {
                this.isProcessing = false;
                store.isProcessing = false;
            }
        },

    },

})

/**
 * Pass in user ids of the members to add
 *
 * @returns { FormData }
 */
function getFormData () {
    const store = useStore();
    const searchStore = useSearchStore();
    const members = searchStore.selectedResults.map((selectedResult) => {
        return {
            id: selectedResult.id,
            fromType: selectedResult.fromType
        }
    });
    return JSON.stringify({
        groupId: store.id,
        members: members,
    });
}
