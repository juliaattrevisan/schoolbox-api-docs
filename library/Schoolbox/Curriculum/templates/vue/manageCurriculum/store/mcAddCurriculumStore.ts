/**
 * Add a new curriculum
 */

import { defineStore } from "pinia";
import { useStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/manageCurriculumStore";
import { useNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore";
import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
import type { Node } from "@schoolboxCurriculum/types/Node";

const UNSET_ID = -1;

/**
 * Pass empty fields to be added
 * @returns { Partial<Node> } - a new Node to add
 */
function getCurriculum(): Partial<Node> {
    return {
        name: "",
        code: "",
        description: "",
    };
}

/**
 * Get the data to pass to the backend
 * @param { Node } node - the node with the fields to update
 * @returns { string } - the data to send to the backend
 */
function getFormData(node: Partial<Node>): string {
    return JSON.stringify({
        name: node.name,
        code: node.code,
        description: node.description,
    });
}

export const useAddCurriculumStore = defineStore("mcAddCurriculum", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const curriculum = window.Vue.ref<Partial<Node>>(getCurriculum());
    const id = window.Vue.ref(UNSET_ID); // the id of the last updated node

    /**
     * to display a prompt to add a curriculum
     */
    function prompt() {
        curriculum.value = getCurriculum();
        isOpen.value = true;
    }

    /**
     * to hide the add prompt
     */
    function cancel() {
        isOpen.value = false;
    }

    /**
     * pass the new curriculum details to the backend
     * @returns { Promise<{ id: number; message: string }> } - the id of the new curriculum and a message
     */
    async function add(): Promise<{ id: number; message: string }> {
        isProcessing.value = true;
        const response = await fetch(`${useStore().getApiEndpoint()}/`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: getFormData(curriculum.value as Partial<Node>),
        });

        const responseJson = (await response.json()) as Node | { title: string };

        if (response.ok) {
            const newCurriculum: Node = responseJson as Node;
            id.value = newCurriculum.id;
            const nodesStore = useNodesStore();
            nodesStore.addNewCurriculum(newCurriculum);
            useViewNodesStore().setSelected(0);
            isProcessing.value = false;
            cancel();
            return { id: newCurriculum.id, message: "Curriculum successfully added" };
        }

        // Throw the error message. Note handling the API response like this is not ideal and requires type casts...
        const responseErrorInfo = responseJson as { title: string };
        throw new Error(responseErrorInfo.title);
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
        curriculum,
        id,
        prompt,
        add,
        cancel,
        unsetId,
    };
});
