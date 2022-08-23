/**
 * Manages the currently visible codes in the viewleNodesStore
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from 'pinia';
import { useNodesStore } from './mcNodesStore';

const UNSET_ID = 0;
const MAX_DEPTH = 6; //dont traverse any further down than 6 levels

export const useViewNodesStore = defineStore('mcViewNodesStore', {

    state: () => ({
        idList: [], //ids of the selected nodes
        currentDepth: -1, //track how far down the tree we are
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
                this.setSelected(0, nodesStore.nodes.children[0].id);
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
        lists () {
            const nodesStore = useNodesStore();
            const lists = [nodesStore.nodes];
            this.idList.forEach((id) => {
                const nodeAtDepth = findById(nodesStore.nodes.children, id);
                lists.push(nodeAtDepth);
            });
            return lists;
        },

        /**
         * Limit to six deep
         *
         * @return { Array }
         */
        listsLimited () {
            if (this.current?.children?.length === 0) { //if there's no more children, we don't need to display another depth
                return this.lists.filter((nodeList, index) => index <= this.currentDepth);
            } else {
                return this.lists.filter((nodeList, index) => index < MAX_DEPTH);
            }
        },

        /**
         * We use this to expand the remaining children
         *
         * @return { Boolean }
         */
        isOnLast () {
            if (this.idList.length === MAX_DEPTH) {
                return true;
            } else {
                return false;
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
         * Used to get the <select> options in mc-view-header-select-list
         *
         * @param { array[object] }
         */
        getChildrenAt (depth) {
            try {
                const nodesStore = useNodesStore();
                if (depth === 0 && this.hasSingleCurriculum) {
                    return [...nodesStore.nodes.children];
                } else {
                    return [
                        getEmptyNode(),
                        ...this.lists[depth].children
                    ];
                }
            } catch (error) {
                // debugger;
                // @TODO - we should be handling moving back after a deleted note better
                // consider using this.activeIdList to automtically filter out ids that can no longer be found
                if (this.lists[depth] === undefined) { //we assume that the node has been deleted if it's undefined
                    this.setSelected(--depth, UNSET_ID); //in which case move back one level to the previous node
                }
            }
        },

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
            } else if (depth === this.currentDepth) { //or selecting a different option on the current depth
                this.changeOnSame(targetDepth, id)
            }
            this.currentDepth = targetDepth;
        },

        /**
         * Add one more selection
         *
         * @param { number }
         */
        toNext (id) {
            this.idList = [...this.idList, id]; //add the new id in
        },

        /**
         * Move back to a previous node
         *
         * @param { number }
         */
        toPrevious (targetDepth) {
            this.idList = this.idList.filter((id, index) => index <= targetDepth); //shorten the list back to the new reduced depth
        },

        /**
         * Either switching selection on same node, or moving back to the previous for the '-' selection
         *
         * @param { number }
         * @param { number }
         */
        changeOnSame (targetDepth, id) {
            if (id === UNSET_ID) { //we need to move back one level
                this.toPrevious(targetDepth); //shorten the list back to the new reduced depth
            } else { //or we are on the same level, so remove the latest than add the new in
                this.idList = this.idList.filter((id, index) => index !== targetDepth); //remove the latest so we can add the new selection
                this.idList = [...this.idList, id]; //then add the new selection in
            }
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
 * Used to indicate that a selection hasn't been made
 * on a curriculum node yet
 *
 * @param { Object }
 */
function getEmptyNode () {
    return {
        id: UNSET_ID,
        name: '-',
        children: [],
    }
}

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