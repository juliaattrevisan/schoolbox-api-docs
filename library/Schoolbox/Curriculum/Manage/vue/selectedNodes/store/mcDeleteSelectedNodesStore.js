/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useNodesStore } from '../../store/mcNodesStore';
import { useSelectedNodesStore } from './mcSelectedNodesStore';


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
         *
         * @TODO - more extensive error checking needs to occur
         */
        async delete () {
            this.isProcessing = true;
            try {
                const nodeDeletions = await Promise.all(this.getFetches());
                const deleteResponses = nodeDeletions.map((response) => response.ok);
                const isAllResponsesOk = deleteResponses.every((response) => response === true);
                if (isAllResponsesOk) {
                    const selectedNodesStore = useSelectedNodesStore();
                    const nodesStore = useNodesStore();
                    selectedNodesStore.nodes.forEach((node) => nodesStore.deleteNode(node)); //delete each node from the tree
                    selectedNodesStore.empty();
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
         * To delete all the marked nodes in parallel
         * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
         *
         * @returns { Array }
         */
        getFetches () {
            const fetchList = [];
            const selectedNodesStore = useSelectedNodesStore();
            selectedNodesStore.nodes.forEach((node) => {
                fetchList.push(fetch(`${apiEndpoint}/${node.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                }));
            });
            return fetchList;
        },

        /**
         * to hide the confirmation prompt
         */
        cancel () {
            this.isOpen = false;
        },

    },

})
