/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const toastEl = window.Vue.ref<typeof Toast | null>(null); // so we don't need to pass it all around as a prop
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const statusPage = window.Vue.ref<string>(""); // to show the finalise review modal

    /**
     * store any passed through properties
     * @param { ref } toastElement - the toast element that can pop up
     * @param { string } $statusPage - the link to the status page
     */
    function init(toastElement: typeof Toast, $statusPage: string) {
        toastEl.value = toastElement;
        statusPage.value = $statusPage;
    }

    return {
        isProcessing,
        isLoaded,
        init,
        toastEl,
        isFinaliseReviewModalOpen,
        statusPage,
    };
});
