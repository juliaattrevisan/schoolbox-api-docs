/**
 * Add a new curriculum
 */

import { ref } from 'vue';
import { defineStore } from 'pinia';
import { useStore } from '@schoolboxCurriculum/vue/manageCurriculum/store/manageCurriculumStore';
import { useNodesStore } from '@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore';
import { useViewNodesStore } from '@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore';
import Node from '@schoolboxCurriculum/types/Node';

const UNSET_ID = -1;

/**
 * Pass empty fields to be added
 *
 * @returns { Node } - a fresh node to use
 */
function getCurriculum() {
    return {
        name: '',
        code: '',
        description: '',
    };
}

/**
 * Pass through the group id
 *
 * @param { Node } node - the node with the fields to update
 * @returns { string } - the data to send to the backend
 */
function getFormData(node: Node) {
    return JSON.stringify({
        name: node.name,
        code: node.code,
        description: node.description,
    });
}

export const useAddCurriculumStore = defineStore('mcAddCurriculum', () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const curriculum = ref<Node | null>(null);
    const id = ref(UNSET_ID); // the id of the last updated node

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
     */
    async function add() {
        try {
            isProcessing.value = true;
            const response = await fetch(`${useStore().getApiEndpoint()}/`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: getFormData(curriculum.value),
            });
            const responseJson: Node = (await response.json()) as Node;
            if (response.ok) {
                const newCurriculum: Node = responseJson;
                id.value = newCurriculum.id;
                const nodesStore = useNodesStore();
                nodesStore.addNewCurriculum(newCurriculum);
                useViewNodesStore().setSelected(0);
                isProcessing.value = false;
                cancel();
                return { id: newCurriculum.id, message: 'Curriculum successfully added' };
            }
            throw new Error(responseJson.title, { ...responseJson });
        } catch (error) {
            throw new Error(error.message, error.options);
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
        curriculum,
        id,
        prompt,
        add,
        cancel,
        unsetId,
    };
});
