/**
 * General store for individual quiz response
 */

import { defineStore } from "pinia";

export const useStore = defineStore("quizFeedback", () => {
    const assessmentId = window.Vue.ref<number>(0); // the id of the assessment
    const isReadOnly = window.Vue.ref<boolean>(false); // whether is in feedback mode, where the teacher can provide feedback to the student
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const hasMarkVisibility = window.Vue.ref<boolean>(false); // whether marks and feedback are visible to the user
    const statusPage = window.Vue.ref<string>(""); // the link to the status page
    const submissionStates = window.Vue.ref<string[]>([]); // the submission states that be used to override the automatically set mark

    /**
     * store any passed through properties
     * @param {number} $assessmentId - the id of the assessment
     * @param {boolean} $isReadOnly - whether is in feedback mode, where the teacher can provide feedback to the student
     * @param {boolean} $hasMarkVisibility - whether marks and feedback are visible to the user
     * @param {string[]} $submissionStates - the submission states that be used to override the automatically set mark
     * @param {string|undefined} $statusPage - the link to the status page
     */
    function init(
        $assessmentId: number,
        $isReadOnly: boolean,
        $hasMarkVisibility: boolean,
        $submissionStates: string[],
        $statusPage: string | undefined,
    ) {
        assessmentId.value = $assessmentId;
        isReadOnly.value = $isReadOnly;
        hasMarkVisibility.value = $hasMarkVisibility;
        submissionStates.value = $submissionStates;
        if ($statusPage !== undefined) {
            statusPage.value = $statusPage;
        }
    }

    return {
        assessmentId,
        isReadOnly,
        isProcessing,
        isLoaded,
        init,
        isFinaliseReviewModalOpen,
        hasMarkVisibility,
        submissionStates,
        statusPage,
    };
});
