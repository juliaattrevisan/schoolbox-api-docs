/**
 * Handles the load of the curriculum data
 */

import { defineStore } from 'pinia';
import { useCodesStore } from './mcCodesStore';


export const useStore = defineStore('curriculumSelectorStore', {

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
         * load all the curriculum data
         *
         * @param { String }
         */
        async load (dataEndpoint) {
            try {
                const response = await fetch(dataEndpoint);
                if (response.ok) {
                    const responseJson = await response.json();
                    const codesStore = useCodesStore();
                    codesStore.load(responseJson);
                } else {
                    console.warn(response);
                    this.hasLoadError = true; //show the load error display state
                }
            } catch (error) {
                console.warn(error);
                this.hasLoadError = true; //show the load error display state
            } finally {
                this.isLoading = false; //otherwise show the table
            }
        },

    },

});