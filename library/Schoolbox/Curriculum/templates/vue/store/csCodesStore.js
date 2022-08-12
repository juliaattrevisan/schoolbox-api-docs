/** 
 * Stores all the curriculum codes and determines display of nodes
 */

import { defineStore } from 'pinia';


export const useCodesStore = defineStore('csCodesStore', {
    
    state: () => ({
        codes: [], //all the codes
    }),

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