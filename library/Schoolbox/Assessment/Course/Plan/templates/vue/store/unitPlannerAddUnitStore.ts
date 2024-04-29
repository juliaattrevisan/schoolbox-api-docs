/**
 * Add a new curriculum
 */

import { defineStore } from "pinia";
import { useUnitPlannerStore } from "./unitPlannerStore";
// import { useNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore";
// import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
// import type { Node } from "@schoolboxCurriculum/types/Node";

// const UNSET_ID = -1;

/**
 * Get the data to pass to the backend
 * @param { Node } node - the node with the fields to update
 * @returns { string } - the data to send to the backend
 */
// function getFormData(node: Partial<Node>) {
//     return JSON.stringify({
//         name: node.name,
//         code: node.code,
//         description: node.description,
//     });
// }

export const useAddUnitStore = defineStore("unitPlannerAddUnit", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const name = window.Vue.ref(""); // the name of the new unit

    /**
     * to display a prompt to add a curriculum
     */
    function prompt() {
        isOpen.value = true;
    }

    /**
     * to hide the add prompt
     */
    function cancel() {
        isOpen.value = false;
        name.value = "";
    }

    /**
     * pass the new unit details to the backend
     */
    async function add() {
        try {
            isProcessing.value = true;
            const response = await fetch(`course/${useUnitPlannerStore().courseId}/unitspecs/new`, {
                method: "POST",
                // headers: {
                //     "Content-Type": "application/json",
                // },
                body: getFormData(curriculum.value as Partial<Node>),
            });
            const responseJson = await response.json();
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
            // throw new Error(responseJson.title, { ...responseJson });
        } catch (error) {
            // throw new Error(error.message, error.options);
        }
    }

    /**
     * to unset the id, called when the toast has already been displayed once
     */
    // function unsetId() {
    //     id.value = UNSET_ID;
    // }

    return {
        isProcessing,
        isOpen,
        // curriculum,
        // id,
        name,
        prompt,
        add,
        cancel,
        // unsetId,
    };
});
