/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useActiveNodeStore } from './mcActiveNodeStore';
import { useSelectedNodesStore } from '../../selectedNodes/store/mcSelectedNodesStore';


export const useUpdateNodeStore = defineStore('mcUpdateNode', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
        activeNodeCopy: {}, //hold a copy of the changes, only update once we confirm the changes
    }),

    actions: {

        /**
         * to display a prompt to update a nodes details
         *
         * @param { object }
         */
        prompt (node) {
            useActiveNodeStore().set(node);
            this.activeNodeCopy = getActiveNodeCopy(node);
            this.isOpen = true;
        },

        /**
         * update the nodes details in the back end
         *
         * @param { object or null }
         */
        async update () {
            this.isProcessing = true;
            try {
                this.isProcessing = false;
                const activeNodeStore = useActiveNodeStore();
                const nodeId = activeNodeStore.node.id;
                const response = await fetch(`${apiEndpoint}/${nodeId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this.activeNodeCopy),
                });
                const responseJson = await response.json();
                if (response.ok) {
                    activeNodeStore.update(this.activeNodeCopy);
                    this.isProcessing = false;
                    this.cancel();
                    return { id: nodeId, message: 'Node successfully updated' };
                } else {
                    throw new Error(responseJson.title, { ...responseJson } );
                }
            } catch (error) {
                throw new Error(error.message, error.options);
            }
        },

        /**
         * to hide the update details prompt
         */
        cancel () {
            useActiveNodeStore().unset();
            this.isOpen = false;
        },

        /**
         * the active node may have just been deleted, in which case we
         * should close the prompt if it's open
         */
        nodesDeleted () {
            const deletedNodeIds = useSelectedNodesStore().nodesIds;
            const activeNodeStore = useActiveNodeStore();
            if (deletedNodeIds.includes(activeNodeStore.node?.id) && this.isOpen) {
                this.cancel();
            }
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
        description: node.description,
    });
}
