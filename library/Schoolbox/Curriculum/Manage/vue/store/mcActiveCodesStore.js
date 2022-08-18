/**
 * Manages the currently active codes in the modal,
 * those filtered by both <select> and also the search <input>
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './mcCodesStore';
import cloneDeep from 'lodash/cloneDeep';

const UNSET_ID = 0;
const MIN_QUERY_LENGTH = 3;

export const useActiveCodesStore = defineStore('mcActiveCodesStore', {

    state: () => ({
        selectedNodeIdList: [],
        // selectedFirstNodeId: UNSET_ID,
        // selectedSecondNodeId: UNSET_ID,
        // selectedThirdNodeId: UNSET_ID,
        // selectedFourthNodeId: UNSET_ID,
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
            if (codesStore.codes.length === 1) {
                // this.selectedFirstNodeId = codesStore.codes[0].id;
                this.selectedNodeIdList = [codesStore.codes[0].id];
                return true;
            } else {
                return false;
            }
        },

        /**
         * The first level of Curriculum nodes
         * plus the empty node by default if there's more than one child
         *
         * @return { Array }
         */
        firstNodes () {
            const codesStore = useCodesStore();
            if (this.hasSingleGroup) {
                return [...codesStore.codes];
            } else {
                return [
                    getEmptyNode(),
                    ...codesStore.codes
                ];
            }
        },

        /**
         * Whether a first node has been selected via mc-header.vue
         *
         * @return { Boolean }
         */
        // hasSelectedFirstNode () {
        //     return this.selectedFirstNodeId !== UNSET_ID;
        // },

        /**
         * The currently selected first node
         *
         * @return { Object }
         */
        // selectedFirstNode () {
        //     return this.firstNodes.find((node) => node.id === this.selectedFirstNodeId);
        // },

        /**
         * To determine whether there's any second level nodes (children) in the selectedFirstNode
         *
         * @return { Boolean }
         */
        // hasSecondNodes () {
        //     return this.selectedFirstNode.children.length > 0 ? true : false;
        // },

        /**
         * Show the second level nodes, which are the children of the selectedFirstNode
         * plus the empty node by default if there's more than one child
         *
         * @return { Array }
         */
        // secondNodes () {
        //     return [
        //         getEmptyNode(),
        //         ...this.selectedFirstNode.children,
        //     ];
        // },

        /**
         * Whether a second node has been selected via mc-header.vue
         *
         * @return { Boolean }
         */
        // hasSelectedSecondNode () {
        //     return this.selectedSecondNodeId !== UNSET_ID;
        // },

        /**
         * The currently selected second node
         *
         * @return { Object }
         */
        // selectedSecondNode () {
        //     return this.secondNodes.find((node) => node.id === this.selectedSecondNodeId);
        // },

        /**
         * To determine whether there's any third level nodes (children) in the selectedSecondNode
         *
         * @return { Boolean }
         */
        // hasThirdNodes () {
        //     return this.selectedSecondNode.children.length > 0 ? true : false;
        // },

        /**
         * Show the third level nodes, which are the children of the selectedSecondNode
         * plus the empty node by default if there's more than one child
         *
         * @return { Array }
         */
        // thirdNodes () {
        //     return [
        //         getEmptyNode(),
        //         ...this.selectedSecondNode.children,
        //     ];
        // },

        /**
         * Whether a third node has been selected via mc-header.vue
         *
         * @return { Boolean }
         */
        // hasSelectedThirdNode () {
        //     return this.selectedThirdNodeId !== UNSET_ID;
        // },

        /**
         * The currently selected third node
         *
         * @return { Object }
         */
        // selectedThirdNode () {
        //     return this.thirdNodes.find((node) => node.id === this.selectedThirdNodeId);
        // },

        /**
         * To determine whether there's any fourth level nodes (children) in the selectedFourthNode
         *
         * @return { Boolean }
         */
        // hasFourthNodes () {
        //     return this.selectedThirdNode.children.length > 0 ? true : false;
        // },

        /**
         * Show the fourth level nodes, which are the children of the selectedThirdNode
         * plus the empty node by default if there's more than one child
         *
         * @return { Array }
         */
        // fourthNodes () {
        //     return [
        //         getEmptyNode(),
        //         ...this.selectedThirdNode.children,
        //     ];
        // },

        /**
         * Whether a fourth node has been selected via mc-header.vue
         *
         * @return { Boolean }
         */
        // hasSelectedFourthNode () {
        //     return this.selectedFourthNodeId !== UNSET_ID;
        // },

        /**
         * The currently selected fourth node
         *
         * @return { Object }
         */
        // selectedFourthNode () {
        //     return this.fourthNodes.find((node) => node.id === this.selectedFourthNodeId);
        // },

        /**
         * To determine the display of elements based on how far along
         * the node selection tree we are
         *
         * @return { Boolean }
         */
        // isOnFirstNode () {
        //     return this.hasSelectedFirstNode && !this.hasSelectedSecondNode && !this.hasSelectedThirdNode;
        // },

        /**
         * To determine the display of elements based on how far along
         * the node selection tree we are
         *
         * @return { Boolean }
         */
        // isOnSecondNode () {
        //     return this.hasSelectedFirstNode && this.hasSelectedSecondNode && !this.hasSelectedThirdNode;
        // },

        /**
         * To determine the display of elements based on how far along
         * the node selection tree we are
         *
         * @return { Boolean }
         */
        // isOnThirdNode () {
        //     return this.hasSelectedFirstNode && this.hasSelectedSecondNode && this.hasSelectedThirdNode;
        // },

        /**
         * To determine the display of elements based on how far along
         * the node selection tree we are
         *
         * @return { Boolean }
         */
        // isOnFourthNode () {
        //     return this.hasSelectedFirstNode && this.hasSelectedSecondNode && this.hasSelectedThirdNode && this.hasSelectedFourthNode;
        // },

        /**
         * The currently selected code
         *
         * @return { Object }
         */
        // selectedCode () {
        //     if (this.isOnFourthNode) {
        //         return this.selectedFourthNode;
        //     } else if (this.isOnThirdNode) {
        //         return this.selectedThirdNode;
        //     } else if (this.isOnSecondNode) {
        //         return this.selectedSecondNode;
        //     } else if (this.isOnFirstNode) {
        //         return this.selectedFirstNode;
        //     } else {
        //         return [];
        //     }
        // },

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
         * Display the current nodes based on filtration, so what's selected via the <selects>
         * and search <input> in mc-header.vue
         *
         * @return { Array }
         */
        codes () {
            if (this.isOnFourthNode) {
                return [this.selectedFourthNode];
            } else if (this.isOnThirdNode) {
                return [this.selectedThirdNode];
            } else if (this.isOnSecondNode) {
                return [this.selectedSecondNode];
            } else if (this.isOnFirstNode) {
                return [this.selectedFirstNode];
            } else {
                return [];
            }
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
                const codesClone = cloneDeep(this.codes); //cloneDeep is from lodash.js
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
                return this.codes;
            }
        },

    },

    actions: {

        /**
         *
         *
         * @param { number }
         */
        getNodesAt (depth) {
            const codesStore = useCodesStore();
            if (depth === 0 && this.hasSingleGroup) {
                return [...codesStore.codes];
            } else {
                return [
                    getEmptyNode(),
                    ...codesStore.codes
                ];
            }
            // if (this.isOnThirdNode) {
            //     this.selectedFourthNodeId = id;
            // } else if (this.isOnSecondNode) {
            //     this.selectedThirdNodeId = id;
            // } else if (this.isOnFirstNode) {
            //     this.selectedSecondNodeId = id;
            // }
        },

        /**
         * Can traverse through the codes via direct selection of a mc-code when
         * it doesn't have a code.
         *
         * @param { number }
         */
        setNextSelectedNode (id) {
            this.selectedNodeIdList = [...this.selectedNodeIdList, id];
            // if (this.isOnThirdNode) {
            //     this.selectedFourthNodeId = id;
            // } else if (this.isOnSecondNode) {
            //     this.selectedThirdNodeId = id;
            // } else if (this.isOnFirstNode) {
            //     this.selectedSecondNodeId = id;
            // }
        },

        /**
         * to reset the selected second node, reverting the node to empty
         */
        // resetSelectedSecondNode () {
        //     this.selectedSecondNodeId = UNSET_ID;
        //     this.resetSelectedThirdNode();
        //     this.resetSelectedFourthNode();
        // },

        /**
         * to reset the selected third node, reverting the node to empty
         */
        // resetSelectedThirdNode () {
        //     this.selectedThirdNodeId = UNSET_ID;
        //     this.resetSelectedFourthNode();
        // },

        /**
         * to reset the selected thurd node, reverting the node to empty
         */
        // resetSelectedFourthNode () {
        //     this.selectedFourthNodeId = UNSET_ID;
        // },

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