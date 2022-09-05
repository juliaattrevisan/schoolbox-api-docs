/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useNodesStore } from '../../store/mcNodesStore';
import { useActiveNodeStore } from '../../activeNode/store/mcActiveNodeStore';


export const useDeleteSelectedNodesStore = defineStore('mcDeleteSelectedNodes', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm a node removal
         */
        prompt () {
            this.isOpen = true;
        },

        /**
         * node removal has been confirmed so notify the backend
         * and then pull it out of the nodes if successful
         */
        async delete () {
            this.isProcessing = true;
            try {
                const activeNodeStore = useActiveNodeStore();
                const response = await fetch(`${apiEndpoint}/${activeNodeStore.node.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                if (response.ok) {
                    const nodesStore = useNodesStore();
                    nodesStore.deleteNode(node);
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
            this.isOpen = false;
        },

    },

})
