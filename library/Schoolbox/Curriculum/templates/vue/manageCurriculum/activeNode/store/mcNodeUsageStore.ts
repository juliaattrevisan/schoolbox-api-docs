/**
 * To request further details on a nodes usage
 */

import { ref } from "vue";
import { defineStore } from "pinia";
import { useStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/manageCurriculumStore";
import { useActiveNodeStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcActiveNodeStore";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useNodeUsageStore = defineStore("mcNodeUsage", () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed

    /**
     * to display all the nodes usage details
     *
     * @param { Node } node - node to update
     */
    function prompt(node: Node) {
        useActiveNodeStore().set(node);
        isOpen.value = true;
    }

    /**
     * to hide the nodes usage details
     */
    function cancel() {
        useActiveNodeStore().unset();
        isOpen.value = false;
    }

    /**
     * retrieve all the details of the nodes usage
     */
    async function view() {
        try {
            isProcessing.value = true;
            const activeNodeStore = useActiveNodeStore();
            const nodeId: number = activeNodeStore.node.id;
            const response = await fetch(`${useStore().getApiEndpoint()}/${nodeId}`, {
                headers: {
                    "Content-Type": "application/json",
                },
            });
            const responseJson: Node = (await response.json()) as Node;
            // if (response.ok) {
            //     activeNodeStore.update(activeNodeCopy.value);
            //     isProcessing.value = false;
            //     cancel();
            //     return { id: nodeId, message: "Node successfully updated" };
            // }
            throw new Error(responseJson.title, { ...responseJson });
        } catch (error) {
            throw new Error(error.message, error.options);
        }
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        view,
        cancel,
    };
});
