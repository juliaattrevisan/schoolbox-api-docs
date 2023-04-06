/**
 * Enable/disable the currently selected nodes
 */

import { ref } from "vue";
import { defineStore } from "pinia";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/selectedNodes/store/mcSelectedNodesStore";
import { removeStoredNodes } from "@schoolboxCurriculum/js/sessionStorage";

export const useEnableNodesStore = defineStore("mcEnableNodes", () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const enabled = ref(false); // to set what enabled status the current selection is being set to

    /**
     * to display a prompt to confirm a node enabled change
     *
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
     *
     * @returns { Array } - to individually enable/disable each node
     */
    function getFetches() {
        const fetchList: Promise<string> = [];
        const selectedNodesStore = useSelectedNodesStore();
        selectedNodesStore.nodes.forEach((node) => {
            fetchList.push(
                fetch(node._links.update, {
                    method: "PATCH",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        enabled: enabled.value,
                    }),
                })
            );
        });
        return fetchList;
    }

    /**
     * To notify the user of success status
     *
     * @returns { string } the success message
     */
    function getSuccessMessage() {
        const selectedNodesStore = useSelectedNodesStore();
        const nodeSingleOrPlural = `Node${selectedNodesStore.count > 1 ? "s" : ""}`;
        const enabledOrDisabled = `${selectedNodesStore.nodes[0].enabled ? "disabled" : "enabled"}`;
        return `${nodeSingleOrPlural} successfully ${enabledOrDisabled}`;
    }

    /**
     * node enable/disable has been confirmed so notify the backend
     * and update the frontend to match
     *
     * @todo - more extensive error checking needs to occur
     */
    async function enable() {
        try {
            isProcessing.value = true;
            const nodeEnables = await Promise.all(getFetches());
            const enableResponses = nodeEnables.map((response) => response.ok);
            const isAllResponsesOk = enableResponses.every((response) => response === true);
            if (isAllResponsesOk) {
                const successMessage = getSuccessMessage();
                useSelectedNodesStore().enableNodes();
                isProcessing.value = false;
                removeStoredNodes();
                cancel();
                return successMessage;
            }
            throw new Error();
        } catch (error) {
            console.warn(error);
        }
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
