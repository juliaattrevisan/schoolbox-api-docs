/**
 * Manages the currently visible nodes in mc-modal-nodes,
 * handling the traversal of the node tree and the breadcrumbs
 */

import { defineStore } from "pinia";
import { useNodesStore } from "./csAllNodesStore";
import { UNSET_ID, findById } from "@schoolboxCurriculum/js/nodeUtils";
import { useModalSearchStore } from "./csModalSearchStore";

export const DIRECTION = {
    FORWARDS: "forwards",
    BACKWARDS: "backwards",
};

export const useModalViewNodesStore = defineStore(`csModalViewNodes`, {
    state: () => ({
        idList: [], //ids of the selected nodes
        currentDepth: 0, //track how far down the tree we are
        currentDirection: DIRECTION.FORWARDS, //which direction we are currently travelling in the tree
        hasStartingNode: false, //whether we have a starting node to begin with
        startingNodeId: null, //the id of the starting node
    }),

    getters: {
        /**
         * Can now use the search input when we have selected at least one node
         *
         * @return { Boolean }
         */
        isActive() {
            return this.idList.length > 0;
        },

        /**
         * to traverse back through the nodes via breadcrumbs
         *
         * @return { Array }
         */
        breadcrumbs() {
            let breadcrumbs = [];
            const nodesStore = useNodesStore();
            if (!nodesStore.hasSingleCurriculum) {
                //we only need this breadcrumb if there's more than one Curriculum, otherwise we jump straight to the single node with no option of returning back
                breadcrumbs.push({ id: nodesStore.nodes.id, name: nodesStore.nodes.name, depth: 0 });
            }
            this.idList.forEach((id, index) => {
                //add in a breadcrumb for each selected node
                const nodeAtDepth = findById(nodesStore.nodes.children, id);
                breadcrumbs.push({ id: id, name: nodeAtDepth.name, depth: index + 1 });
            });
            // remove any breadcrumbs that are before the starting node id, so the user can't go back further than the starting node
            if (this.hasStartingNode) {
                const indexOfStartingNode = breadcrumbs.findIndex(
                    (breadcrumb) => breadcrumb.id === this.startingNodeId,
                );
                breadcrumbs = breadcrumbs.filter((_, index) => index >= indexOfStartingNode);
            }
            //we return an empty array if there's no selections yet, otherwise return the breadcrumbs
            return breadcrumbs.length === 0 ? [] : breadcrumbs;
        },

        /**
         * the current node selection
         *
         * @returns { Object }
         */
        current() {
            const nodesStore = useNodesStore();
            if (this.idList.length > 0) {
                //grab the current latest selection
                return findById(nodesStore.nodes.children, this.idList[this.currentDepth - 1]);
            } else {
                //otherwise return the root level structure when there's more than one root level node
                return nodesStore.nodes;
            }
        },

        /**
         * the current nodes to display, which are either just the current selection
         * or when the search is active the results found within the current selection
         *
         * @returns { Array }
         */
        nodes() {
            const modalSearchStore = useModalSearchStore();
            if (modalSearchStore.isActive) {
                return modalSearchStore.results;
            } else {
                return [this.current];
            }
        },
    },

    actions: {
        /**
         * There's been a change in node selection,
         * so update based on the change in depth
         *
         * @param { number }
         * @param { number }
         */
        setSelected(depth, id) {
            useModalSearchStore().clear(); //clear the search results when traversing
            if (depth === 0) {
                //pulling back to no node selection at all, the root level
                this.reset();
            } else if (depth > this.currentDepth) {
                //progressing to the next depth
                this.toNext(id);
            } else if (depth < this.currentDepth) {
                //or going back to a previous depth
                this.toPrevious(depth);
            }
            this.currentDepth = depth;
        },

        /**
         * Reset to the original state, with no active selection at all
         */
        reset() {
            this.currentDirection = DIRECTION.BACKWARDS;
            this.idList = [];
            this.currentDepth = 0;
        },

        /**
         * Add one more selection
         *
         * @param { number }
         */
        toNext(id) {
            this.currentDirection = DIRECTION.FORWARDS;
            this.idList = [...this.idList, id]; //add the new id in
        },

        /**
         * Move back to a previous node
         *
         * @param { number }
         */
        toPrevious(targetDepth) {
            this.currentDirection = DIRECTION.BACKWARDS;
            this.idList = this.idList.filter((id, index) => index < targetDepth); //shorten the list back to the new reduced depth
        },

        /**
         * Move one depth down the tree
         *
         * @param { number }
         */
        async setNextSelected(id) {
            const nodesStore = useNodesStore();
            await nodesStore.loadChildren(id);
            this.setSelected(this.currentDepth + 1, id);
        },

        /**
         * To traverse to a specified id, regardless of where it is
         * in the current tree structure
         *
         * @param { number } id - id of the Node to move to
         */
        async toId(id) {
            await useNodesStore().loadId(id);
        },

        /**
         * To traverse to a specified id, regardless of where it is
         * in the current tree structure and prevent any further
         * traversal back before the starting node
         *
         * @param { number } id - id of the Node to move to
         */
        async toStartingNodeId(id = UNSET_ID) {
            this.hasStartingNode = true;
            this.startingNodeId = id;
            // if the id is unset, we just reset and return
            this.reset();
            if (id === UNSET_ID) {
                return;
            }
            // otherwise we load the node at which we start
            await useNodesStore().loadId(this.startingNodeId);
        },
    },
});
