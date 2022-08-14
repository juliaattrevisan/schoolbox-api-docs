/**
 * Handles the store to remove members from the Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './manageGroupMembersStore';
import Endpoints from '../manageGroupEndpoints';


export const useRejectMembersStore = defineStore('manageGroupRejectMembers', {

    state: () => ({
        isProcessing: false,
        isSelectedProcessing: false, //need to distinguish between processing a single member via rejectMember() and rejectSelectedMembers()
    }),

    actions: {

        /**
         * reject the single member
         *
         * @param { Object }
         */
        async rejectMember (member) {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(Endpoints.REMOVE_MEMBERS, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        groupId: store.id,
                        members: [member.id],
                    }),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    const membersStore = useMembersStore();
                    membersStore.removeMember(member);
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
         * reject the selected members
         */
        async rejectSelectedMembers () {
            this.isSelectedProcessing = true;
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
                this.isSelectedProcessing = false;
                store.isProcessing = false;
            }
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
    const membersToRejectIdList = membersStore.selectedMembers.map(row => row.id);
    return JSON.stringify({
        groupId: store.id,
        members: membersToRejectIdList
    });
}
