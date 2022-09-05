/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { useActiveNodeStore } from '../../activeNode/store/mcActiveNodeStore';
import { apiEndpoint } from '../../store/manageCurriculumStore';


export const useEnableSelectedNodesStore = defineStore('mcEnableSelectedNodes', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
    }),

    actions: {

        /**
         * to display a prompt to confirm a node enabled change
         */
        prompt () {
            this.isOpen = true;
        },

        /**
         * node enable/disable has been confirmed so notify the backend
         * and update the frontend to match
         *
         * @TODO - more extensive error checking needs to occur
         *
         * @param { boolean }
         */
        async enable (enabled) {
            // const activeNodeStore = useActiveNodeStore();
            // if (node !== null) { //when we are enabling, we don't need the prompt, so we pass in the node directly
            //     activeNodeStore.set(node);
            // }
            // this.isProcessing = true;
            // try {
            //     const enabledInverted = !activeNodeStore.node.enabled;
            //     const response = await fetch(`${apiEndpoint}/${activeNodeStore.node.id}`, {
            //         method: 'PATCH',
            //         headers: {
            //             'Content-Type': 'application/json',
            //         },
            //         body: JSON.stringify({
            //             enabled: enabledInverted,
            //         }),
            //     });
            //     if (response.ok) {
            //         activeNodeStore.enabled(enabledInverted);
            //         this.isProcessing = false;
            //         this.cancel();
            //     } else {
            //         throw new Error();
            //     }
            // } catch (error) {
            //     console.warn(error);
            // }
            this.isProcessing = true;
            try {
                const nodeEnables = await Promise.all(this.getFetches());
                const enableResponses = nodeEnables.map((response) => response.ok);
                const isAllResponsesOk = enableResponses.every((response) => response === true);
                if (isAllResponsesOk) {
                    const selectedNodesStore = useSelectedNodesStore();
                    selectedNodesStore.nodes.forEach((node) => this.enableAllChildren(node, enabled)); //set the enabled on each node
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
         * @param { boolean }
         *
         * @returns { Array }
         */
        getFetches (enabled) {
            const fetchList = [];
            const selectedNodesStore = useSelectedNodesStore();
            selectedNodesStore.nodes.forEach((node) => {
                fetchList.push(fetch(`${apiEndpoint}/${node.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        enabled: enabled,
                    }),
                }));
            });
            return fetchList;
        },

        /**
         * Loop through and update all children to the parents
         * updated enabled state
         *
         * @param { array }
         * @param { boolean }
         */
        enableAllChildren (node, enabled) {
            node.enabled = enabled;
            node.children?.forEach((innerNode) => {
                this.enableAllChildren(innerNode, enabled);
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
