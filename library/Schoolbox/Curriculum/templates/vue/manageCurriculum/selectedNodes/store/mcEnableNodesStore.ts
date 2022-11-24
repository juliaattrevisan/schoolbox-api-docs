/**
 * Enable/disable the currently selected nodes
 */

import { ref } from 'vue';
import { defineStore } from 'pinia';
import { getApiEndpoint } from '../../store/manageCurriculumStore';
import { useSelectedNodesStore } from './mcSelectedNodesStore';


export const useEnableNodesStore = defineStore('mcEnableNodes', () => {

    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const enabled = ref(false); // to set what enabled status the current selection is being set to

    /**
     * to display a prompt to confirm a node enabled change
     *
     * @param { boolean }
     */
    function prompt (newEnabled) {
        enabled.value = newEnabled;
        isOpen.value = true;
    }

    /**
     * node enable/disable has been confirmed so notify the backend
     * and update the frontend to match
     *
     * @TODO - more extensive error checking needs to occur
     */
    async function enable () {
        try {
            isProcessing.value = true;
            const nodeEnables = await Promise.all(getFetches());
            const enableResponses = nodeEnables.map((response) => response.ok);
            const isAllResponsesOk = enableResponses.every((response) => response === true);
            if (isAllResponsesOk) {
                const successMessage = getSuccessMessage();
                useSelectedNodesStore().enableNodes();
                isProcessing.value = false;
                cancel();
                return successMessage;
            } else {
                throw new Error();
            }
        } catch (error) {
            console.warn(error);
        }
    }

    /**
     * To delete all the marked nodes in parallel
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     *
     * @returns { Array }
     */
    function getFetches () {
        const fetchList = [];
        const selectedNodesStore = useSelectedNodesStore();
        selectedNodesStore.nodes.forEach((node) => {
            fetchList.push(fetch(`${getApiEndpoint()}/${node.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    enabled: enabled.value,
                }),
            }));
        });
        return fetchList;
    }

    /**
     * To notify the user of success status
     *
     * @returns { string }
     */
    function getSuccessMessage () {
        const selectedNodesStore = useSelectedNodesStore();
        const nodeSingleOrPlural = `Node${selectedNodesStore.count > 1  ? 's' : ''}`;
        const enabledOrDisabled = `${ selectedNodesStore.nodes[0].enabled ? 'disabled' : 'enabled'}`
        return `${nodeSingleOrPlural} successfully ${enabledOrDisabled}`;
    }

    /**
     * to hide the confirmation prompt
     */
    function cancel () {
        isOpen.value = false;
    }

    return {
        isProcessing,
        isOpen,
        enabled,
        prompt,
        enable,
        cancel,
    }

})
