/**
 * Handles the store to remove members from the Manage Group page
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore';
import { useMembersStore } from './mgMembersStore';
import Endpoints from '../mgEndpoints';


const MIN_QUERY_LENGTH = 3; //the amount of characters typed before the search becomes active
const CAPPED_RESULTS_LENGTH = 50; //the maximum amount of results to show

export const useAddMembersStore = defineStore('mgAddMembers', {

    state: () => ({
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        query: '', //current search query
        isResultsCapped: false, //to show a 'refine your search' note
        resultsNotShownCount: 0, //a total of the results not visible in the list
        searchResults: [], //members returned in the current search results
        selectedMembers: [], //members to add on Add Members selection
    }),

    getters: {

        /**
         * To only start showing search results once enough characters
         * have been added 
         * 
         * @return { Boolean }
         */
        isSearchActive () {
            return this.query.length >= MIN_QUERY_LENGTH;
        },

        /**
         * Only if there's not already a process taking place and there's actually members to add
         * 
         * @returns { Boolean }
         */
        canAddMembers () {
            return this.selectedMembers.length > 0 && !this.isProcessing;
        },

    },

    actions: {

        /**
         * search for members
         */
        async searchForMembers () {

        },

        /**
         * clear the current search
         */
        clear () {
            this.query = '';
        },

        /**
         * add the members to the group
         */
        async addMembers () {
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
                if (responseJson.success) {
                    // const newMembers = responseJson.newMembers.map((newMember) => { //add in some additional properties
                    //     return {
                    //         ...newMember,
                    //         displayPriority: 2, //to show at the very top of the display table
                    //         isNew: true, //to highlight new members just added
                    //         justUpdated: false, //to highlight members that have just been changed
                    //     }
                    // });
                    const membersStore = useMembersStore();
                    membersStore.groupMembers = [...membersStore.groupMembers, ...newMembers]; //add in the new members to the table
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                this.isProcessing = true;
                store.isProcessing = false;
            }
        },

    },

})

/**
 * Pass in user ids of the members to add
 *
 * @param { Array }
 *
 * @returns { FormData }
 */
function getFormData () {
    const store = useStore();
    return JSON.stringify({
        groupId: store.id,
        members: [60, 64, 46]
    });
}
