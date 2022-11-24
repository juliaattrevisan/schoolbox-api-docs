/**
 * Handles the store add a new subsection to an existing node
 */

import { ref } from 'vue';
import { defineStore } from 'pinia';
import { useStore } from '../../store/manageCurriculumStore';
import { useActiveNodeStore } from './mcActiveNodeStore';
import { useSelectedNodesStore } from '../../selectedNodes/store/mcSelectedNodesStore';

const UNSET_ID = -1;

export const useAddSubsectionStore = defineStore('mcAddSubsection', () => {
    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const subsection = ref({});
    const id = ref(UNSET_ID); // the id of the last updated node

    /**
     * to display a prompt to confirm adding a node to a subsection
     *
     * @param { object }
     */
    function prompt(node) {
        useActiveNodeStore().set(node);
        subsection.value = getSubsection();
        isOpen.value = true;
    }

    /**
     * pass the new subsections details to the backend
     */
    async function add() {
        const activeNodeStore = useActiveNodeStore();
        try {
            isProcessing.value = true;
            const response = await fetch(`${useStore().getApiEndpoint()}/${activeNodeStore.node.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: getFormData(subsection.value, activeNodeStore.node),
            });
            const responseJson = await response.json();
            if (response.ok) {
                const newNode = responseJson;
                id.value = newNode.id;
                useSelectedNodesStore().checkIfSelected(newNode); //the parent node could be selected, so this node will need to be selected as well
                await activeNodeStore.addSubsection(newNode);
                cancel();
                return { id: newNode.id, message: 'Subsection successfully added' };
            } else {
                throw new Error(responseJson.title, { ...responseJson });
            }
        } catch (error) {
            throw new Error(error.message, error.options);
        } finally {
            isProcessing.value = false;
        }
    }

    /**
     * to hide the confirmation prompt
     */
    function cancel() {
        useActiveNodeStore().unset();
        isOpen.value = false;
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
        cancel,
        unsetId,
    };
});

/**
 * Pass empty fields to be added
 *
 * @returns { Object }
 */
function getSubsection() {
    return {
        name: '',
        code: '',
        description: '',
    };
}

/**
 * Pass through the subsection and whether the node is enabled
 *
 * @param { object }
 * @param { object }
 *
 * @returns { FormData }
 */
function getFormData(subsection, node) {
    return JSON.stringify({
        name: subsection.name,
        code: subsection.code,
        description: subsection.description,
        enabled: node.enabled,
    });
}
