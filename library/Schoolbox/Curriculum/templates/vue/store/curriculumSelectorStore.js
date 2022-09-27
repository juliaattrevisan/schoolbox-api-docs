/**
 * Handles the load of the curriculum data
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './csCodesStore';


export let apiEndpoint = '';


export const useStore = defineStore(`curriculumSelector`, {

    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        isReadOnly: true, //can only view the currently selected codes, not edit them
        containerWidth: 0, //currently so can tweak the styling when the selector is small
        selectedOriginal: [], //an 'original' selection, saved by the user
        selectedDefault: [], //the default selection relevant to the current user role
    }),

    getters: {

        /**
         * When load is complete and was a success
         *
         * @returns { Boolean }
         */
        hasLoadedOk () {
            return !this.isLoading && !this.hasLoadError;
        },

        /**
         * To tweak the design of the selector when small
         *
         * @returns { Boolean }
         */
        isSmallContainer () {
            return this.containerWidth < 500;
        },

    },

    actions: {

        /**
         * load the top level curricula
         *
         * @param { String }
         */
        load (endpoint) {
            apiEndpoint = endpoint;
            useCodesStore().initTopLevel();
        },

        /**
         * to track the width of the container the curriculum selector is in,
         * so we can update the design when appropriate
         *
         * @param { number }
         */
        updateContainerWidth (width) {
            this.containerWidth = width;
        },

    },

});