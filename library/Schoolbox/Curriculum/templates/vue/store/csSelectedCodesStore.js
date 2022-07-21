/**
 * Manage the currently selected curriculum codes
 */

import { defineStore } from 'pinia';


export const useSelectedCodesStore = defineStore('csSelectedCodesStore', {

    state: () => ({
        codes: [],
    }),

    getters: {
       
        /**
         * Do we have any selected codes?
         * 
         * @return { Boolean }
         */
        hasNoCodes () {
            return this.codes.length === 0;
        },

    },
   
    actions: {
       
        /**
         * add a code 
         * 
         * @param { Object }
         */
        add (codeToAdd) {
            this.codes  = [...this.codes, codeToAdd];
        },

        /**
         * remove a code 
         * 
         * @param { Object }
         */
        remove (codeToRemove) {
            this.codes = this.codes.filter((code) => code.id !== codeToRemove.id);
        },

        /**
         * clear all the currently selected codes
         */
        empty (codeToAdd) {
            this.codes  = [];
        },
        
    },
   
})