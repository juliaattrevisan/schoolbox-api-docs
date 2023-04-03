/**
 * Handles the display state of the curriculum selector modal
 */

import { defineStore } from "pinia";
import { useModalSelectedNodesStore } from "./csModalSelectedNodesStore";

export const useModalStore = defineStore(`csModal`, {
    state: () => ({
        isOpen: false, //to notify that the modal is opened / closed
        selectedBadge: null, // to display the selected badge in the modal if selected
    }),

    actions: {
        /**
         * to open the modal and show currently selected nodes
         * in the new modal session
         */
        open() {
            useModalSelectedNodesStore().refresh(); //ensure current selections are reflected in the new modal session
            this.isOpen = true;
        },

        /**
         * to close the modal, clearing any selected nodes in this modal session
         */
        cancel() {
            useModalSelectedNodesStore().empty();
            this.isOpen = false;
        },

        /**
         * close the modal, also updating the selected nodes to reflect the changes
         * made in this modal session and then clear for another possible modal session
         */
        select() {
            useModalSelectedNodesStore().update();
            this.isOpen = false;
            useModalSelectedNodesStore().empty();
        },

        /**
         * to set the selected badge in the modal
         *
         * @param { BadgeUsageObject } badge
         */
        setSelectedBadge(badge) {
            this.selectedBadge = badge;
        },
    },
});
