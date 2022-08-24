/**
 * Handles the store to update the group settings
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore'
import Endpoints from '../mgEndpoints';


export const useSettingsStore = defineStore('mgSettings', {

    state: () => ({
        isProcessing: false,
        access: '', //can be private, moderated or free
        isNotificationsEnabled: false, //to send notifications to the group
        isForceFollow: false, //so notifcations are ALWAYS sent to all members
        isPromptOpen: false, //to notify that the prompt is opened / closed
        isAccessSaved: false, //to ensure group access is set and saved
    }),

    getters: {

        /**
         * to prompt the user to select the group access
         *
         * @return { Boolean }
         */
        isAccessNotSet () {
            const store = useStore();
            return store.showSetting;
        },

    },

    actions: {

        /**
         * Add in the group settings for reference / updating
         *
         * @param { Object }
         */
        loadSettings (settings) {
            this.access = settings.access;
            if (this.access !== '') {
                this.isAccessSaved = true;
            }
            this.isNotificationsEnabled = settings.isNotificationsEnabled;
            this.isForceFollow = settings.isForceFollow;
        },

        /**
         * to display a prompt to confirm member removal
         */
        promptUpdateSettings () {
            this.isPromptOpen = true;
        },

        /**
         * update the pages settings
         */
        async updateSettings () {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(Endpoints.UPDATE_SETTINGS, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    this.isAccessSaved = true;
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            } finally {
                this.isProcessing = false;
                store.isProcessing = false;
                store.showSetting = false;
                this.isPromptOpen = false;
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancelUpdateSettings () {
            this.isPromptOpen = false;
        },

    },

})

/**
 * Pass through the latest settings
 *
 * @returns { FormData }
 */
function getFormData (myStore) {
    const store = useStore();
    return JSON.stringify({
        groupId: store.id,
        settings: {
            access: myStore.access,
            isNotificationsEnabled: myStore.isNotificationsEnabled,
            isForceFollow: myStore.isForceFollow,
        }
    });
}
