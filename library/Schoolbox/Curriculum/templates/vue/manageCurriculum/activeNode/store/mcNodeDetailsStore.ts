/**
 * To request further details on a nodes usage
 */

import { ref } from "vue";
import { defineStore } from "pinia";
import { getBreadcrumbs } from "@schoolboxCurriculum/js/nodeUtils";
import { useStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/manageCurriculumStore";
import { useActiveNodeStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcActiveNodeStore";
import type { Node } from "@schoolboxCurriculum/types/Node";

export const useNodeDetailsStore = defineStore("mcNodeDetails", () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed

    /**
     * retrieve all the details of the nodes usage
     */
    async function view() {
        try {
            isProcessing.value = true;
            const activeNodeStore = useActiveNodeStore();
            const nodeId: number = activeNodeStore.node.id;
            const response = await fetch(`${useStore().getApiEndpoint()}/${nodeId}?ancestors=99`); // just use 99 as hopefully there's no tree that deep...            // if (response.ok) {
            //     activeNodeStore.update(activeNodeCopy.value);
            //     isProcessing.value = false;
            //     cancel();
            //     return { id: nodeId, message: "Node successfully updated" };
            // }
            if (response.ok) {
                const nodeWithParents = (await response.json()) as Node;
                activeNodeStore.node.breadcrumbs = getBreadcrumbs(nodeWithParents, []);
                // node.value.description = nodeWithParents.description;
            } else {
                console.warn(response);
                // hasLoadError.value = true;
            }
            // throw new Error(responseJson.title, { ...responseJson });
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
        isOpen,
        prompt,
        view,
        cancel,
    };
});
