/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useActiveNodeStore } from './mcActiveNodeStore';


export const useAddSubsectionStore = defineStore('mcAddSubsectionStore', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
        subsection: {},
    }),

    actions: {

        /**
         * to display a prompt to confirm adding a node to a subsection
         *
         * @param { object }
         */
        prompt (node) {
            const activeNodeStore = useActiveNodeStore();
            activeNodeStore.set(node);
            this.subsection = getSubsection();
            this.isOpen = true;
        },

        /**
         * pass the new subsections details to the backend
         *
         * @param { object or null }
         */
        async addSubsection () {
            this.isProcessing = true;
            try {
                this.isProcessing = false;
                const activeNodeStore = useActiveNodeStore();
                const response = await fetch(`/api/curriculum/${activeNodeStore.node.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this.subsection),
                });
                if (response.ok) {
                    const responseJson = await response.json();
                    // activeNodeStore.update(this.activeNodeCopy);
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
 * Pass empty fields to be added
 *
 * @param { Object }
 *
 * @returns { Object }
 */
 function getSubsection (node) {
    return {
        name: '',
        code: '',
        reference: '',
        description: '',
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
