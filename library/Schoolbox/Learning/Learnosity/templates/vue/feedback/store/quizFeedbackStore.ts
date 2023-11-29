/**
 * General store for individual quiz response
 */

import { defineStore } from "pinia";

export const useStore = defineStore("quizFeedback", () => {
    const isReadOnly = window.Vue.ref<boolean>(false); // whether is in feedback mode, where the teacher can provide feedback to the student
    const isReviewed = window.Vue.ref<boolean>(false); // whether the quiz has been reviewed
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const postFeedback = window.Vue.ref<string>(""); // the feedback url to post to
    const statusPage = window.Vue.ref<string>(""); // the link to the status page

    /**
     * store any passed through properties
     * @param {boolean} $isReadOnly - whether is in feedback mode, where the teacher can provide feedback to the student
     * @param {boolean} $isReviewed - whether the quiz has been reviewed
     * @param {string|undefined} $postFeedback - the feedback url to post to
     * @param {string|undefined} $statusPage - the link to the status page
     */
    function init(
        $isReadOnly: boolean,
        $isReviewed: boolean,
        $postFeedback: string | undefined,
        $statusPage: string | undefined
    ) {
        isReadOnly.value = $isReadOnly;
        isReviewed.value = $isReviewed;
        if ($postFeedback !== undefined) {
            postFeedback.value = $postFeedback;
        }
        if ($statusPage !== undefined) {
            statusPage.value = $statusPage;
        }
    }

    return {
        isReadOnly,
        isReviewed,
        isProcessing,
        isLoaded,
        init,
        isFinaliseReviewModalOpen,
        postFeedback,
        statusPage,
    };
});
