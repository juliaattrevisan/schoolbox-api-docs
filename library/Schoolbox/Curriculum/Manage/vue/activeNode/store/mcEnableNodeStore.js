/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useActiveNodeStore } from './mcActiveNodeStore';
import { apiEndpoint } from '../../store/manageCurriculumStore';


export const useEnableNodeStore = defineStore('mcEnableNode', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
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
            this.isOpen = true;
        },

        /**
         * node enable/disable has been confirmed so notify the backend
         * and then pull it out of the nodes if successful
         */
        async enable (node = null) {
            const activeNodeStore = useActiveNodeStore();
            if (node !== null) { //when we are enabling, we don't need the prompt, so we pass in the node directly
                activeNodeStore.set(node);
            }
            this.isProcessing = true;
            try {
                const enabledInverted = !activeNodeStore.node.enabled;
                const response = await fetch(`${apiEndpoint}/${activeNodeStore.node.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        enabled: enabledInverted,
                    }),
                });
                if (response.ok) {
                    activeNodeStore.enabled(enabledInverted);
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
