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
            // return this.node !== null ? true : false;
            return false;
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
         * set the node to perform an action on
         *
         * @param { Object }
         */
        set (node) {
            this.node = node;
        },

        /**
         * Set newly saved values on the node
         *
         * @param { Object }
         */
        update (node) {
            this.node.name = node.name;
            this.node.code = node.code;
            this.node.reference = node.reference;
            this.node.description = node.description;
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