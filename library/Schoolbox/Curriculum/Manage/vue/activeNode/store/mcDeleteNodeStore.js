/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useActiveNodeStore } from './mcActiveNodeStore'


export const useDeleteNodeStore = defineStore('mcDeleteNode', {

    state: () => ({
        isProcessing: false,
        isPromptOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm member removal
         */
        prompt () {
            this.isPromptOpen = true;
        },

        /**
         * member removal has been confirmed so pull them out
         */
        async delete () {
            this.isProcessing = true;
            try {
                const response = await fetch('/api/curriculum/{id}', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
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
                this.isProcessing = false;
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancel () {
            this.isPromptOpen = false;
        },

    },

})
