/**
 * Manage the currently selected curriculum nodes
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from './curriculumSelectorStore';
import { getBreadcrumbs } from '../../../js/nodeUtils';


export const useSelectedNodesStore = defineStore(`csSelectedNodes`, {

    state: () => ({
        nodes: [], //the currently selected nodes
        activeNode: null, //to show extra info on a selected node in a modal
        isLoading: false, //to display loading note
        hasLoadError: false, //to display loading error
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
         * To show further info on a selected node
         *
         * @return { Boolean }
         */
        hasActiveNode () {
            return this.activeNode !== null ? true : false;
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
                    this.nodes = this.nodes.filter((selectedNode) => selectedNode.id !== node.id);
                });
            } else {
                this.nodes = this.nodes.filter((node) => node.id !== nodeToRemove.id);
            }
        },

        /**
         * to check if a node is selected
         *
         * @param { Object }
         *
         * @returns { boolean }
         */
        isSelected (node) {
            return this.nodesIds.includes(node.id);
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
            this.nodes.forEach((node) => this.remove(node));
        },

        /**
         * set the active node, which would prompt the cs-active-selected-node-modal to open
         * and if it's set to null we can listen for it to be set again
         * We also load the ancestors of the node to display the breadcrumbs in the modal
         *
         * @param { Object }
         */
        async setActiveNode (node) {
            if (node === null) { //we only need to grab the nodes breadcrumbs if there's actually a node
                this.activeNode = node;
                return;
            }
            try {
                this.isLoading = true;
                const response = await fetch(`${apiEndpoint}/${node.id}?ancestors=99`); //just use 99 as hopefully there's no tree that deep...
                if (response.ok) {
                    const nodeWithParents = await response.json();
                    node.breadcrumbs = getBreadcrumbs(nodeWithParents, []);
                } else {
                    console.warn(response);
                    this.hasLoadError = true;
                }
            } catch (error) {
                console.warn(error);
                this.hasLoadError = true;
            } finally {
                this.isLoading = false;
            }
            this.activeNode = node;
        },

        /**
         * A node can have .children and .parent properties which aren't always needed
         * and will bloat the selected objects. As such we remove these properties
         * to slim down the object.
         *
         * @param { Object }
         */
         trimNode (node) {
            const copiedNode = { ...node };
            delete copiedNode.children;
            delete copiedNode.parent;
            return copiedNode;
        },

    },

})