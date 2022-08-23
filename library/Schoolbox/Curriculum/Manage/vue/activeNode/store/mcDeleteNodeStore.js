/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useNodesStore } from '../../store/mcNodesStore';
import { useActiveNodeStore } from './mcActiveNodeStore';
import { useViewNodesStore } from '../../store/mcViewNodesStore';


export const useDeleteNodeStore = defineStore('mcDeleteNode', {

    state: () => ({
        isProcessing: false,
        isModalOpen: false, //to notify that the prompt is opened / closed
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
            this.isModalOpen = true;
        },

        /**
         * node removal has been confirmed so notify the backend
         * and then pull it out of the nodes if successful
         */
        async delete () {
            this.isProcessing = true;
            const nodesStore = useNodesStore();
            // const activeNodeStore = useActiveNodeStore();
            try {
                // useViewNodesStore().deleteActiveNode();
                nodesStore.deleteActiveNode();
                this.isProcessing = false;
                this.cancel();
                // const response = await fetch(`/api/curriculum/${activeNodeStore.node.id}`, {
                //     method: 'DELETE',
                //     headers: {
                //         'Content-Type': 'application/json',
                //     },
                // });
                // if (response.ok) {
                //     activeNodeStore.unset(); //unset as the node is no longer available
                //     this.isProcessing = false;
                // } else {
                //     throw new Error();
                // }
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
            this.isModalOpen = false;
        },

    },

})
