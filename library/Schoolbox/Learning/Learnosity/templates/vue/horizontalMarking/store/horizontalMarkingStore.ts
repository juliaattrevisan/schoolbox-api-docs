/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const postFeedback = window.Vue.ref<string>(""); // the feedback url to post to
    const statusPage = window.Vue.ref<string>(""); // to show the finalise review modal

    /**
     * store any passed through properties
     * @param {string} $postFeedback - the feedback url to post to
     * @param { string } $statusPage - the link to the status page
     */
    function init($postFeedback: string, $statusPage: string) {
        postFeedback.value = $postFeedback;
        statusPage.value = $statusPage;
    }

    return {
        isProcessing,
        isLoaded,
        init,
        isFinaliseReviewModalOpen,
        postFeedback,
        statusPage,
    };
});
