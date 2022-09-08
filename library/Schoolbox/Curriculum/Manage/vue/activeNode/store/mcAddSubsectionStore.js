/**
 * Handles the store to disband the group
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useNodesStore } from '../../store/mcNodesStore';
import { useActiveNodeStore } from './mcActiveNodeStore';
import { useViewNodesStore } from '../../viewNodes/store/mcViewNodesStore';


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
            this.isProcessing = true;
            try {
                this.isProcessing = false;
                const response = await fetch(`${apiEndpoint}/${useActiveNodeStore().node.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: getFormData(this.subsection),
                });
                if (response.ok) {
                    const newNode = await response.json();
                    this.id = newNode.id;
                    await this.addSubsection(newNode);
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
         * Add in a new subsection to the active node
         *
         * @param { object }
         */
        async addSubsection (newNode) {
            const activeNodeStore = useActiveNodeStore();
            const nodesStore = useNodesStore();
            if (activeNodeStore.node.children === null) { //if the children aren't loaded, load them in - which will also return the newly added node
                await nodesStore.loadChildren(activeNodeStore.node.id);
            } else { //otherwise we need to push the newNode in as the children are already loaded
                /**
                 * @TODO - place in sorted alphabetically
                 */
                activeNodeStore.node.children.push(newNode);
            }
            const viewNodesStore = useViewNodesStore();
            if (viewNodesStore.current.id !== newNode.parent.id) { //if we aren't on the new nodes level move down so we can see the new node
                viewNodesStore.setNextSelected(newNode.parent.id);
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
