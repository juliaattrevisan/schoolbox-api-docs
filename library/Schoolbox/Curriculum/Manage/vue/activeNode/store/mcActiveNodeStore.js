/**
 * Manage the currently selected curriculum node
 */

import { defineStore } from 'pinia';
import { useNodesStore, sortByName } from '../../store/mcNodesStore';
import { useViewNodesStore } from '../../viewNodes/store/mcViewNodesStore';


export const useActiveNodeStore = defineStore('mcActiveNode', {

    state: () => ({
        node: null, //the currently selected node
    }),

    getters: {

        /**
         * To show further info on a selected node
         *
         * @return { Boolean }
         */
        isActive () {
            return this.node !== null ? true : false;
        },

        /**
         * Add in the code if it's available otherwise just show the title
         *
         * @return { String }
         */
        title () {
            if (this.node === null) {
                return '';
            } else if (this.node.code && this.node.code !== '') {
                return `[${this.node.code}] ${this.node.name}`;
            } else {
                return this.node.name;
            }
        },

    },

    actions: {

        /**
         * set the node to perform an action on
         *
         * @param { Object }
         */
        set (node) {
            this.node = node;
        },

        /**
         * Set newly saved values on the node
         *
         * @param { Object }
         */
        update (node) {
            if (node.name !== this.node.name) {
                this.node.name = node.name;
                useNodesStore().nodeUpdated(this.node); //notify to prompt a sort
            }
            if (node.code !== this.node.code) {
                this.node.code = node.code;
            }
            if (node.description !== this.node.description) {
                this.node.description = node.description;
            }
        },

        /**
         * Add in a new subsection to the active node
         *
         * @param { object }
         */
        async addSubsection (newNode) {
            if (this.node.children === null) { //if the children aren't loaded, load them in - which will also return the newly added node
                await useNodesStore().loadChildren(this.node.id);
            } else { //otherwise we need to push the newNode in as the children are already loaded
                this.node.children.push(newNode);
                sortByName(this.node.children); //and sort again with the new node added
            }
            const viewNodesStore = useViewNodesStore();
            if (viewNodesStore.current.id !== newNode.parent.id) { //if we aren't on the new nodes level move down so we can see the new node
                viewNodesStore.setNextSelected(newNode.parent.id);
            }
        },

        /**
         * unset ready for another use
         *
         * @param { Object }
         */
        unset () {
            this.node = null;
        },

    },

})