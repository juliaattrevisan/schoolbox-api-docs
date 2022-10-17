/**
 * Delete the currently selected nodes
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useSelectedNodesStore } from './mcSelectedNodesStore';
import { useUpdateNodeStore } from '../../activeNode/store/mcUpdateNodeStore';
import { useAddSubsectionStore } from '../../activeNode/store/mcAddSubsectionStore';


export const useDeleteNodesStore = defineStore('mcDeleteNodes', {

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
                    useSelectedNodesStore().deleteNodes();
                    useUpdateNodeStore().cancel(); //the active node may have been deleted
                    useAddSubsectionStore().cancel(); //the active node may have been deleted
                    this.cancel();
                    return this.getSuccessMessage();
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
         * To notify the user of success status
         *
         * @returns { string }
         */
        getSuccessMessage () {
            const selectedNodesStore = useSelectedNodesStore();
            const nodeSingleOrPlural = `Node${selectedNodesStore.count > 1  ? 's' : ''}`;
            return `${nodeSingleOrPlural} successfully deleted`;
        },

        /**
         * to hide the confirmation prompt
         */
        cancel () {
            this.isOpen = false;
        },

    },

})
