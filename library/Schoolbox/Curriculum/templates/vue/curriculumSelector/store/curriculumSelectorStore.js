/**
 * Handles the load of the curriculum data
 */

import { defineStore } from "pinia";
import { useNodesStore } from "./csAllNodesStore";
import { useUnitNodesStore } from "./csUnitNodesStore";
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
        includeWithoutCode: false, // set to true if curriculum nodes with blank codes need to be selectable
        includeChildren: false, // When a node is selected, also select all its children
        includeDisabled: false, // whether to display disabled nodes
        hasTabs: false, // whether to display tabs
        folderId: undefined, // the folderId to load the unit nodes from
    }),

    getters: {
        /**
         * When all loading is complete and was a success
         *
         * @returns { Boolean }
         */
        hasLoadedOk() {
            if (!this.hasTabs) {
                return !this.isLoading && !this.hasLoadError;
            }
            return (
                !this.isLoading &&
                !this.hasLoadError &&
                useUnitNodesStore().hasLoaded &&
                !useUnitNodesStore().hasLoadError
            );
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
            this.includeWithoutCode = props.includeWithoutCode;
            this.includeChildren = props.includeChildren;
            this.includeDisabled = props.includeDisabled;
            this.hasTabs = props.hasTabs;
            this.folderId = props.folderId;
            if (!this.isReadOnly && props.dataEndpoint !== "") {
                //only load when we need to
                apiEndpoint = props.dataEndpoint;
                useNodesStore().load();
            }
            // load in the unit nodes, only displaying the tabs if there are any unit nodes
            if (props.hasTabs && props.folderId !== undefined) {
                useUnitNodesStore().load(props.folderId);
            }
            if (props.selectedNodes?.length > 0) {
                //add in any nodes that have been selected previously
                const selectedNodesStore = useSelectedNodesStore();
                props.selectedNodes.forEach((node) => selectedNodesStore.add(node));
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
