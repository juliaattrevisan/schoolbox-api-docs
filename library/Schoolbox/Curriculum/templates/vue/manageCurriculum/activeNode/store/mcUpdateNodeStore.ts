/**
 * Handles the store to disband the group
 */

/* eslint-disable import/no-cycle -- Disable dependency cycle warning temporarily [SA-18428] */

import { defineStore } from "pinia";
import { useActiveNodeStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcActiveNodeStore";
import type { Node, NodeToUpdate } from "@schoolboxCurriculum/types/Node";

/**
 * Create a copy so the data is only updated once actually saved
 * @param node - the node to copy
 * @returns the copy of the node details
 */
function getActiveNodeCopy(node: NodeToUpdate): NodeToUpdate {
    return {
        name: node.name,
        code: node.code,
        description: node.description,
    };
}

/**
 * Get the data to pass to the backend
 * @param node - the node details to pass to the backend
 * @returns the JSON to pass
 */
function getFormData(node: NodeToUpdate): string {
    return JSON.stringify({
        name: node.name,
        code: node.code,
        description: node.description,
    });
}

export const useUpdateNodeStore = defineStore("mcUpdateNode", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const activeNodeCopy = window.Vue.ref<NodeToUpdate>({ name: "", description: null, code: null }); // hold a copy of the changes, only update once we confirm the changes

    /**
     * to display a prompt to update a nodes details
     * @param node - node to update
     */
    function prompt(node: Node): void {
        useActiveNodeStore().set(node);
        activeNodeCopy.value = getActiveNodeCopy(node);
        isOpen.value = true;
    }

    /**
     * to hide the update details prompt
     */
    function cancel(): void {
        useActiveNodeStore().unset();
        isOpen.value = false;
    }

    /**
     * update the nodes details in the back end
     * @returns the id of the node and a message
     */
    async function update(): Promise<{ id: number; message: string }> {
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
            const responseJson = (await response.json()) as Node | { title: string };
            if (response.ok) {
                activeNodeStore.update(activeNodeCopy.value);
                isProcessing.value = false;
                cancel();
                return { id: activeNodeStore.node.id, message: "Outcome successfully updated" };
            }

            throw new Error((responseJson as { title: string }).title);
        } catch (error) {
            throw new Error((error as Error).message);
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
