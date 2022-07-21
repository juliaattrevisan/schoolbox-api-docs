/** 
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';


export const useCodesStore = defineStore('csCodesStore', {
    
    state: () => ({
        codes: [], //all the codes
        selectedFirstNodeId: 0,
        selectedSecondNodeId: 0,
        selectedThirdNodeId: 0,
    }),

    getters: {

        /**
         * The first level of Curriculum nodes
         * 
         * @return { Array }
         */
        firstNodes () {
            return [ 
                getEmptyNode(),
                ...this.codes
            ];
        },

        /**
         * To determine whether there's any second level nodes
         * 
         * @return { Boolean }
         */
        hasSecondNodes () {
            const selectedFirstNode = this.firstNodes.find((node) => node.id === this.selectedFirstNodeId);
            return selectedFirstNode.children.length > 1 ? true : false;
        },

        /**
         * The second level of Curriculum nodes, once a firstNode is selected, this second level is displayed
         * showing all the second level nodes within the secondNode
         * 
         * @return { Array }
         */
        secondNodes () {
            const selectedFirstNode = this.firstNodes.find((node) => node.id === this.selectedFirstNodeId);
            return [ 
                getEmptyNode(),
                ...selectedFirstNode.children,
            ];
        },

        /**
         * To determine whether there's any third level nodes
         * 
         * @return { Boolean }
         */
        hasThirdNodes () {
            const selectedSecondNode = this.secondNodes.find((node) => node.id === this.selectedSecondNodeId);
            return selectedSecondNode.children.length > 1 ? true : false;
        },

        /**
         * The third level of Curriculum nodes, once a secondNode is selected, this third level is displayed
         * showing all the third level nodes within the thirdNode
         * 
         * @return { Array }
         */
        thirdNodes () {
            const selectedSecondNode = this.secondNodes.find((node) => node.id === this.selectedSecondNodeId);
            return [ 
                getEmptyNode(),
                ...selectedSecondNode.children,
            ];
        },

    },

    actions: {

        /**
         * Add in all the codes for display 
         * 
         * @param { Array }
         */
        load (codes) {
            this.codes = codes;
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
        id: 0, 
        name: '-',
        children: [], 
    }
}