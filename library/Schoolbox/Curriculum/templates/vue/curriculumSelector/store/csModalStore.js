/**
 * Handles the display state of the curriculum selector modal
 */

import { defineStore } from "pinia";
import { useModalSelectedNodesStore } from "./csModalSelectedNodesStore";
import { useModalViewNodesStore } from "./csModalViewNodesStore";

export const useModalStore = defineStore(`csModal`, {
    state: () => ({
        isOpen: false, //to notify that the modal is opened / closed
        selectedBadge: null, // to display the selected badge in the modal if selected
        isSingleSelect: false, // to notify if the modal is in single select mode
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
         * to move to a specific node in the modal
         */
        toStartingNodeId(id) {
            useModalViewNodesStore().toStartingNodeId(id);
        },

        /**
         * to close the modal
         */
        cancel() {
            this.isOpen = false;
        },

        /**
         * close the modal, also updating the selected nodes to reflect the changes
         * made in this modal session
         */
        select() {
            useModalSelectedNodesStore().update();
            this.isOpen = false;
        },

        /**
         * clearing any selected nodes in this modal session
         */
        closed() {
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
