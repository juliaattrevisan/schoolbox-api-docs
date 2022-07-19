/** 
 * Handles the display state of the curriculum selector modal
 */

import { defineStore } from 'pinia';


export const useModalStore = defineStore('csModalStore', {
    
    state: () => ({
        isOpen: false, //to notify that the modal is opened / closed
        query: '', //the term to search for 
    }),

    actions: {

        /**
         * to open the modal
         */
        open () {
            this.isOpen = true;
        },

        /**
         * to close the modal
         */
       close () {
            this.isOpen = false;
        },

    },
    
})