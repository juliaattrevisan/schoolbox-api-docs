/**
 * Manage the currently selected curriculum nodes
 */

import { defineStore } from 'pinia';


export const useSelectedNodesStore = defineStore('csSelectedNodes', {

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

        /**
         * Number of nodes
         *
         * @return { number }
         */
        count () {
            return this.nodes.length;
        },

        /**
         * Return the single or plural depending on node selection
         *
         * @return { string }
         */
        singleOrPlural () {
            return this.count > 1 ? 'nodes' : 'node';
        },

        /**
         * just return an array of ids, used to compare if a node is selected or not
         *
         * @return { array }
         */
        nodesIds () {
            return this.nodes.map((node) => node.id);
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
         * to switch a nodes selection status
         *
         * @param { object }
         * @param { boolean }
         */
        switch (node, isChecked) {
            isChecked ? this.add(node) : this.remove(node);
        },

        /**
         * clear all the currently selected nodes
         */
        empty () {
            this.nodes  = [];
        },

    },

})