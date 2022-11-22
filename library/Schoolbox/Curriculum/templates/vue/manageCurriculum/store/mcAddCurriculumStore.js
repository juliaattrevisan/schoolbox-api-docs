/**
 * Add a new curriculum
 */

import { ref } from 'vue';
import { defineStore } from 'pinia';
import { apiEndpoint } from './manageCurriculumStore';
import { useNodesStore } from './mcNodesStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';


const UNSET_ID = -1;


export const useAddCurriculumStore = defineStore('mcAddCurriculum', () => {

    const isProcessing = ref(false);
    const isOpen = ref(false); // to notify that the prompt is opened / closed
    const curriculum = ref({});
    const id = ref(UNSET_ID); // the id of the last updated node

    /**
     * to display a prompt to add a curriculum
     */
    function prompt () {
        curriculum.value = getCurriculum();
        isOpen.value = true;
    }

    /**
     * pass the new curriculum details to the backend
     */
    async function add () {
        try {
            isProcessing.value = true;
            const response = await fetch(`${apiEndpoint}/`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: getFormData(curriculum.value),
            });
            const responseJson = await response.json();
            if (response.ok) {
                const newCurriculum = responseJson;
                id.value = newCurriculum.id;
                const nodesStore = useNodesStore();
                nodesStore.addNewCurriculum(newCurriculum);
                useViewNodesStore().setSelected(0);
                isProcessing.value = false;
                cancel();
                return { id: newCurriculum.id, message: 'Curriculum successfully added' };
            } else {
                throw new Error(responseJson.title, { ...responseJson } );
            }
        } catch (error) {
            throw new Error(error.message, error.options);
        }
    }

    /**
     * to hide the add prompt
     */
    function cancel () {
        isOpen.value = false;
    }

    /**
     * to unset the id, called when the toast has already been displayed once
     */
    function unsetId () {
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
    }

})

/**
 * Pass empty fields to be added
 *
 * @param { Object }
 *
 * @returns { Object }
 */
 function getCurriculum (node) {
    return {
        name: '',
        code: '',
        description: '',
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
