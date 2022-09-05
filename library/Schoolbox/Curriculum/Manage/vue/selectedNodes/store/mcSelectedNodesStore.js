/**
 * Manage the currently selected curriculum nodes
 */

import { defineStore } from 'pinia';
import { useNodesStore, findById } from '../../store/mcNodesStore';


export const useSelectedNodesStore = defineStore('mcSelectedNodes', {

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
         * Return the single or plural depending on node selection
         *
         * @return { string }
         */
        countWithName () {
            return `${this.count} ${this.singleOrPlural}`;
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
                this.updateIsSelected(nodeToAdd, true);
                this.nodes = this.removeChildNodes(nodeToAdd);
                this.nodes  = [...this.nodes, nodeToAdd];
            }
        },

        /**
         * we need to check if a new parent has been selected of any existing children
         * if it has the children should be removed as the parent now takes precedence
         *
         * @param { Object }
         */
        removeChildNodes (nodeToAdd) {
            const withoutChildrenOfNewSelection = this.nodes.filter((selectedNode) => {
                if (nodeToAdd.children) { //only if the children have loaded
                    const node = findById(nodeToAdd.children, selectedNode.id);
                    return node !== undefined ? false : true; //only keep those nodes that aren't a child of the new node
                } else { //otherwise no existing nodes will be children, as they haven't been loaded and so couldn't have been selected
                    return true;
                }
            });
            return withoutChildrenOfNewSelection;
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
                this.updateIsSelected(nodeToRemove, false);
                this.nodes = this.nodes.filter((node) => node.id !== nodeToRemove.id);
            }
        },

        /**
         * to track whether a node is selected (with mc-view-node-checkbox)
         * we add an additional property to each node
         *
         * @param { object }
         */
        addIsSelected (node) {
            if (node.isSelected === undefined) {
                const nodesStore = useNodesStore();
                const parentNode = findById(nodesStore.nodes.children, node.parent?.id);
                if (parentNode) {
                    node.isSelected = parentNode.isSelected;
                } else {
                    node.isSelected = false;
                }
            }
        },

        /**
         * Loop through and update all children to the parents
         * updated isSelected state
         *
         * @param { array }
         * @param { boolean }
         */
        updateIsSelected (node, isSelected) {
            node.isSelected = isSelected;
            node.children?.forEach((innerNode) => {
                this.updateIsSelected(innerNode, isSelected);
            });
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
            this.nodes.forEach((node) => this.remove(node));
        },

    },

})

