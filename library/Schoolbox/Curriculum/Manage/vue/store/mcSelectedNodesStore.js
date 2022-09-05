/**
 * Manage the currently selected curriculum nodes
 */

import { defineStore } from 'pinia';


export const useSelectedNodesStore = defineStore('csSelectedNodesStore', {

    state: () => ({
        nodes: [], //the currently selected nodes
    }),

    getters: {

        /**
         * Do we have any selected nodes?
         *
         * @return { Boolean }
         */
        hasNoNodes () {
            return this.nodes.length === 0;
        },

        /**
         * Do we have any selected nodes?
         *
         * @return { Boolean }
         */
        hasNodes () {
            return this.nodes.length > 0;
        },

    },

    actions: {

        /**
         * add a node
         *
         * @param { Object or Array } either a single node, or mulitple can be added
         */
        add (nodeToAdd) {
            if (Array.isArray(nodeToAdd)) {
                this.nodes  = [...this.nodes, ...nodeToAdd];
            } else {
                this.nodes  = [...this.nodes, nodeToAdd];
            }
        },

        /**
         * remove a node
         *
         * @param { Object }
         */
        remove (nodeToRemove) {
            if (Array.isArray(nodeToRemove)) {
                nodeToRemove.forEach((node) => {
                    this.nodes = this.nodes.filter((selectedCode) => selectedCode.id !== node.id);
                });
            } else {
                this.nodes = this.nodes.filter((node) => node.id !== nodeToRemove.id);
            }
        },

        /**
         * completely update the nodes with a new array
         *
         * @param { Aray }
         */
        update (updatedNodes) {
            this.nodes  = [...updatedNodes];
        },

        /**
         * clear all the currently selected nodes
         */
        empty () {
            this.nodes  = [];
        },

    },

})