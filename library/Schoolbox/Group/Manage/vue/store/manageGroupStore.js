/**
 * Handles the load of the current Manage Group page and
 * sends off the data to settings/members stores
 */

import { defineStore } from 'pinia';
import Endpoints from '../mgEndpoints';
import { useSettingsStore } from './mgSettingsStore';
import { useMembersStore } from './mgMembersStore';


export const useStore = defineStore('manageGroup', {

    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        id: 0, //the id of the group
        name: '', //the name of the group
        toastEl: null, //so we don't need to pass it all around as a prop
        showSetting: false, // display setting modal
        groupStaffOnly: false, // restrict only staff to be group admin
    }),

    getters: {

        /**
         * When load is complete and and was a success
         *
         * @returns { Boolean }
         */
        hasLoadedOk () {
            return !this.isLoading && !this.hasLoadError;
        },

    },

    actions: {

        /**
         * load all the group data
         *
         * @param { String }
         */
        async loadData () {
            try {
                const response = await fetch(Endpoints.GROUP_DATA);
                if (response.ok) {
                    const responseJson = await response.json();
                    this.id = responseJson.groupId;
                    this.name = responseJson.groupName;
                    const settingsStore = useSettingsStore();
                    settingsStore.loadSettings(responseJson.settings);
                    const membersStore = useMembersStore();
                    membersStore.loadMembers(responseJson.members);
                } else {
                    console.warn(response);
                    this.hasLoadError = true; //show the load error display state
                }
            } catch (error) {
                console.warn(error);
                this.hasLoadError = true; //show the load error display state
            } finally {
                this.isLoading = false; //otherwise show the table
            }
        },

    },

});
