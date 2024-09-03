/**
 * To request further details on a nodes usage
 */

import { defineStore } from "pinia";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import type { Node, Usage } from "@schoolboxCurriculum/types/Node";
import { useActiveNodeStore } from "./mcActiveNodeStore";

export const useNodeUsageStore = defineStore("mcNodeUsage", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const hasLoadError = window.Vue.ref(false);

    /**
     * retrieve all the details of the nodes usage
     */
    async function view(): Promise<void> {
        try {
            isProcessing.value = true;
            const activeNodeStore = useActiveNodeStore();
            const usageResponse = await fetch(activeNodeStore.node._links.getUsage);
            if (usageResponse.ok) {
                const usage = (await usageResponse.json()) as { data: Usage[] };
                // we are not currently displaying folders in the usage list
                const withoutFolders = usage.data.filter((item: Usage) => item.discriminator !== ActivityTypes.FOLDER);
                activeNodeStore.node.usage = withoutFolders;
            } else {
                hasLoadError.value = true;
            }
        } catch (error) {
            throw new Error((error as Error).message);
        } finally {
            isProcessing.value = false;
        }
    }

    /**
     * to display all the nodes usage details
     * @param node - node to update
     */
    async function prompt(node: Node): Promise<void> {
        useActiveNodeStore().set(node);
        isOpen.value = true;
        await view();
    }

    /**
     * to hide the nodes usage details
     */
    function cancel(): void {
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
