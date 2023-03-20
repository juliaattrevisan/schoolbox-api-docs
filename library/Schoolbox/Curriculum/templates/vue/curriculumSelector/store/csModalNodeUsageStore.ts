/**
 * To request further details on a nodes usage
 */

import { ref } from "vue";
import { defineStore } from "pinia";
import type { Node, Usage } from "@schoolboxCurriculum/types/Node";
import { useModalActiveNodeStore } from "./csModalActiveNodeStore";

export const useModalNodeUsageStore = defineStore("csModalNodeUsage", () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const hasLoadError = ref(false);

    /**
     * retrieve all the details of the nodes usage
     */
    async function view() {
        try {
            isProcessing.value = true;
            const modalActiveNodeStore = useModalActiveNodeStore();
            const usageResponse = await fetch(modalActiveNodeStore.node._links.getUsage);
            if (usageResponse.ok) {
                const usage = (await usageResponse.json()) as JSON;
                // we are not currently displaying folders in the usage list
                const withoutFolders = usage.data.filter((item: Usage) => item.discriminator !== "folder");
                modalActiveNodeStore.node.usage = withoutFolders as Usage[];
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
        useModalActiveNodeStore().set(node);
        isOpen.value = true;
        await view();
    }

    /**
     * to hide the nodes usage details
     */
    function cancel() {
        useModalActiveNodeStore().unset();
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
