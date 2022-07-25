/** 
 * Manages the currently active codes, those filtered by both <select> and 
 * the search <input>
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './csCodesStore';

const UNSET_ID = 0; 
const MIN_QUERY_LENGTH = 2; 

export const useModalActiveCodesStore = defineStore('csModalActiveCodesStore', {
    
    state: () => ({
        selectedFirstNodeId: UNSET_ID,
        selectedSecondNodeId: UNSET_ID,
        selectedThirdNodeId: UNSET_ID,
        query: '', //current search term to filter the active codes
    }),

    getters: {
       
        /**
         * The first level of Curriculum nodes
         * plus the empty node by default if there's more than one child
         * 
         * @return { Array }
         */
        firstNodes () {
            return [ 
                getEmptyNode(),
                ...useCodesStore().codes
            ];
        },
        
        /**
         * Whether a first node has been selected via cs-modal-header.vue
         * 
         * @return { Boolean }
         */
        hasSelectedFirstNode () {
            return this.selectedFirstNodeId !== UNSET_ID;
        },

        /**
         * The currently selected first node
         * 
         * @return { Object }
         */
        selectedFirstNode () {
            return this.firstNodes.find((node) => node.id === this.selectedFirstNodeId);
        },

        /**
         * To determine whether there's any second level nodes (children) in the selectedFirstNode
         * 
         * @return { Boolean }
         */
        hasSecondNodes () {
            return this.selectedFirstNode.children.length > 1 ? true : false;
        },

        /**
         * Show the second level nodes, which are the children of the selectedFirstNode
         * plus the empty node by default if there's more than one child
         * 
         * @return { Array }
         */
        secondNodes () {
            return [ 
                getEmptyNode(),
                ...this.selectedFirstNode.children,
            ];
        },

        /**
         * Whether a second node has been selected via cs-modal-header.vue
         * 
         * @return { Boolean }
         */
        hasSelectedSecondNode () {
            return this.selectedSecondNodeId !== UNSET_ID;
        },

        /**
         * The currently selected second node 
         * 
         * @return { Object }
         */
        selectedSecondNode () {
            return this.secondNodes.find((node) => node.id === this.selectedSecondNodeId);
        },

        /**
         * To determine whether there's any third level nodes (children) in the selectedSecondNode
         * 
         * @return { Boolean }
         */
        hasThirdNodes () {
            return this.selectedSecondNode.children.length > 1 ? true : false;
        },

        /**
         * Show the third level nodes, which are the children of the selectedSecondNode
         * plus the empty node by default if there's more than one child
         * 
         * @return { Array }
         */
        thirdNodes () {
            return [ 
                getEmptyNode(),
                ...this.selectedSecondNode.children,
            ];
        },

        /**
         * Whether a third node has been selected via cs-modal-header.vue
         * 
         * @return { Boolean }
         */
        hasSelectedThirdNode () {
            return this.selectedThirdNodeId !== UNSET_ID;
        },

        /**
         * The currently selected third node 
         * 
         * @return { Object }
         */
        selectedThirdNode () {
            return this.thirdNodes.find((node) => node.id === this.selectedThirdNodeId);
        },

        /**
         * The currently selected code
         * 
         * @return { Object }
         */
        selectedCode () {
            if (this.hasSelectedFirstNode && this.hasSelectedSecondNode && this.hasSelectedThirdNode) {
                return this.selectedThirdNode;
            } else if (this.hasSelectedFirstNode && this.hasSelectedSecondNode) {
                return this.selectedSecondNode;
            } else if (this.hasSelectedFirstNode) {
                return this.selectedFirstNode;
            } else {
                return [];
            }
        },

        /**
         * the search string broken into words, to search for each word
         *
         * @returns { Array } the search string broken into single words
         */
        queryWordList () {
            const queryTrimmed = this.query.trim(); //remove any front or back white space
            const re = /\s*(?:,|$)\s*/ //adapted from https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/split#removing_spaces_from_a_string
            const commaSeparated = queryTrimmed.split(re) ?? []; //split the string into a comma separated list
            return commaSeparated.filter(word => word.length > 0); //and ensure each contains a character
        },
        
        /**
         * Display the current nodes based on filtration, so what's selected via the <selects>
         * and search <input> in cs-modal-header.vue
         * 
         * @return { Array }
         */
        codes () {
            if (this.hasSelectedFirstNode && this.hasSelectedSecondNode && this.hasSelectedThirdNode) {
                return [this.selectedThirdNode];
            } else if (this.hasSelectedFirstNode && this.hasSelectedSecondNode) {
                return [this.selectedSecondNode];
            } else if (this.hasSelectedFirstNode) {
                return [this.selectedFirstNode];
            } else {
                return [];
            }
        },

        /**
         * 
         * 
         * @return { Boolean }
         */
        isSearchActive () {
            return this.query.length > MIN_QUERY_LENGTH;
        },

        /**
         * no children descendents, all put in an array one level deep
         * 
         * @returns { Array } 
         */
        nodesFlattened () {
            if (this.isSearchActive) {
                return splitChildrenToArray(this.codes[0]);
            } else {
                return [];
            }
        },

        /**
         * the classes found relevant to the search input, capped to the maximum 
         * amount of results to be shown
         * 
         * @returns { Array } all relevant search results, once we've reached the minQueryLength
         */
        codesWithSearch () {
            if (this.query.length < MIN_QUERY_LENGTH) {
                return this.codes;
            } else if (this.codes.filter(this.searchForName).length > 0) { 
                console.warn(this.codes.filter(this.searchForName).length);
                const fullResults = this.codes.filter(this.searchForName);
                return fullResults;
            } else {
                return this.codes;
            }
        },

    },

    actions: {

        /**
         * to reset the selected second node, reverting the node to empty 
         */
        resetSelectedSecondNode () {
            this.selectedSecondNodeId = UNSET_ID;
            this.query = '';
        },

        /**
         * to reset the selected thurd node, reverting the node to empty 
         */
        resetSelectedThirdNode () {
            this.selectedThirdNodeId = UNSET_ID;
            this.query = '';
        },

        /**
         * find all those results that match EACH search word
         * within the the title field
         *
         * @params { Object }
         *
         * @return { Boolean } true if found
         */
        searchForName (code) {
            let wordMatchCount = 0;
            this.queryWordList.forEach(wordInSearch => {
                if (code.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
                    wordMatchCount++;
                }
            });
            return wordMatchCount > 0 ? true : false; //if a word matches
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
 * 
 * 
 * @param { Object }
 */
function splitChildrenToArray (obj) {
    let array = [obj];
    if (obj.children && obj.children.length > 0) {
        obj.children.forEach((child) => {
            array = [...array, ...splitChildrenToArray(child)];
        });
        obj.children = []; 
    }
    return array;
}

/**
 * find all those results that match EACH search word
 * within the the title field
 *
 * @params { Object }
 *
 * @return { Boolean } true if found
 */
// function searchForName (code) {
//     let wordMatchCount = 0;
//     this.queryWordList.forEach(wordInSearch => {
//         if (code.name?.toLowerCase().includes(wordInSearch.toLowerCase())) {
//             wordMatchCount++;
//         }
//     });
//     return wordMatchCount > 0 ? true : false; //if a word matches
// }