/**
 * Manages the currently selected nodes relevant to the current modal session
 */

import { defineStore } from "pinia";
import { useSelectedNodesStore } from "./csSelectedNodesStore";

export const useModalSelectedNodesStore = defineStore(`csModalSelectedNodes`, {
    state: () => ({
        nodes: [], //the currently selected nodes in the current modal session, with any previously selected also considered
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
         * populate with the currently selected nodes
         */
        refresh() {
            this.nodes = useSelectedNodesStore().nodes; //ensure current selections are reflected in the new modal session
        },

        /**
         * add a node
         *
         * @param { Object }
         */
        add(nodeToAdd) {
            this.nodes = [...this.nodes, nodeToAdd];
        },

        /**
         * to only add a single node, removing any previous selections
         *
         * @param { Object }
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
            this.nodes = this.nodes.filter((node) => node.id !== nodeToRemove.id);
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
         * clear all the currently selected nodes
         */
        empty() {
            this.nodes.forEach((node) => this.remove(node));
        },

        /**
         * update the currently selected with the latest modal session
         */
        update() {
            useSelectedNodesStore().update(this.nodes);
        },
    },
});
