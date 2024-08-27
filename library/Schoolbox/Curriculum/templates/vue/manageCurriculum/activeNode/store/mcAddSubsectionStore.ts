/**
 * Handles the store add a new subsection to an existing node
 */

import { defineStore } from "pinia";
import { useActiveNodeStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcActiveNodeStore";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/selectedNodes/store/mcSelectedNodesStore";
import type { Node } from "@schoolboxCurriculum/types/Node";

const UNSET_ID = -1;

/**
 * Pass empty fields to be added
 * @returns { Partial<Node> } - a new Node to add
 */
function getSubsection(): Partial<Node> {
    return {
        name: "",
        code: "",
        description: "",
    };
}

/**
 * Pass through the subsection and whether the node is enabled
 * @param { Partial<Node> } subsection - the new subsection data
 * @param { Node } node - the parent Node
 * @returns { string } - the data to send to the backend
 */
function getFormData(subsection: Partial<Node>, node: Node) {
    return JSON.stringify({
        name: subsection.name,
        code: subsection.code,
        description: subsection.description,
        enabled: node.enabled,
    });
}

export const useAddSubsectionStore = defineStore("mcAddSubsection", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const subsection = window.Vue.ref<Partial<Node>>(getSubsection());
    const id = window.Vue.ref(UNSET_ID); // the id of the last updated node

    /**
     * to display a prompt to confirm adding a node to a subsection
     * @param { Node } node - the node being added
     */
    function prompt(node: Node) {
        useActiveNodeStore().set(node);
        subsection.value = getSubsection();
        isOpen.value = true;
    }

    /**
     * to hide the confirmation prompt
     */
    function cancel() {
        useActiveNodeStore().unset();
        isOpen.value = false;
    }

    /**
     * pass the new subsections details to the backend
     * @returns { Promise<{ id: number, message: string }> } - the id of the new node and a message to display
     */
    async function add(): Promise<{ id: number; message: string }> {
        const activeNodeStore = useActiveNodeStore();
        try {
            isProcessing.value = true;
            const response = await fetch(activeNodeStore.node._links.update, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: getFormData(subsection.value, activeNodeStore.node),
            });
            const responseJson = (await response.json()) as Node | { title: string };
            if (response.ok) {
                const newNode: Node = responseJson as Node;
                id.value = newNode.id;
                useSelectedNodesStore().checkIfSelected(newNode); // the parent node could be selected, so this node will need to be selected as well
                await activeNodeStore.addSubsection(newNode);
                cancel();
                return { id: newNode.id, message: "Subsection successfully added" };
            }

            throw new Error((responseJson as { title: string }).title);
        } catch (error) {
            throw new Error((error as Error).message);
        } finally {
            isProcessing.value = false;
        }
    }

    /**
     * to unset the id, called when the toast has already been displayed once
     */
    function unsetId() {
        id.value = UNSET_ID;
    }

    return {
        isProcessing,
        isOpen,
        subsection,
        prompt,
        add,
        id,
        cancel,
        unsetId,
    };
});
