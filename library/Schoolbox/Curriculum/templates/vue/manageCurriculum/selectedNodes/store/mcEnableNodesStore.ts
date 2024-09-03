/**
 * Enable/disable the currently selected nodes
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import { defineStore } from "pinia";
import { capitalizeFirstLetter } from "@js/util/stringUtils";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/selectedNodes/store/mcSelectedNodesStore";

export const useEnableNodesStore = defineStore("mcEnableNodes", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const enabled = window.Vue.ref(false); // to set what enabled status the current selection is being set to

    /**
     * to display a prompt to confirm a node enabled change
     * @param { boolean } newEnabled - whether to enable or disable the selected nodes
     */
    function prompt(newEnabled: boolean) {
        enabled.value = newEnabled;
        isOpen.value = true;
    }

    /**
     * to hide the confirmation prompt
     */
    function cancel() {
        isOpen.value = false;
    }

    /**
     * To delete all the marked nodes in parallel
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
     * @returns { Promise<Response> } - to individually enable/disable each node
     */
    function getFetches(): Promise<Response>[] {
        const selectedNodesStore = useSelectedNodesStore();

        return selectedNodesStore.nodes.map((node) => {
            return fetch(node._links.update, {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    enabled: enabled.value,
                }),
            });
        });
    }

    /**
     * To notify the user of success status
     * @returns { string } the success message
     */
    function getSuccessMessage(): string {
        const selectedNodesStore = useSelectedNodesStore();
        const nodeSingleOrPlural = capitalizeFirstLetter(useSelectedNodesStore().singleOrPlural);
        const enabledOrDisabled = `${selectedNodesStore.nodes[0].enabled ? "disabled" : "enabled"}`;
        return `${nodeSingleOrPlural} successfully ${enabledOrDisabled}`;
    }

    /**
     * node enable/disable has been confirmed so notify the backend
     * and update the frontend to match
     * @todo - more extensive error checking needs to occur
     * @returns { Promise<string> } the success message
     */
    async function enable(): Promise<string> {
        try {
            isProcessing.value = true;
            const nodeEnables = await Promise.all(getFetches());
            isProcessing.value = false;

            const enableResponses = nodeEnables.map((response) => response.ok);
            const isAllResponsesOk = enableResponses.every((response) => response === true);
            if (isAllResponsesOk) {
                const successMessage = getSuccessMessage(); // Must get success message before selectedNodesStore is emptied following enableNodes() below.
                useSelectedNodesStore().enableNodes();
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
        enabled,
        prompt,
        enable,
        cancel,
    };
});
