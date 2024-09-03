/**
 * General store for individual quiz response
 */

import { defineStore } from "pinia";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import { MARK_TYPE_NONE } from "@schoolboxAssessment/Types/MarkType";

export const useStore = defineStore("quizFeedback", () => {
    const isReadOnly = window.Vue.ref<boolean>(false); // whether is in feedback mode, where the teacher can provide feedback to the student
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const hasMarkVisibility = window.Vue.ref<boolean>(false); // whether marks and feedback are visible to the user
    const statusPage = window.Vue.ref<string>(""); // the link to the status page
    const markData = window.Vue.ref<AssessmentMarkData>({
        type: { name: "", slug: MARK_TYPE_NONE },
        submissionStates: [],
    }); // the mark type data linked to the assessment

    /**
     * store any passed through properties
     * @param $isReadOnly - whether is in feedback mode, where the teacher can provide feedback to the student
     * @param $hasMarkVisibility - whether marks and feedback are visible to the user
     * @param $markData - the mark type data linked to the assessment
     * @param $statusPage - the link to the status page
     */
    function init(
        $isReadOnly: boolean,
        $hasMarkVisibility: boolean,
        $markData: AssessmentMarkData,
        $statusPage: string | undefined,
    ): void {
        isReadOnly.value = $isReadOnly;
        hasMarkVisibility.value = $hasMarkVisibility;
        markData.value = $markData;
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
        markData,
        statusPage,
    };
});
