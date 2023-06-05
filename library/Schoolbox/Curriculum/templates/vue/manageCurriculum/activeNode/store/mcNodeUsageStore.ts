/**
 * To request further details on a nodes usage
 */

import { defineStore } from "pinia";
import { useActiveNodeStore } from "./mcActiveNodeStore";
import type { Node, Usage } from "@schoolboxCurriculum/types/Node";

export const useNodeUsageStore = defineStore("mcNodeUsage", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const hasLoadError = window.Vue.ref(false);

    /**
     * retrieve all the details of the nodes usage
     */
    async function view() {
        try {
            isProcessing.value = true;
            const activeNodeStore = useActiveNodeStore();
            const usageResponse = await fetch(activeNodeStore.node._links.getUsage);
            if (usageResponse.ok) {
                const usage = (await usageResponse.json()) as JSON;
                // we are not currently displaying folders in the usage list
                const withoutFolders = usage.data.filter((item: Usage) => item.discriminator !== "folder");
                activeNodeStore.node.usage = withoutFolders as Usage[];
            } else {
                hasLoadError.value = true;
            }
        } catch (error) {
            throw new Error(error.message, error.options);
        } finally {
            isProcessing.value = false;
        }
    }

    /**
     * to display all the nodes usage details
     *
     * @param { Node } node - node to update
     */
    async function prompt(node: Node) {
        useActiveNodeStore().set(node);
        isOpen.value = true;
        await view();
    }

    /**
     * to hide the nodes usage details
     */
    function cancel() {
        useActiveNodeStore().unset();
        isOpen.value = false;
    }

    return {
        isProcessing,
        hasLoadError,
        isOpen,
        prompt,
        view,
        cancel,
    };
});
