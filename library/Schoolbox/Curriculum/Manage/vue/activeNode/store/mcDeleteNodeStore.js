/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useActiveNodeStore } from './mcActiveNodeStore'


export const useDeleteNodeStore = defineStore('mcDeleteNode', {

    state: () => ({
        isProcessing: false,
        isModalOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm member removal
         */
        prompt () {
            this.isModalOpen = true;
        },

        /**
         * member removal has been confirmed so pull them out
         */
        async delete () {
            this.isProcessing = true;
            const activeNodeStore = useActiveNodeStore();
            try {
                const response = await fetch(`/api/curriculum/${activeNodeStore.node.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                if (response.ok) {
                    this.isProcessing = false;
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancel () {
            this.isModalOpen = false;
        },

    },

})
