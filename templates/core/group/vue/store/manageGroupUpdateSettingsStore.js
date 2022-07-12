/** 
 * Handles the store to update the group settings 
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore'
import Endpoints from '../manageGroupEndpoints';


export const useUpdateSettingsStore = defineStore('manageGroupUpdateSettings', {
    
    state: () => ({
        isPromptOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm member removal
         */
        promptUpdateSettings () {
            this.isPromptOpen = true;
        },

        /**
         * member removal has been confirmed so pull them out
         */
        async updateSettings () {
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(Endpoints.UPDATE_SETTINGS, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(),
                });
                const responseJson = await response.json();
                if (responseJson.success) {
                    // 
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
function getFormData () {
    const store = useStore();
    return JSON.stringify({ 
        settings: {
            access: store.access,
            isNotificationsEnabled: store.isNotificationsEnabled,
            isForceFollow: store.isForceFollow,
        }
    });
}