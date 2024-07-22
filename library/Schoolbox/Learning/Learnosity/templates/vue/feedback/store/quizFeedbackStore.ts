/**
 * General store for individual quiz response
 */

import { defineStore } from "pinia";

export const useStore = defineStore("quizFeedback", () => {
    const isReadOnly = window.Vue.ref<boolean>(false); // whether is in feedback mode, where the teacher can provide feedback to the student
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const hasMarkVisibility = window.Vue.ref<boolean>(false); // whether marks and feedback are visible to the user
    const postFeedback = window.Vue.ref<string>(""); // the feedback url to post to
    const maxMarks = window.Vue.ref<string>(""); // the maximum marks for the quiz
    const statusPage = window.Vue.ref<string>(""); // the link to the status page

    /**
     * store any passed through properties
     * @param {boolean} $isReadOnly - whether is in feedback mode, where the teacher can provide feedback to the student
     * @param {boolean} $hasMarkVisibility - whether marks and feedback are visible to the user
     * @param {string|undefined} $postFeedback - the feedback url to post to
     * @param {string|undefined} $maxMarks - the maximum marks for the quiz
     * @param {string|undefined} $statusPage - the link to the status page
     */
    function init(
        $isReadOnly: boolean,
        $hasMarkVisibility: boolean,
        $postFeedback: string | undefined,
        $maxMarks: string | undefined,
        $statusPage: string | undefined,
    ) {
        isReadOnly.value = $isReadOnly;
        hasMarkVisibility.value = $hasMarkVisibility;
        if ($maxMarks !== undefined) {
            maxMarks.value = $maxMarks;
        }
        if ($postFeedback !== undefined) {
            postFeedback.value = $postFeedback;
        }
        if ($statusPage !== undefined) {
            statusPage.value = $statusPage;
        }
    }

    return {
        isReadOnly,
        isProcessing,
        isLoaded,
        init,
        isFinaliseReviewModalOpen,
        hasMarkVisibility,
        postFeedback,
        maxMarks,
        statusPage,
    };
});
