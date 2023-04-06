/**
 * Delete the currently selected nodes
 */

import { ref } from "vue";
import { defineStore } from "pinia";
import { capitalizeFirstLetter } from "@js/util/stringUtils";
import { useSelectedNodesStore } from "./mcSelectedNodesStore";
import { removeStoredNodes } from "@schoolboxCurriculum/js/sessionStorage";

export const useDeleteNodesStore = defineStore("mcDeleteNodes", () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed

    /**
     * to display a prompt to confirm a node removal
     */
    function prompt() {
        isOpen.value = true;
    }

    /**
     * To delete all the marked nodes in parallel
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     *
     * @returns { Array } - list of fetches
     */
    function getFetches() {
        const fetchList = [];
        const selectedNodesStore = useSelectedNodesStore();
        selectedNodesStore.nodes.forEach((node) => {
            fetchList.push(
                fetch(node._links.delete, {
                    method: "DELETE",
                    headers: {
                        "Content-Type": "application/json",
                    },
                })
            );
        });
        return fetchList;
    }

    /**
     * To notify the user of success status
     *
     * @returns { string } - the success message
     */
    function getSuccessMessage() {
        const nodeSingleOrPlural = capitalizeFirstLetter(useSelectedNodesStore().singleOrPlural);
        return `${nodeSingleOrPlural} successfully deleted`;
    }

    /**
     * to hide the confirmation prompt
     */
    function cancel() {
        isOpen.value = false;
    }

    /**
     * node removal has been confirmed so notify the backend
     * and then pull it out of the nodes if successful
     *
     * @todo - more extensive error checking needs to occur
     */
    async function deleteNodes() {
        try {
            isProcessing.value = true;
            const nodeDeletions = await Promise.all(getFetches());
            const deleteResponses = nodeDeletions.map((response) => response.ok);
            const isAllResponsesOk = deleteResponses.every((response) => response === true);
            if (isAllResponsesOk) {
                const successMessage = getSuccessMessage();
                useSelectedNodesStore().deleteNodes();
                removeStoredNodes();
                cancel();
                return successMessage;
            }
            throw new Error();
        } catch (error) {
            throw new Error();
        } finally {
            isProcessing.value = false;
        }
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        deleteNodes,
        cancel,
    };
});
