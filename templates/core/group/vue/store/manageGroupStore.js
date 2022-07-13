/** 
 * Handles the load of the current Manage Group page and
 * sends off the data to settings/members stores
 */

import { defineStore } from 'pinia';
import Endpoints from '../manageGroupEndpoints';
import { useSettingsStore } from './manageGroupSettingsStore';
import { useMembersStore } from './manageGroupMembersStore';


export const useStore = defineStore('manageGroup', {
    
    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        groupId: 0, //the id of the group
    }),

    actions: {

        /**
         * load all the group data  
         * 
         * @param { String }
         */
        async loadData () {
            try {
                const response = await fetch(Endpoints.GROUP_DATA);
                const responseJson = await response.json();
                this.groupId = responseJson.groupId;
                const settingsStore = useSettingsStore();
                settingsStore.loadSettings(responseJson.settings);
                const membersStore = useMembersStore();
                membersStore.loadMembers(responseJson.members);
            } catch (error) {
                console.warn(error);
                this.hasLoadError = true; //show the load error display state 
            } finally {
                this.isLoading = false; //otherwise show the table
            }
        },

    },
    
});