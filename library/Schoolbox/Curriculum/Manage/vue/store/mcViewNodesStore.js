/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from 'pinia';
import { useNodesStore } from './mcNodesStore';

const UNSET_ID = 0;
const DIRECTION = {
    FORWARDS: 'forwards',
    BACKWARDS: 'backwards',
}

export const useViewNodesStore = defineStore('mcViewNodesStore', {

    state: () => ({
        idList: [], //ids of the selected nodes
        currentDepth: -1, //track how far down the tree we are
        currentDirection: DIRECTION.FORWARDS //which direction we are currently travelling in the tree
    }),

    getters: {

        /**
         * If there's only one curriculum, we can select it
         * straight away, otherwise we allow the user to make the selection
         *
         * @return { Boolean }
         */
        hasSingleCurriculum () {
            const nodesStore = useNodesStore();
            if (nodesStore.nodes.children.length === 1) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * No nodes have been selected yet, used to show empy state in mc-view-codes
         *
         * @return { Boolean }
         */
        isInactive () {
            return this.idList.length === 0;
        },

        /**
         * Can now use the search input
         *
         * @return { Boolean }
         */
        isActive () {
            return this.idList.length > 0;
        },

        /**
         * @TODO in progress to detect deleted nodes
         *
         * @return { Array }
         */
        activeIdList () {
            const nodesStore = useNodesStore();
            return this.idList.filter((id) => {
                const nodeAtDepth = findById(nodesStore.nodes.children, id);
                return nodeAtDepth !== undefined ? true : false;
            })
        },

        /**
         * to traverse through the nodes via the <selects> in mc-view-header-select-list.vue
         *
         * @return { Array }
         */
        breadcrumbs () {
            const nodesStore = useNodesStore();
            const lastIdRemovedList = this.idList.slice(0, this.idList.length - 1);
            if (lastIdRemovedList.length > 0) {
                return lastIdRemovedList.map((id) => {
                    const nodeAtDepth = findById(nodesStore.nodes.children, id);
                    return {
                        id: id,
                        name: nodeAtDepth.name,
                    }
                });
            } else {
                return [];
            }
        },

        /**
         * the codes found relevant to the search input
         *
         * @returns { Array }
         */
        nodesWithSearch () {
            return [this.current];
        },

        /**
         * the current node selection
         *
         * @returns { Object }
         */
        current () {
            const nodesStore = useNodesStore();
            return findById(nodesStore.nodes.children, this.idList[this.currentDepth])
        }

    },

    actions: {

        /**
         * There's been a change in node selection,
         * so update based on the change in depth
         *
         * @param { number }
         * @param { number }
         */
        setSelected (depth, id) {
            if (depth === 0 && id === UNSET_ID) { //pulling back to no node selection at all
                this.reset();
                return;
            }
            const targetDepth = id === UNSET_ID ? depth - 1 : depth; //we need to go back an additional depth
            if (depth > this.currentDepth) { //progressing to the next depth
                this.toNext(id);
            } else if (depth < this.currentDepth) { //or going back to a previous depth
                this.toPrevious(targetDepth)
            }
            this.currentDepth = targetDepth;
        },

        /**
         * Add one more selection
         *
         * @param { number }
         */
        toNext (id) {
            this.currentDirection = DIRECTION.FORWARDS;
            this.idList = [...this.idList, id]; //add the new id in
        },

        /**
         * Move back to a previous node
         *
         * @param { number }
         */
        toPrevious (targetDepth) {
            this.currentDirection = DIRECTION.BACKWARDS;
            this.idList = this.idList.filter((id, index) => index <= targetDepth); //shorten the list back to the new reduced depth
        },

        /**
         * Reset to the original state, with no active selection at all
         */
        reset () {
            this.idList = [];
            this.currentDepth = this.idList.length - 1;
        },

        /**
         * Move one depth down the tree
         *
         * @param { number }
         */
        setNextSelected (id) {
            this.setSelected(this.currentDepth+1, id);
        },


    },

});

/**
 * To just return the node containing the specified id
 *
 * @param { array }
 * @param { number }
 */
function findById (array, id) {
    for (const item of array) {
        if (item.id === id) {
            return item;
        }
        if (item.children?.length) {
            const innerResult = findById(item.children, id);
            if (innerResult) {
                return innerResult;
            }
        }
    }
}