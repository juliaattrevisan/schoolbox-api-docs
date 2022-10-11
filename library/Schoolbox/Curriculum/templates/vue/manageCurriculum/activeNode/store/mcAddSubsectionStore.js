/**
 * Handles the store add a new subsection to an existing node
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useActiveNodeStore } from './mcActiveNodeStore';
import { useSelectedNodesStore } from '../../selectedNodes/store/mcSelectedNodesStore';


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
            try {
                this.isProcessing = true;
                const response = await fetch(`${apiEndpoint}/${activeNodeStore.node.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this.subsection, activeNodeStore.node),
                });
                const responseJson = await response.json();
                if (response.ok) {
                    const newNode = responseJson;
                    this.id = newNode.id;
                    useSelectedNodesStore().checkIfSelected(newNode); //the parent node could be selected, so this node will need to be selected as well
                    await activeNodeStore.addSubsection(newNode);
                    this.cancel();
                    return { id: newNode.id, message: 'Subsection successfully added' };
                } else {
                    throw new Error(responseJson.title, { ...responseJson } );
                }
            } catch (error) {
                throw new Error(error.message, error.options);
            } finally {
                this.isProcessing = false;
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
 * @returns { Object }
 */
 function getSubsection () {
    return {
        name: '',
        code: '',
        description: '',
    }
}

/**
 * Pass through the subsection and whether the node is enabled
 *
 * @param { object }
 * @param { object }
 *
 * @returns { FormData }
 */
 function getFormData (subsection, node) {
    return JSON.stringify({
        name: subsection.name,
        code: subsection.code,
        description: subsection.description,
        enabled: node.enabled,
    });
}
