/**
 * Handles the load of the curriculum data
 */

import { defineStore } from 'pinia';
import { useNodesStore } from './mcNodesStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';


export let apiEndpoint = '';

export const useStore = defineStore('manageCurriculum', {

    state: () => ({
        isLoading: true, //to display loading note
        hasLoadError: false, //to display loading error
        isProcessing: false, //to notify that an action is taking place, like updating a member access
        isReadOnly: true, //can only view the currently selected codes, not edit them
        hasId: false, //whether we should traverse directly to a node id on page load
        id: 0, //the id of the node to traverse directly to
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
         * set up the passed properties and start loading the node data
         *
         * @param { object }
         * @param { ref }
         */
        init (props, toastEl) {
            this.checkForNodeHash();
            this.checkForStorybook();
            this.isReadOnly = props.isReadOnly;
            this.toastEl = toastEl;
            if (props.dataEndpoint !== '') { //only load when we need to
                apiEndpoint = props.dataEndpoint;
                useNodesStore().load(); //load the top level curricula
            }
        },

        /**
         * a node id could be passed into the page via the hash,
         * if so we need to traverse to this node on load
         */
        checkForNodeHash () {
            const documentURL = document.URL;
            const hashCount = (documentURL.match(/#/g) || []).length; //the number of # found in the url
            const id = Number(documentURL.substring(documentURL.indexOf('#')+1, documentURL.length));
            const isIdNumber = Number.isInteger(id);
            if (hashCount === 1 && isIdNumber) { //we check if there's an #, and the string proceeding it is a number
                this.hasId = true;
                this.id = id;
            }
        },

        /**
         * When switching between stories, we need to reset the data models
         * as they persist between the stories otherwise
         */
        checkForStorybook () {
            if (typeof STORYBOOK_ENV !== 'undefined') {
                this.$reset();
                useViewNodesStore().$reset();
                useActiveNodeStore().$reset();
            }
        },

    },

});