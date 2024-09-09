/**
 * Handles the load of the curriculum data
 */

import { defineStore } from "pinia";
import { useNodesStore } from "./csNodesStore";
import { useSelectedNodesStore } from "./csSelectedNodesStore";
import { useSearchStore } from "./csSearchStore";
import { useModalStore } from "./csModalStore";
import { useModalViewNodesStore } from "./csModalViewNodesStore";

export let apiEndpoint = "";

export const useStore = defineStore(`curriculumSelector`, {
    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        isReadOnly: true, //can only view the currently selected codes, not edit them
        containerWidth: 0, //currently so can tweak the styling when the selector is small
        selectedOriginal: [], //an 'original' selection, saved by the user
        selectedDefault: [], //the default selection relevant to the current user role
        isSelectAll: false, // to select any nodes, not just those that have a code
        canSelectChildren: true, // is able to select children of an already selected node
    }),

    getters: {
        /**
         * When load is complete and was a success
         *
         * @returns { Boolean }
         */
        hasLoadedOk() {
            return !this.isLoading && !this.hasLoadError;
        },

        /**
         * To tweak the design of the selector when small
         *
         * @returns { Boolean }
         */
        isSmallContainer() {
            return this.containerWidth < 500;
        },
    },

    actions: {
        /**
         * set up the passed properties and start loading the node data
         *
         * @param { object }
         */
        init(props) {
            this.checkForStorybook();
            this.isReadOnly = props.isReadOnly;
            this.formValueName = props.formValueName;
            this.isSelectAll = props.isSelectAll;
            this.canSelectChildren = props.canSelectChildren;
            if (!this.isReadOnly && props.dataEndpoint !== "") {
                //only load when we need to
                apiEndpoint = props.dataEndpoint;
                useNodesStore().load();
            }
            if (props.selectedNodes?.length > 0) {
                //add in any nodes that have been selected previously
                useSelectedNodesStore().add(props.selectedNodes);
            }
        },

        /**
         * When switching between stories, we need to reset the data models
         * as they persist between the stories otherwise
         */
        checkForStorybook() {
            if (typeof STORYBOOK_ENV !== "undefined") {
                useSelectedNodesStore().$reset();
                useSearchStore().$reset();
                useModalStore().$reset();
                useModalViewNodesStore().$reset();
                this.$reset();
            }
        },

        /**
         * to track the width of the container the curriculum selector is in,
         * so we can update the design when appropriate
         *
         * @param { number }
         */
        updateContainerWidth(width) {
            this.containerWidth = width;
        },
    },
});
