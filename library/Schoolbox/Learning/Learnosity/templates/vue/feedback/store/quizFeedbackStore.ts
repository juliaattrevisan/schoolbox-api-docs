/**
 * General store for individual quiz response
 */

import { defineStore } from "pinia";

export const useStore = defineStore("quizFeedback", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const postFeedback = window.Vue.ref<string>(""); // the feedback url to post to
    const statusPage = window.Vue.ref<string>(""); // the link to the status page

    /**
     * store any passed through properties
     * @param {string|undefined} $postFeedback - the feedback url to post to
     * @param {string|undefined} $statusPage - the link to the status page
     */
    function init($postFeedback: string | undefined, $statusPage: string | undefined) {
        if ($postFeedback !== undefined) {
            postFeedback.value = $postFeedback;
        }
        if ($statusPage !== undefined) {
            statusPage.value = $statusPage;
        }
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
