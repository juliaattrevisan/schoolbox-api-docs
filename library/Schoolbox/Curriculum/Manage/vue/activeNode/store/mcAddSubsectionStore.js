/**
 * Handles the store add a new subsection to an existing node
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useActiveNodeStore } from './mcActiveNodeStore';


const UNSET_ID = -1;


export const useAddSubsectionStore = defineStore('mcAddSubsection', {

    state: () => ({
        isProcessing: false,
        isOpen: false, //to notify that the prompt is opened / closed
        subsection: {},
        id: UNSET_ID, //the id of the last updated node
    }),

    actions: {

        /**
         * to display a prompt to confirm adding a node to a subsection
         *
         * @param { object }
         */
        prompt (node) {
            const activeNodeStore = useActiveNodeStore();
            activeNodeStore.set(node);
            this.subsection = getSubsection();
            this.isOpen = true;
        },

        /**
         * pass the new subsections details to the backend
         */
        async add () {
            const activeNodeStore = useActiveNodeStore();
            this.isProcessing = true;
            try {
                this.isProcessing = false;
                const response = await fetch(`${apiEndpoint}/${activeNodeStore.node.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this.subsection),
                });
                if (response.ok) {
                    const newNode = await response.json();
                    this.id = newNode.id;
                    await activeNodeStore.addSubsection(newNode);
                    this.isProcessing = false;
                    this.cancel();
                    return newNode.id;
                } else {
                    throw new Error();
                }
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * to hide the confirmation prompt
         */
        cancel () {
            const activeNodeStore = useActiveNodeStore();
            activeNodeStore.unset();
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
 function getSubsection (node) {
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
