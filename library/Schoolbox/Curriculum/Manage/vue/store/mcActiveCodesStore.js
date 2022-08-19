/**
 * Manages the currently active codes in the view
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './mcCodesStore';
import cloneDeep from 'lodash/cloneDeep';

const UNSET_ID = 0;
const MIN_QUERY_LENGTH = 3;
const MAX_DEPTH = 6; //dont traverse any further down than 6 levels

export const useActiveCodesStore = defineStore('mcActiveCodesStore', {

    state: () => ({
        selectedNodeIdList: [],
        nodeLists: [],
        activeNode: {},
        currentDepth: -1,
        query: '', //current search term to filter the active codes
    }),

    getters: {

        /**
         * If there's only one curriculum group, we can select it
         * straight away, otherwise we allow the user to make the selection
         *
         * @return { Boolean }
         */
        hasSingleGroup () {
            const codesStore = useCodesStore();
            if (codesStore.codes.children.length === 1) {
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
        hasNoNodesSelected () {
            return this.selectedNodeIdList.length === 0;
        },

        /**
         * Can now use the search input
         *
         * @return { Boolean }
         */
        hasSelectedFirstNode () {
            return this.selectedNodeIdList.length > 0;
        },

        /**
         * Limit to six deep
         *
         * @return { Array }
         */
        nodeListsLimited () {
            if (this.activeNode?.children?.length === 0) { //if there's no more children, we don't need to display another depth
                return this.nodeLists.filter((nodeList, index) => index <= this.currentDepth);
            } else {
                return this.nodeLists.filter((nodeList, index) => index < MAX_DEPTH);
            }

        },

        /**
         * We use this to expand the remaining children
         *
         * @return { Boolean }
         */
        isOnLastNode () {
            if (this.selectedNodeIdList.length === MAX_DEPTH) {
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
        codesFlattened () {
            if (this.isSearchActive) {
                let flatChildren = [];
                const codesClone = cloneDeep(this.activeNode); //cloneDeep is from lodash.js
                codesClone.forEach((obj) => flatChildren = [...flatChildren, ...flattenCodes(obj)]);
                flatChildren = flatChildren.filter((code) => code.code != ''); //only show those with a code, as those without can't be selected
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
            return this.codesFlattened.filter(this.filterResults);
        },

        /**
         * To show that no codes march the current search
         *
         * @return { Boolean }
         */
        hasNoSearchResults () {
            return this.searchResults.length === 0 && this.isSearchActive && this.hasSelectedFirstNode;
        },

        /**
         * the codes found relevant to the search input
         *
         * @returns { Array }
         */
        codesWithSearch () {
            if (this.isSearchActive) { //only start showing results once the query is long enough
                return this.searchResults;
            } else { //otherwise just show codes
                return [this.activeNode];
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
                if (depth === 0 && this.hasSingleGroup) {
                    return [...this.nodeLists[depth].children];
                } else {
                    return [
                        getEmptyNode(),
                        ...this.nodeLists[depth].children
                    ];
                }
            } catch (error) {
                debugger;
            }
        },

        /**
         *
         *
         * @param { number }
         * @param { number }
         */
        setSelectedNode (depth, id) {
            if (depth === 0 && id === UNSET_ID) { //pulling back to no node selection at all
                this.reset();
                return;
            }
            const targetDepth = id === UNSET_ID ? depth - 1 : depth; //we need to go back an additional depth
            if (depth > this.currentDepth) { //progressing to the next depth
                this.toNextNode(id);
            } else if (depth < this.currentDepth) { //or going back to a previous depth
                this.toAPreviousNode(targetDepth)
            } else if (depth === this.currentDepth) { //or selecting a different option on the current depth
                this.changeOnSameNode(targetDepth, id)
            }
            this.updateOnNodeChange(targetDepth, id)
        },

        /**
         * Add one more selection
         *
         * @param { number }
         */
        toNextNode (id) {
            this.selectedNodeIdList = [...this.selectedNodeIdList, id]; //add the new id in
        },

        /**
         * Move back to a previous node
         *
         * @param { number }
         */
        toAPreviousNode (targetDepth) {
            this.selectedNodeIdList = this.selectedNodeIdList.filter((id, index) => index <= targetDepth); //shorten the list back to the new reduced depth
            this.nodeLists = this.nodeLists.filter((id, index) => index <= targetDepth);
        },

        /**
         *
         *
         *  @param { number }
         *  @param { number }
         */
        changeOnSameNode (targetDepth, id) {
            if (id === UNSET_ID) { //we need to move back one level
                this.selectedNodeIdList = this.selectedNodeIdList.filter((id, index) => index <= targetDepth); //shorten the list back to the new reduced depth
            } else {
                this.selectedNodeIdList = this.selectedNodeIdList.filter((id, index) => index !== targetDepth); //remove the latest so we can add the new selection
                this.selectedNodeIdList = [...this.selectedNodeIdList, id]; //then add the new selection in
            }
            this.nodeLists = this.nodeLists.filter((id, index) => index <= targetDepth);
        },

        /**
         *
         *  @param { number }
         *  @param { number }
         */
        updateOnNodeChange (targetDepth, id) {
            this.currentDepth = targetDepth;
            const targetId = id === UNSET_ID ? this.selectedNodeIdList[this.currentDepth] : parseInt(id); //we need to move back to the previous selectedNodeList when the id is unset (the - in the select)
            this.activeNode = this.nodeLists[this.currentDepth].children.find((code) => parseInt(code.id) === targetId);
            this.nodeLists = [...this.nodeLists, this.activeNode];
        },

        /**
         * Reset to the original state, with no active selection at all
         */
        reset () {
            const codesStore = useCodesStore();
            this.selectedNodeIdList = [];
            this.nodeLists = [codesStore.codes];
            this.activeNode = {};
            this.currentDepth = -1;
        },

        /**
         * Move down one depth down the tree
         *
         * @param { number }
         */
        setNextSelectedNode (id) {
            this.setSelectedNode(this.currentDepth+1, id);
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
 export function flattenCodes (obj) {
    let array = [obj];
    if (obj.children && obj.children.length > 0) {
        obj.children.forEach((child) => {
            array = [...array, ...flattenCodes(child)];
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
export function searchForMatches (code, queryWordList) {
    let wordMatchCount = 0;
    queryWordList.forEach(wordInSearch => {
        if (code.code?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
        if (code.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
        if (code.breadcrumb?.toLowerCase().includes(wordInSearch.toLowerCase())) {
            wordMatchCount++;
        }
    });
    return wordMatchCount > 0 ? true : false; //if there's any matches
}