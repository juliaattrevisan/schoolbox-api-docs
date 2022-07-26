/**
 * Manage the currently selected curriculum codes
 */

import { defineStore } from 'pinia';


export const useSelectedCodesStore = defineStore('csSelectedCodesStore', {

    state: () => ({
        codes: [], //the currently selected codes
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

        /**
         * Do we have any selected codes?
         * 
         * @return { Boolean }
         */
        hasCodes () {
            return this.codes.length > 0;
        },

    },
   
    actions: {
       
        /**
         * add a code 
         * 
         * @param { Object or Array } either a single code, or mulitple can be added
         */
        add (codeToAdd) {
            if (Array.isArray(codeToAdd)) {
                this.codes  = [...this.codes, ...codeToAdd];
            } else {
                this.codes  = [...this.codes, codeToAdd];
            }
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
         * completely update the codes with a new array
         * 
         * @param { Aray }
         */
        update (updatedCodes) {
            this.codes  = [...updatedCodes];
        },

        /**
         * clear all the currently selected codes
         */
        empty (codeToAdd) {
            this.codes  = [];
        },
        
    },
   
})