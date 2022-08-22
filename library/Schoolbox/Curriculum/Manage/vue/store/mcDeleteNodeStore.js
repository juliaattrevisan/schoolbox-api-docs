/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useStore } from './manageGroupStore'


export const useDeleteNodeStore = defineStore('mcDeleteNode', {

    state: () => ({
        isProcessing: false,
        isPromptOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm member removal
         */
        propt () {
            this.isPromptOpen = true;
        },

        /**
         * member removal has been confirmed so pull them out
         */
        async delete () {
            this.isProcessing = true;
            const store = useStore();
            store.isProcessing = true;
            try {
                const response = await fetch('/api/curriculum/{id}', {
                    method: 'DELETE',
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
        groupId: store.groupId
    });

}
