/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useActiveNodeStore } from './mcActiveNodeStore';


export const useUpdateNodeStore = defineStore('mcUpdateNode', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
        activeNodeCopy: {},
    }),

    actions: {

        /**
         * to display a prompt to confirm a node removal
         *
         * @param { object }
         */
        prompt (node) {
            const activeNodeStore = useActiveNodeStore();
            activeNodeStore.set(node);
            this.activeNodeCopy = getActiveNodeCopy(node);
            this.isOpen = true;
        },

        /**
         * node removal has been confirmed so notify the backend
         * and then pull it out of the nodes if successful
         */
        async update () {
            this.isProcessing = true;
            try {
                this.isProcessing = false;
                const activeNodeStore = useActiveNodeStore();
                const response = await fetch(`/api/curriculum/${activeNodeStore.node.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this.activeNodeCopy),
                });
                if (response.ok) {
                    activeNodeStore.update(this.activeNodeCopy);
                    this.isProcessing = false;
                    this.cancel();
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
            const activeNodeStore = useActiveNodeStore();
            activeNodeStore.unset();
            this.isOpen = false;
        },

    },

})

/**
 * Create a copy so the data is only updated once actually saved
 *
 * @param { Object }
 *
 * @returns { Object }
 */
 function getActiveNodeCopy (node) {
    return {
        name: node.name,
        code: node.code,
        reference: node.reference,
        description: node.description,
    }
}

/**
 * Pass through the group id
 *
 * @returns { FormData }
 */
 function getFormData (node) {
    return JSON.stringify({
        name: node.name,
        code: node.code,
        reference: node.reference,
        description: node.description,
    });
}
