/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useSelectedNodesStore } from './mcSelectedNodesStore';


export const useEnableNodesStore = defineStore('mcEnableNodes', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
        enabled: false, //to set what enabled status the current selection is being set to
    }),

    actions: {

        /**
         * to display a prompt to confirm a node enabled change
         *
         * @param { boolean }
         */
        prompt (enabled) {
            this.enabled = enabled;
            this.isOpen = true;
        },

        /**
         * node enable/disable has been confirmed so notify the backend
         * and update the frontend to match
         *
         * @TODO - more extensive error checking needs to occur
         */
        async enable () {
            this.isProcessing = true;
            try {
                const nodeEnables = await Promise.all(this.getFetches());
                const enableResponses = nodeEnables.map((response) => response.ok);
                const isAllResponsesOk = enableResponses.every((response) => response === true);
                if (isAllResponsesOk) {
                    const selectedNodesStore = useSelectedNodesStore();
                    selectedNodesStore.nodes.forEach((node) => this.enableAllChildren(node)); //set the enabled on each node
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
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        enabled: this.enabled,
                    }),
                }));
            });
            return fetchList;
        },

        /**
         * Loop through recursively and update all
         * children to the parents updated enabled state
         *
         * @param { array }
         * @param { boolean }
         */
        enableAllChildren (node) {
            node.enabled = this.enabled;
            node.children?.forEach((innerNode) => {
                this.enableAllChildren(innerNode);
            });
        },

        /**
         * to hide the confirmation prompt
         */
        cancel () {
            this.isOpen = false;
        },

    },

})
