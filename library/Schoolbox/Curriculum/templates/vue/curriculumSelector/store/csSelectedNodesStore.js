/**
 * Manage the currently selected curriculum nodes
 */

import { defineStore } from "pinia";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";

export const useSelectedNodesStore = defineStore(`csSelectedNodes`, {
    state: () => ({
        nodes: [], //the currently selected nodes
        activeNode: { id: UNSET_ID }, //to show extra info on a selected node in a modal
        isLoading: false, //to display loading note
        hasLoadError: false, //to display loading error
    }),

    getters: {
        /**
         * Do we have any selected nodes?
         *
         * @return { Boolean }
         */
        hasNoNodes() {
            return this.nodes.length === 0;
        },

        /**
         * Do we have any selected nodes?
         *
         * @return { Boolean }
         */
        hasNodes() {
            return this.nodes.length > 0;
        },

        /**
         * To show further info on a selected node
         *
         * @return { Boolean }
         */
        hasActiveNode() {
            return this.activeNode.id !== UNSET_ID ? true : false;
        },

        /**
         * just return an array of ids, used to compare if a node is selected or not
         *
         * @return { array }
         */
        nodesIds() {
            return this.nodes.map((node) => node.id);
        },
    },

    actions: {
        /**
         * add a node
         *
         * @param { Object } a single node
         */
        add(nodeToAdd) {
            this.nodes = [...this.nodes, nodeToAdd];
        },

        /**
         * add a single node
         *
         * @param { Object or Array } add a single node, removing any previously selected
         */
        addSingle(nodeToAdd) {
            this.nodes = [nodeToAdd];
        },

        /**
         * remove a node
         *
         * @param { Object }
         */
        remove(nodeToRemove) {
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
        isSelected(node) {
            return this.nodesIds.includes(node.id);
        },

        /**
         * to switch a nodes selection status
         *
         * @param { object }
         * @param { boolean }
         */
        switch(node, isChecked) {
            isChecked ? this.add(node) : this.remove(node);
        },

        /**
         * completely update the nodes with a new array
         *
         * @param { Aray }
         */
        update(updatedNodes) {
            this.nodes = [...updatedNodes];
        },

        /**
         * clear all the currently selected nodes
         */
        empty() {
            this.nodes.forEach((node) => this.remove(node));
        },

        /**
         * set the active node, which would prompt the selected-node-modal to open
         *
         * @param { object }
         */
        setActiveNode(node) {
            this.activeNode = node;
        },

        /**
         * A node can have .children and .parent properties which aren't always needed
         * and will bloat the selected objects. As such we remove these properties
         * to slim down the object.
         *
         * @param { object }
         */
        trimNode(node) {
            const copiedNode = { ...node };
            delete copiedNode.children;
            delete copiedNode.parent;
            return copiedNode;
        },
    },
});
