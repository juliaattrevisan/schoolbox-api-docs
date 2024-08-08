/**
 * Delete the currently selected nodes
 */

import { defineStore } from "pinia";
import { capitalizeFirstLetter } from "@js/util/stringUtils";
import { useSelectedNodesStore } from "./mcSelectedNodesStore";

export const useDeleteNodesStore = defineStore("mcDeleteNodes", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed

    /**
     * to display a prompt to confirm a node removal
     */
    function prompt() {
        isOpen.value = true;
    }

    /**
     * To delete all the marked nodes in parallel
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     * @returns { Promise<Response> } - list of fetch promises
     */
    function getFetches(): Promise<Response>[] {
        const selectedNodesStore = useSelectedNodesStore();

        return selectedNodesStore.nodes.map((node) => {
            return fetch(node._links.delete, {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                },
            });
        });
    }

    /**
     * To notify the user of success status
     * @returns { string } - the success message
     */
    function getSuccessMessage(): string {
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
     * @todo - more extensive error checking needs to occur
     * @returns { Promise<string> } - the success message
     */
    async function deleteNodes(): Promise<string> {
        try {
            isProcessing.value = true;
            const nodeDeletions = await Promise.all(getFetches());
            isProcessing.value = false;

            const deleteResponses = nodeDeletions.map((response) => response.ok);
            const isAllResponsesOk = deleteResponses.every((response) => response === true);
            if (isAllResponsesOk) {
                const successMessage = getSuccessMessage();
                useSelectedNodesStore().deleteNodes();
                cancel();
                return successMessage;
            }
        } catch (error) {
            throw new Error();
        }

        // It is expected that the request will be sent, and all responses will be okay.
        // So if execution gets to here, an error has occurred.
        throw new Error();
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        deleteNodes,
        cancel,
    };
});
