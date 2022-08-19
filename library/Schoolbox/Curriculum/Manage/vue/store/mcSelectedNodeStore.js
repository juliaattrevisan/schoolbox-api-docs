/**
 * Manage the currently selected curriculum codes
 */

import { defineStore } from 'pinia';


export const useSelectedNodeStore = defineStore('mcSelectedNodeStore', {

    state: () => ({
        node: null, //the currently selected node
    }),

    getters: {

        /**
         * To show further info on a selected node
         *
         * @return { Boolean }
         */
        isActive () {
            return this.node !== null ? true : false;
        },

    },

    actions: {

        /**
         * set the active node, which would prompt the mc-active-selected-code-modal to open
         * and if it's set to null we can listen for it to be set again
         *
         * @param { Object }
         */
        setSelected (node) {
            this.node = node;
        },

    },

})