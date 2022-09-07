/**
 * Manages the currently visible nodes in mc-view-nodes
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from 'pinia';
import { useCodesStore, findById } from './csCodesStore';
import { useModalSearchStore } from './csModalSearchStore';

export const DIRECTION = {
    FORWARDS: 'forwards',
    BACKWARDS: 'backwards',
}

export const useModalActiveCodesStore = defineStore('csModelActive', {

    state: () => ({
        idList: [], //ids of the selected nodes
        currentDepth: 0, //track how far down the tree we are
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
            const codesStore = useCodesStore();
            if (codesStore.codes.children?.length === 1) {
                return true;
            } else {
                return false;
            }
        },

        /**
         * Can now use the search input when we have selected at least one node
         *
         * @return { Boolean }
         */
        isActive () {
            return this.idList.length > 0;
        },

        /**
         * to traverse back through the nodes via breadcrumbs
         *
         * @return { Array }
         */
        breadcrumbs () {
            const codesStore = useCodesStore();
            let breadcrumbs = [];
            if (!this.hasSingleCurriculum) { //we only need this breadcrumb if there's more than one Curriculum, otherwise we jump straight to the single node with no option of returning back
                breadcrumbs.push( { id: codesStore.codes.id, name: codesStore.codes.name, depth: 0 } );
            }
            this.idList.forEach((id, index) => { //add in a breadcrumb for each selected node
                const nodeAtDepth = findById(codesStore.codes.children, id);
                breadcrumbs.push( { id: id, name: nodeAtDepth.name, depth: index+1 } );
            });
            //we return an empty array if there's no selections yet, otherwise we pull out the latest as it represents the node we are currently on (not one we need to move back to)
            return breadcrumbs.length === 0 ? [] : breadcrumbs.slice(0, -1);
        },

        /**
         * the current node selection
         *
         * @returns { Object }
         */
        current () {
            const codesStore = useCodesStore();
            if (this.idList.length > 0) { //grab the current latest selection
                return findById(codesStore.codes.children, this.idList[this.currentDepth-1]);
            } else { //otherwise return the root level structure when there's more than one root level node
                return codesStore.codes;
            }
        },

        /**
         * the current nodes to display, which are either just the current selection
         * or when the search is active the results found within the current selection
         *
         * @returns { Array }
         */
        codes () {
            const searchStore = useModalSearchStore();
            if (searchStore.hasNoResults) {
                return [this.current];
            } else {
                return searchStore.results;
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
        setSelected (depth, id) {
            if (depth === 0) { //pulling back to no node selection at all, the root level
                this.reset();
            } else if (depth > this.currentDepth) { //progressing to the next depth
                this.toNext(id);
            } else if (depth < this.currentDepth) { //or going back to a previous depth
                this.toPrevious(depth)
            }
            this.currentDepth = depth;
        },

        /**
         * Reset to the original state, with no active selection at all
         */
        reset () {
            this.currentDirection = DIRECTION.BACKWARDS;
            this.idList = [];
            this.currentDepth = 0;
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
            this.idList = this.idList.filter((id, index) => index < targetDepth); //shorten the list back to the new reduced depth
        },

        /**
         * Move one depth down the tree
         *
         * @param { number }
         */
        async setNextSelected (id) {
            const codesStore = useCodesStore();
            await codesStore.loadChildren(id);
            this.setSelected(this.currentDepth+1, id);
        },

    },

});