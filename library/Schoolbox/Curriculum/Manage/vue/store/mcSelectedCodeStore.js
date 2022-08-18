/**
 * Manage the currently selected curriculum codes
 */

import { defineStore } from 'pinia';


export const useSelectedCodesStore = defineStore('mcSelectedCodeStore', {

    state: () => ({
        code: [], //the currently selected code
    }),

    getters: {

        /**
         * To show further info on a selected code
         *
         * @return { Boolean }
         */
        hasActiveCode () {
            return this.activeCode !== null ? true : false;
        },

    },

    actions: {

        /**
         * set the active code, which would prompt the mc-active-selected-code-modal to open
         * and if it's set to null we can listen for it to be set again
         *
         * @param { Object }
         */
        setSelectedCode (code) {
            this.code = code;
        },

    },

})