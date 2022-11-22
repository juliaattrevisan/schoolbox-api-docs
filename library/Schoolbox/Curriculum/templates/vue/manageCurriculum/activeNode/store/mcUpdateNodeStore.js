/**
 * Handles the store to disband the group
 */

import { ref } from 'vue';
import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useActiveNodeStore } from './mcActiveNodeStore';


export const useUpdateNodeStore = defineStore('mcUpdateNode', () => {

    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const activeNodeCopy = ref({}); // hold a copy of the changes, only update once we confirm the changes

    /**
     * to display a prompt to update a nodes details
     *
     * @param { object }
     */
    function prompt (node) {
        useActiveNodeStore().set(node);
        activeNodeCopy.value = getActiveNodeCopy(node);
        isOpen.value = true;
    }

    /**
     * update the nodes details in the back end
     *
     * @param { object or null }
     */
    async function update () {
        try {
            isProcessing.value = true;
            const activeNodeStore = useActiveNodeStore();
            const nodeId = activeNodeStore.node.id;
            const response = await fetch(`${apiEndpoint}/${nodeId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: getFormData(activeNodeCopy.value),
            });
            const responseJson = await response.json();
            if (response.ok) {
                activeNodeStore.update(activeNodeCopy.value);
                isProcessing.value = false;
                cancel();
                return { id: nodeId, message: 'Node successfully updated' };
            } else {
                throw new Error(responseJson.title, { ...responseJson } );
            }
        } catch (error) {
            throw new Error(error.message, error.options);
        }
    }

    /**
     * to hide the update details prompt
     */
    function cancel () {
        useActiveNodeStore().unset();
        isOpen.value = false;
    }

    return {
        isProcessing,
        isOpen,
        activeNodeCopy,
        prompt,
        update,
        cancel,
    }

})

/**
 * Create a copy so the data is only updated once actually saved
 *
 * @param { Object }
 *
 * @returns { Object }
 */
 function getActiveNodeCopy (node) {
    return {
        name: node.name,
        code: node.code,
        description: node.description,
    }
}

/**
 * Pass through the group id
 *
 * @returns { FormData }
 */
 function getFormData (node) {
    return JSON.stringify({
        name: node.name,
        code: node.code,
        description: node.description,
    });
}
