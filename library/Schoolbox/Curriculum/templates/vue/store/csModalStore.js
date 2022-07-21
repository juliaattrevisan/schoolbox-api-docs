/** 
 * Handles the display state of the curriculum selector modal
 */

import { defineStore } from 'pinia';
import { useSelectedCodesStore } from './csSelectedCodesStore';


export const useModalStore = defineStore('csModalStore', {
    
    state: () => ({
        isOpen: false, //to notify that the modal is opened / closed
        query: '', //the term to search for 
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
         * to open the modal and show currently selected nodes 
         * in the new modal session
         */
        open () {
            this.codes = useSelectedCodesStore().codes; //ensure current selections are reflected in the new modal session
            this.isOpen = true;
        },

        /**
         * to close the modal, clearing and selected codes in this modal session
         */
        cancel () {
            this.emptyCodes();
            this.isOpen = false;
        },

        /**
         * close the modal, also updating the selected codes to reflect the changes
         * made in this modal session and then clear for another possible modal session
         */
        select () {
            useSelectedCodesStore().update(this.codes);
            this.isOpen = false;
            this.emptyCodes();
        },

        /**
         * add a code 
         * 
         * @param { Object }
         */
        addCode (codeToAdd) {
            this.codes  = [...this.codes, codeToAdd];
        },

        /**
         * remove a code 
         * 
         * @param { Object }
         */
        removeCode (codeToRemove) {
            this.codes = this.codes.filter((code) => code.id !== codeToRemove.id);
        },

        /**
         * clear all the currently selected codes
         */
        emptyCodes () {
            this.codes  = [];
        },

    },
    
})