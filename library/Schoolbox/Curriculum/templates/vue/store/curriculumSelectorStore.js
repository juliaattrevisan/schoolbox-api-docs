/**
 * Handles the load of the curriculum data
 */

import { defineStore } from 'pinia';
import { randomId } from '@js/util/jsUtils';
import { useCodesStore } from './csCodesStore';


export let apiEndpoint = '';
// export let uniqueId = 0;

export const useStore = defineStore(`curriculumSelector__${randomId()}`, {

    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        isReadOnly: true, //can only view the currently selected codes, not edit them
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
         * load the top level curricula
         *
         * @param { String }
         */
        load (endpoint) {
            apiEndpoint = endpoint;
            const codesStore = useCodesStore();
            codesStore.initTopLevel();
        },

    },

});