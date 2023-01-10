/**
 * Handles the load of the curriculum data
 */

import { ref, computed } from "vue";
import { defineStore } from "pinia";
import { UNSET_ID } from "@schoolboxCurriculum/js/nodeUtils";
import { useNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/store/mcNodesStore";
import { useViewNodesStore } from "@schoolboxCurriculum/vue/manageCurriculum/viewNodes/store/mcViewNodesStore";
import { useActiveNodeStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcActiveNodeStore";
import Toast from "@schoolboxVue/components/toast.vue";

export const useStore = defineStore("manageCurriculum", () => {
    let apiEndpoint = ""; // where to load the curriculum api from
    let uiLimit = 0;
    const isLoading = ref(true); // to display loading note
    const hasLoadError = ref(false); // to display loading error
    const isProcessing = ref(false); // to notify that an action is taking place, like updating a member access
    const isReadOnly = ref(true); // can only view the currently selected codes, not edit them
    const hasId = ref(false); // whether we should traverse directly to a node id on page load
    const id = ref(UNSET_ID); // the id of the node to traverse directly to
    const toastEl = ref<typeof Toast | null>(null); // so we don't need to pass it all around as a prop

    /**
     * When load is complete and was a success
     *
     * @returns { boolean } whether the nodes have loaded
     */
    const hasLoadedOk = computed(() => !isLoading.value && !hasLoadError.value);

    /**
     * Get the url of the curriculum api
     *
     * @returns { string } the url of the curriculum api
     */
    function getApiEndpoint(): string {
        return apiEndpoint;
    }

    /**
     * Get default number of nodes to retrieve using the api
     *
     * @returns { number }
     */
    function getUILimit(): number {
        return uiLimit;
    }

    /**
     * a node id could be passed into the page via the hash,
     * if so we need to traverse to this node on load
     */
    function checkForNodeHash() {
        const documentURL = document.URL;
        const hashCount = (documentURL.match(/#/g) || []).length; // the number of # found in the url
        const idHash = Number(documentURL.substring(documentURL.indexOf("#") + 1, documentURL.length));
        const isIdNumber = Number.isInteger(idHash);
        if (hashCount === 1 && isIdNumber && idHash !== UNSET_ID) {
            // we check if there's an #, and the string proceeding it is a number
            hasId.value = true;
            id.value = idHash;
        }
    }

    /**
     * When switching between stories, we need to reset the data models
     * as they persist between the stories otherwise
     */
    function checkForStorybook() {
        if (typeof window.STORYBOOK_ENV !== "undefined") {
            $reset();
            useViewNodesStore().$reset();
            useActiveNodeStore().$reset();
        }
    }

    /**
     * set up the passed properties and start loading the node data
     *
     * @param { string } dataEndpoint - the curriculum api url
     * @param { boolean } $isReadOnly - whether the current user can edit the curriculum
     * @param { number } uiLimit - the number of nodes to retrieve using the api
     * @param { ref } toastElement -
     */
    async function init(dataEndpoint: string, $isReadOnly: boolean, dataUiLimit: number, toastElement: typeof Toast): Promise<void> {
        checkForNodeHash();
        checkForStorybook();
        isReadOnly.value = $isReadOnly;
        toastEl.value = toastElement;
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
        toastEl,
        hasLoadedOk,
        getApiEndpoint,
        getUILimit,
        init,
        checkForStorybook,
    };
});
