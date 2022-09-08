/**
 * Add a new curriculum
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from './manageCurriculumStore';
import { useNodesStore } from './mcNodesStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';


const UNSET_ID = -1;


export const useAddCurriculumStore = defineStore('mcAddCurriculum', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
        curriculum: {},
        id: UNSET_ID, //the id of the last updated node
    }),

    actions: {

        /**
         * to display a prompt to add a curriculum
         */
        prompt () {
            this.curriculum = getCurriculum();
            this.isOpen = true;
        },

        /**
         * pass the new curriculum details to the backend
         */
        async add () {
            this.isProcessing = true;
            try {
                this.isProcessing = false;
                const response = await fetch(`${apiEndpoint}/`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this.curriculum),
                });
                if (response.ok) {
                    const newCurriculum = await response.json();
                    this.id = newCurriculum.id;
                    const nodesStore = useNodesStore();
                    nodesStore.addNewCurriculum(newCurriculum);
                    useViewNodesStore().reset();
                    this.isProcessing = false;
                    this.cancel();
                    return newCurriculum.id;
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * to hide the add prompt
         */
        cancel () {
            this.isOpen = false;
        },

        /**
         * to unset the id, called when the toast has already been displayed once
         */
        unsetId () {
            this.id = UNSET_ID;
        },

    },

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
