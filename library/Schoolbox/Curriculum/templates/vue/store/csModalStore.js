/**
 * Handles the display state of the curriculum selector modal
 */

import { defineStore } from 'pinia';
import { useModalSelectedCodesStore } from './csModalSelectedCodesStore';


export const useModalStore = defineStore('csModalStore', {

    state: () => ({
        isOpen: false, //to notify that the modal is opened / closed
    }),

    actions: {

        /**
         * to open the modal and show currently selected nodes
         * in the new modal session
         */
        open () {
            useModalSelectedCodesStore().refresh(); //ensure current selections are reflected in the new modal session
            this.isOpen = true;
        },

        /**
         * to close the modal, clearing any selected codes in this modal session
         */
        cancel () {
            useModalSelectedCodesStore().empty();
            this.isOpen = false;
        },

        /**
         * close the modal, also updating the selected codes to reflect the changes
         * made in this modal session and then clear for another possible modal session
         */
        select () {
            useModalSelectedCodesStore().update();
            this.isOpen = false;
            useModalSelectedCodesStore().empty();
        },

    },

})