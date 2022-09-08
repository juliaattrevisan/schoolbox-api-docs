/**
 * Handles the load of the curriculum data
 */

import { defineStore } from 'pinia';
import { useNodesStore } from './mcNodesStore';


export let apiEndpoint = '';

export const useStore = defineStore('manageCurriculum', {

    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        isReadOnly: true, //can only view the currently selected codes, not edit them
        toastEl: null, //so we don't need to pass it all around as a prop
    }),

    getters: {

        /**
         * When load is complete and and was a success
         *
         * @returns { Boolean }
         */
        hasLoadedOk () {
            return !this.isLoading && !this.hasLoadError;
        },

    },

    actions: {

        /**
         * load the top level curriculums
         *
         * @param { String }
         */
        load (endpoint) {
            apiEndpoint = endpoint;
            const nodesStore = useNodesStore();
            nodesStore.initTopLevel();
        },

    },

});