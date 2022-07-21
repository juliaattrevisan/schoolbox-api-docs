/** 
 * Manages the currently active codes, those filtered by both <select> and 
 * the search <input>
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './csCodesStore';


export const useModalActiveCodesStore = defineStore('csModalActiveCodesStore', {
    
    state: () => ({
        query: '', //current search term to filter the active codes
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
                ...useCodesStore().codes
            ];
        },

        /**
         * 
         * 
         * @return { Object }
         */
        // selectedFirstNode () {
        //     return this.firstNodes.find((node) => node.id === this.selectedFirstNodeId);
        // },

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

        /**
         * Display the current nodes based on filtration
         * 
         * @return { Array }
         */
        codes () {
            if (this.selectedFirstNodeId !== 0 && this.selectedSecondNodeId !== 0 && this.selectedThirdNodeId !== 0) {
                const selectedThirdNode = this.thirdNodes.find((node) => node.id === this.selectedThirdNodeId);
                return [selectedThirdNode];
            } else if (this.selectedFirstNodeId !== 0 && this.selectedSecondNodeId !== 0) {
                const selectedSecondNode = this.secondNodes.find((node) => node.id === this.selectedSecondNodeId);
                return [selectedSecondNode];
            } else if (this.selectedFirstNodeId !== 0) {
                const selectedFirstNode = this.firstNodes.find((node) => node.id === this.selectedFirstNodeId);
                return [selectedFirstNode];
            } else {
                return [];
            }
            // if (this.selectedFirstNodeId !== 0 && this.hasSecondNodes) {
            //     const selectedFirstNode = this.firstNodes.find((node) => node.id === this.selectedFirstNodeId);
            //     return [selectedFirstNode];
            // } else {
            //     return [];
            // }
        },

    },

    actions: {

        /**
         * to reset the selected second node, reverting the node to empty 
         */
         resetSelectedSecondNode () {
            this.selectedSecondNodeId = 0;
        },

        /**
         * to reset the selected thurd node, reverting the node to empty 
         */
        resetSelectedThirdNode () {
            this.selectedThirdNodeId = 0;
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