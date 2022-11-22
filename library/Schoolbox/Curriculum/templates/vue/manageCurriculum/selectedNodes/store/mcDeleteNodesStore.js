/**
 * Delete the currently selected nodes
 */

import { ref } from 'vue';
import { defineStore } from 'pinia';
import { capitalizeFirstLetter } from '@js/util/stringUtils';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useSelectedNodesStore } from './mcSelectedNodesStore';


export const useDeleteNodesStore = defineStore('mcDeleteNodes', () => {

    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed

    /**
     * to display a prompt to confirm a node removal
     */
    function prompt () {
        isOpen.value = true;
    }

    /**
     * node removal has been confirmed so notify the backend
     * and then pull it out of the nodes if successful
     *
     * @TODO - more extensive error checking needs to occur
     */
    async function deleteNodes () {
        try {
            isProcessing.value = true;
            const nodeDeletions = await Promise.all(getFetches());
            const deleteResponses = nodeDeletions.map((response) => response.ok);
            const isAllResponsesOk = deleteResponses.every((response) => response === true);
            if (isAllResponsesOk) {
                const successMessage = getSuccessMessage();
                useSelectedNodesStore().deleteNodes();
                cancel();
                return successMessage;
            } else {
                throw new Error();
            }
        } catch (error) {
            console.warn(error);
        } finally {
            isProcessing.value = false;
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
            fetchList.push(fetch(`${apiEndpoint}/${node.id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
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
        const nodeSingleOrPlural = capitalizeFirstLetter(useSelectedNodesStore().singleOrPlural);
        return `${nodeSingleOrPlural} successfully deleted`;
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
        prompt,
        deleteNodes,
        cancel,
    }

})
