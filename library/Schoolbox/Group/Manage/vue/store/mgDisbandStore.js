/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore'
import Endpoints from '../mgEndpoints';


export const useDisbandStore = defineStore('mgDisband', {

    state: () => ({
        isProcessing: false,
        isPromptOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm member removal
         */
        promptDisbandGroup () {
            this.isPromptOpen = true;
        },

        /**
         * member removal has been confirmed so pull them out
         */
        async disbandGroup () {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch(Endpoints.DISBAND_GROUP, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(),
                });
                if (!response.ok) {
                    throw new Error();
                }
            } catch (error) {
                throw new Error(error);
            } finally {
                this.isProcessing = false;
                store.isProcessing = false;
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancelDisbandGroup () {
            this.isPromptOpen = false;
        },

    },

})

/**
 * Pass through the group id
 *
 * @returns { FormData }
 */
function getFormData () {
    const store = useStore();
    return JSON.stringify({
        groupId: store.id
    });
}
