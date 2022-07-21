/** 
 * Manages the currently selected codes relevant to the curent modal session
 */

import { defineStore } from 'pinia';
import { useSelectedCodesStore } from './csSelectedCodesStore';


export const useModalSelectedCodesStore = defineStore('csModalSelectedCodesStore', {
    
    state: () => ({
        codes: [], //the currently selected codes in the current modal session, with any previously selected also considered
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
         * populate with the currently selected codes
         */
        refresh () {
            this.codes = useSelectedCodesStore().codes; //ensure current selections are reflected in the new modal session
        },

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
        empty () {
            this.codes  = [];
        },

        /**
         * update the currently selected with the latest modal session
         */
        update () {
            useSelectedCodesStore().update(this.codes);
        },

    },
    
})