/**
 * Manage the currently selected curriculum node
 */

import { defineStore } from 'pinia';
import { useDeleteNodeStore } from './mcDeleteNodeStore';


export const useActiveNodeStore = defineStore('mcActiveNodeStore', {

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

        /**
         * Add in the code if it's available otherwise just show the title
         *
         * @return { String }
         */
        title () {
            if (this.node === null) {
                return '';
            } else if (this.node.code && this.node.code !== '') {
                return `[${this.node.code}] ${this.node.name}`;
            } else {
                return this.node.name;
            }
        },

    },

    actions: {

        /**
         * set the selected node, which would prompt the mc-active-selected-code-modal to open
         * and if it's set to null we can listen for it to be set again
         *
         * @param { Object }
         */
        set (node) {
            this.node = node;
            // this.isModalOpen = true;
        },

        /**
         * unset ready for another use
         *
         * @param { Object }
         */
        unset () {
            this.node = null;
        },

    },

})