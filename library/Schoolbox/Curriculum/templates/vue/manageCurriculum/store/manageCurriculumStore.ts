/**
 * Handles the load of the curriculum data
 */

import { defineStore } from "pinia";
import { UNSET_ID, getIdHash, isValidHash } from "@schoolboxCurriculum/js/nodeUtils";
import { useNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore";
import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
import { useActiveNodeStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcActiveNodeStore";

export const useStore = defineStore("manageCurriculum", () => {
    let apiEndpoint = ""; // where to load the curriculum api from
    let uiLimit = 0;
    const isLoading = window.Vue.ref(true); // to display loading note
    const hasLoadError = window.Vue.ref(false); // to display loading error
    const isProcessing = window.Vue.ref(false); // to notify that an action is taking place, like updating a member access
    const isReadOnly = window.Vue.ref(true); // can only view the currently selected codes, not edit them
    const hasId = window.Vue.ref(false); // whether we should traverse directly to a node id on page load
    const id = window.Vue.ref(UNSET_ID); // the id of the node to traverse directly to

    /**
     * When load is complete and was a success
     * @returns { boolean } whether the nodes have loaded
     */
    const hasLoadedOk = window.Vue.computed(() => !isLoading.value && !hasLoadError.value);

    /**
     * Get the url of the curriculum api
     * @returns { string } the url of the curriculum api
     */
    function getApiEndpoint(): string {
        return apiEndpoint;
    }

    /**
     * Get default number of nodes to retrieve using the api
     * @returns { number } the number of nodes to show
     */
    function getUILimit(): number {
        return uiLimit;
    }

    /**
     * When switching between stories, we need to reset the data models
     * as they persist between the stories otherwise
     */
    function checkForStorybook() {
        if (typeof window.STORYBOOK_ENV !== "undefined") {
            // @ts-expect-error - this is available within a pinia store
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            $reset();
            useViewNodesStore().$reset();
            useActiveNodeStore().$reset();
        }
    }

    /**
     * set up the passed properties and start loading the node data
     * @param { string } dataEndpoint - the curriculum api url
     * @param { boolean } $isReadOnly - whether the current user can edit the curriculum
     * @param { number } dataUiLimit - the number of nodes to retrieve using the api
     */
    async function init(dataEndpoint: string, $isReadOnly: boolean, dataUiLimit: number): Promise<void> {
        if (isValidHash()) {
            hasId.value = true;
            id.value = getIdHash();
        }
        checkForStorybook();
        isReadOnly.value = $isReadOnly;
        if (dataEndpoint !== "") {
            // only load when we need to
            apiEndpoint = dataEndpoint;
            uiLimit = dataUiLimit;
            await useNodesStore().load(); // load the top level curricula
        }
    }

    return {
        isLoading,
        hasLoadError,
        isProcessing,
        isReadOnly,
        hasId,
        id,
        hasLoadedOk,
        getApiEndpoint,
        getUILimit,
        init,
        checkForStorybook,
    };
});
