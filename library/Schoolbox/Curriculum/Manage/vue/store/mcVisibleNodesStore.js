/**
 * Manages the currently visible codes in the viewleNodesStore
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from 'pinia';
import { useNodesStore } from './mcNodesStore';
import cloneDeep from 'lodash/cloneDeep';

const UNSET_ID = 0;
const MIN_QUERY_LENGTH = 3;
const MAX_DEPTH = 6; //dont traverse any further down than 6 levels

export const useVisibleNodesStore = defineStore('mcVisibleNodesStore', {

    state: () => ({
        idList: [], //ids of the selected nodes
        lists: [], //the list of nodes available at each active depth
        current: {}, //the current node at the most recently selected depth
        currentDepth: -1,
        query: '', //current search term to filter the active codes
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
         * the search string broken into words, to search for each word
         *
         * @returns { Array } the search string broken into single words
         */
        queryWordList () {
            const queryTrimmed = this.query.trim(); //remove any front or back white space
            const spaceSeparated = queryTrimmed.split(' ') ?? []; //split the string with every space
            return spaceSeparated.filter(word => word.length > 0); //and ensure each contains a character
        },

        /**
         * no children descendents, all put in an array one level deep
         * for search results
         *
         * @returns { Array }
         */
        nodesFlattened () {
            if (this.isSearchActive) {
                let flatChildren = [];
                const nodesClone = cloneDeep(this.current); //cloneDeep is from lodash.js
                nodesClone.forEach((obj) => flatChildren = [...flatChildren, ...flattenNodes(obj)]);
                flatChildren = flatChildren.filter((node) => node.code != ''); //only show those with a code, as those without can't be selected
                return flatChildren;
            } else {
                return [];
            }
        },

        /**
         * To only start showing search results once enough characters
         * have been added
         *
         * @return { Boolean }
         */
        isSearchActive () {
            return this.query.length >= MIN_QUERY_LENGTH;
        },

        /**
         * When the search is active, will return any codes
         * that match the current search query
         *
         * @return { Array }
         */
        searchResults () {
            return this.nodesFlattened.filter(this.filterResults);
        },

        /**
         * To show that no codes march the current search
         *
         * @return { Boolean }
         */
        hasNoSearchResults () {
            return this.searchResults.length === 0 && this.isSearchActive && this.isActive;
        },

        /**
         * the codes found relevant to the search input
         *
         * @returns { Array }
         */
        nodesWithSearch () {
            if (this.isSearchActive) { //only start showing results once the query is long enough
                return this.searchResults;
            } else { //otherwise just show codes
                return [this.current];
            }
        },

    },

    actions: {

        /**
         * Used to get the <select> options in mc-view-header-select-list
         *
         * @param { array[object] }
         */
        getChildrenAt (depth) {
            try {
                if (depth === 0 && this.hasSingleCurriculum) {
                    return [...this.lists[depth].children];
                } else {
                    return [
                        getEmptyNode(),
                        ...this.lists[depth].children
                    ];
                }
            } catch (error) {
                debugger;
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
            this.updateOnChange(targetDepth, id)
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
            this.lists = this.lists.filter((id, index) => index <= targetDepth);
        },

        /**
         *
         *
         * @param { number }
         * @param { number }
         */
        changeOnSame (targetDepth, id) {
            if (id === UNSET_ID) { //we need to move back one level
                this.idList = this.idList.filter((id, index) => index <= targetDepth); //shorten the list back to the new reduced depth
            } else {
                this.idList = this.idList.filter((id, index) => index !== targetDepth); //remove the latest so we can add the new selection
                this.idList = [...this.idList, id]; //then add the new selection in
            }
            this.lists = this.lists.filter((id, index) => index <= targetDepth);
        },

        /**
         * Update to the currently selected node
         *
         * @param { number }
         * @param { number }
         */
        updateOnChange (targetDepth, id) {
            this.currentDepth = targetDepth;
            const targetId = id === UNSET_ID ? this.idList[this.currentDepth] : parseInt(id); //we need to move back to the previous selectedNodeList when the id is unset (the - options in mc-view-header-select-list.vue)
            this.current = this.lists[this.currentDepth].children.find((code) => parseInt(code.id) === targetId);
            this.lists = [...this.lists, this.current];
        },

        /**
         * Reset to the original state, with no active selection at all
         */
        reset () {
            this.idList = [];
            const nodesStore = useNodesStore();
            this.lists = [nodesStore.nodes];
            this.current = {};
            this.currentDepth = -1;
        },

        /**
         * Move down one depth down the tree
         *
         * @param { number }
         */
        setNextSelected (id) {
            this.setSelected(this.currentDepth+1, id);
        },

        /**
         * find all those results that match any search words
         * within the the code, name or breadcrumb fields
         *
         * @params { Object }
         *
         * @return { Boolean } true if found
         */
        filterResults (code) {
            return searchForMatches(code, this.queryWordList)
        },

        /**
         * clear the current search
         */
        clearSearch () {
            this.query = '';
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
 * The codes are nested with children:[], so we pull them all
 * out into a single level when searching
 *
 * @param { Object }
 */
export function flattenNodes (obj) {
    let array = [obj];
    if (obj.children && obj.children.length > 0) {
        obj.children.forEach((child) => {
            array = [...array, ...flattenNodes(child)];
        });
        obj.children = [];
    }
    return array;
}

/**
 * find all those results that match any search words
 * within the the code, name or breadcrumb fields
 *
 * @params { Object }
 * @params { Array }
 *
 * @return { Boolean } true if found
 */
export function searchForMatches (node, queryWordList) {
    let wordMatchCount = 0;
    queryWordList.forEach(wordInSearch => {
        if (node.code?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
        if (node.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
        if (node.breadcrumb?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
    });
    return wordMatchCount > 0 ? true : false; //if there's any matches
}