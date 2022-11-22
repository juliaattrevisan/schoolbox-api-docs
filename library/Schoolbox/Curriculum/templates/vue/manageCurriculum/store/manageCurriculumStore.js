/**
 * Handles the load of the curriculum data
 */

import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { UNSET_ID } from '@schoolboxCurriculum/js/nodeUtils';
import { useNodesStore } from './mcNodesStore';
import { useViewNodesStore } from '../viewNodes/store/mcViewNodesStore';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';


export let apiEndpoint = '';

export const useStore = defineStore('manageCurriculum', () => {

    const isLoading = ref(true); // to display loading note
    const hasLoadError = ref(false); // to display loading error
    const isProcessing = ref(false); // to notify that an action is taking place, like updating a member access
    const isReadOnly = ref(true); // can only view the currently selected codes, not edit them
    const hasId = ref(false); // whether we should traverse directly to a node id on page load
    const id = ref(UNSET_ID); // the id of the node to traverse directly to
    const toastEl = ref(null); // so we don't need to pass it all around as a prop

    /**
     * When load is complete and and was a success
     *
     * @returns { Boolean }
     */
    const hasLoadedOk = computed(() => {
        return !isLoading.value && !hasLoadError.value;
    });

    /**
     * set up the passed properties and start loading the node data
     *
     * @param { object }
     * @param { ref }
     */
    function init (props, toastElement) {
        checkForNodeHash();
        checkForStorybook();
        isReadOnly.value = props.isReadOnly;
        toastEl.value = toastElement;
        if (props.dataEndpoint !== '') { //only load when we need to
            apiEndpoint = props.dataEndpoint;
            useNodesStore().load(); //load the top level curricula
        }
    }

    /**
     * a node id could be passed into the page via the hash,
     * if so we need to traverse to this node on load
     */
    function checkForNodeHash () {
        const documentURL = document.URL;
        const hashCount = (documentURL.match(/#/g) || []).length; //the number of # found in the url
        const idHash = Number(documentURL.substring(documentURL.indexOf('#')+1, documentURL.length));
        const isIdNumber = Number.isInteger(idHash);
        if (hashCount === 1 && isIdNumber && idHash !== UNSET_ID) { //we check if there's an #, and the string proceeding it is a number
            hasId.value = true;
            id.value = idHash;
        }
    }

    /**
     * When switching between stories, we need to reset the data models
     * as they persist between the stories otherwise
     */
    function checkForStorybook () {
        if (typeof STORYBOOK_ENV !== 'undefined') {
            $reset();
            useViewNodesStore().$reset();
            useActiveNodeStore().$reset();
        }
    }

    return {
        isLoading,
        hasLoadError,
        isProcessing,
        isReadOnly,
        hasId,
        id,
        toastEl,
        hasLoadedOk,
        init,
        checkForStorybook
    }

});