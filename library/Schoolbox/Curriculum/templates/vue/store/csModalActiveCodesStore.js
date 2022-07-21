/** 
 * Manages the currently active codes, those filtered by both <select> and 
 * the search <input>
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './csCodesStore';

const UNSET = 0; 

export const useModalActiveCodesStore = defineStore('csModalActiveCodesStore', {
    
    state: () => ({
        query: '', //current search term to filter the active codes
        selectedFirstNodeId: UNSET,
        selectedSecondNodeId: UNSET,
        selectedThirdNodeId: UNSET,
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
            return this.selectedFirstNodeId !== UNSET;
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
            return this.selectedSecondNodeId !== UNSET;
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
            return this.selectedThirdNodeId !== UNSET;
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

    },

    actions: {

        /**
         * to reset the selected second node, reverting the node to empty 
         */
        resetSelectedSecondNode () {
            this.selectedSecondNodeId = UNSET;
        },

        /**
         * to reset the selected thurd node, reverting the node to empty 
         */
        resetSelectedThirdNode () {
            this.selectedThirdNodeId = UNSET;
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
        id: UNSET, 
        name: '-',
        children: [], 
    }
}