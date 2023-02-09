/**
 * Handles the store to remove members from the Manage Group page
 */

import { defineStore } from "pinia";
import { useStore } from "./manageGroupStore";
import { useMembersStore } from "./mgMembersStore";
import { useSearchStore } from "./mgSearchStore";
import { useSettingsStore } from "./mgSettingsStore";
import Endpoints from "../mgEndpoints";
import FromTypes from "../addMembers/mgAddMembersFromTypes";

let latestDisplayPriority = 2; //to increment on every new members add, so the most recently added are always displayed at top of the table

export const useAddMembersStore = defineStore("mgAddMembers", {
    state: () => ({
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        fromType: FromTypes.NAME, //the current way to search for members to add
    }),

    actions: {
        /**
         * add the members to the group
         */
        async add() {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            const membersStore = useMembersStore();
            membersStore.refreshMembers(); //to set any previously isNew or justUpdated to false
            // refresh whole page after adding users, if current folder is not a group
            const reload = !useSettingsStore().isAccessSaved;
            try {
                const response = await fetch(Endpoints.ADD_MEMBERS, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: getFormData(),
                });
                const responseJson = await response.json();
                if (response.ok && reload) {
                    location.reload();
                } else if (response.ok) {
                    const newMembers = responseJson.newMembers.map((newMember) => {
                        //add in some additional properties
                        return {
                            ...newMember,
                            displayPriority: latestDisplayPriority, //to show at the very top of the display table, 1 is used for those on the waiting list, and all others below
                            isNew: true, //to highlight new members just added
                            justUpdated: false, //to highlight members that have just been changed
                        };
                    });
                    latestDisplayPriority++; //increment so newest members are always on top
                    useSearchStore().clearSelectedResults(); //the members have been added so clear the selection
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
});

/**
 * Pass in user ids of the members to add
 *
 * @returns { FormData }
 */
function getFormData() {
    const store = useStore();
    const searchStore = useSearchStore();
    const members = searchStore.selectedResults.map((selectedResult) => {
        return {
            id: selectedResult.id,
            fromType: selectedResult.fromType,
        };
    });
    return JSON.stringify({
        groupId: store.id,
        members: members,
    });
}
