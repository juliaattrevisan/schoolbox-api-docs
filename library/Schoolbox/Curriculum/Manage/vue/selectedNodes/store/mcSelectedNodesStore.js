/**
 * Manage the currently selected curriculum nodes
 */

import { defineStore } from 'pinia';
import { apiEndpoint } from '../../store/manageCurriculumStore';
import { useNodesStore, findById, getBreadcrumbs } from '../../store/mcNodesStore';
import { useEnableNodesStore } from './mcEnableNodesStore';


export const useSelectedNodesStore = defineStore('mcSelectedNodes', {

    state: () => ({
        nodes: [], //the currently selected nodes
        activeNode: null, //to show extra info on a selected code in a modal
        hasDisabledChildNodes: false,
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

        /**
         * check if all nodes are enabled so we can just show the disable button
         *
         * @return { boolean }
         */
        allNodesEnabled () {
            if (this.hasNodes) {
                const enabledNodes = this.nodes.filter((node) => node.enabled);
                return enabledNodes.length === this.nodes.length;
            } else {
                return false;
            }
        },

        /**
         * check if all nodes are disabled so we can just show the enable button
         * disabled child nodes cant be enabled - only the disabled parent can be enabled
         *
         * @return { boolean }
         */
        allNodesDisabled () {
            if (this.hasNodes) {
                const enabledNodes = this.nodes.filter((node) => !node.enabled);
                const nodesStore = useNodesStore();
                const noChildEnabledNodes = enabledNodes.filter((node) => { //prevent enabling child nodes of disabled parents
                    if (node.parent) { //could be the root level node, so won't have a parent
                        const parentNode = findById(nodesStore.nodes.children, node.parent.id);
                        return parentNode.enabled ? true : false;
                    } else {
                        return node.enabled ? false : true;
                    }
                });
                this.hasDisabledChildNodes = noChildEnabledNodes.length !== this.nodes.length ? true : false; //show a note in the the UI that enabling child nodes of disabled parents is not possible
                return noChildEnabledNodes.length === this.nodes.length;
            } else {
                return false;
            }
        },

        /**
         * To show further info on a selected node
         *
         * @return { Boolean }
         */
        hasActiveNode () {
            return this.activeNode !== null ? true : false;
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

        /**
         * set the active node, which would prompt the mc-active-selected-code-modal to open
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
         * Update the .enabled status on eac node and all its children
         *
         * @param { object }
         */
        enableNodes () {
            const enabled = useEnableNodesStore().enabled;
            this.nodes.forEach((node) => this.enableAllChildren(node, enabled)); //set the enabled on each node
            this.empty();
        },

        /**
         * Loop through recursively and update all
         * children to the parents updated enabled state
         *
         * @param { array }
         * @param { boolean }
         */
        enableAllChildren (node, enabled) {
            node.enabled = enabled;
            node.children?.forEach((innerNode) => {
                this.enableAllChildren(innerNode, enabled);
            });
        },

        /**
         * Delete all the seleced nodes from the tree structure
         */
        deleteNodes () {
            const nodesStore = useNodesStore();
            this.nodes.forEach((node) => nodesStore.deleteNode(node)); //delete each node from the tree
            this.empty();
        },

    },

})

