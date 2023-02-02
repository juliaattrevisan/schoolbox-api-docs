/**
 * Handles the store to disband the group
 */

import { ref } from "vue";
import { defineStore } from "pinia";
import { useActiveNodeStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcActiveNodeStore";
import type { Node, NodeToUpdate } from "@schoolboxCurriculum/types/Node";

/**
 * Create a copy so the data is only updated once actually saved
 *
 * @param { NodeToUpdate } node - the node to copy
 * @returns { Node } the copy of the node details
 */
function getActiveNodeCopy(node: NodeToUpdate) {
    return {
        name: node.name,
        code: node.code,
        description: node.description,
    };
}

/**
 * Get the data to pass to the backend
 *
 * @param { NodeToUpdate } node - the node details to pass to the backend
 * @returns { string } the JSON to pass
 */
function getFormData(node: NodeToUpdate) {
    return JSON.stringify({
        name: node.name,
        code: node.code,
        description: node.description,
    });
}

export const useUpdateNodeStore = defineStore("mcUpdateNode", () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const activeNodeCopy = ref<NodeToUpdate>({ name: "", description: null, code: null }); // hold a copy of the changes, only update once we confirm the changes

    /**
     * to display a prompt to update a nodes details
     *
     * @param { Node } node - node to update
     */
    function prompt(node: Node) {
        useActiveNodeStore().set(node);
        activeNodeCopy.value = getActiveNodeCopy(node as NodeToUpdate);
        isOpen.value = true;
    }

    /**
     * to hide the update details prompt
     */
    function cancel() {
        useActiveNodeStore().unset();
        isOpen.value = false;
    }

    /**
     * update the nodes details in the back end
     */
    async function update() {
        try {
            isProcessing.value = true;
            const activeNodeStore = useActiveNodeStore();
            const response = await fetch(activeNodeStore.node._links.update, {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                },
                body: getFormData(activeNodeCopy.value),
            });
            const responseJson: Node = (await response.json()) as Node;
            if (response.ok) {
                activeNodeStore.update(activeNodeCopy.value);
                isProcessing.value = false;
                cancel();
                return { id: activeNodeStore.node.id, message: "Node successfully updated" };
            }
            throw new Error(responseJson.title, { ...responseJson });
        } catch (error) {
            throw new Error(error.message, error.options);
        }
    }

    return {
        isProcessing,
        isOpen,
        activeNodeCopy,
        prompt,
        update,
        cancel,
    };
});
