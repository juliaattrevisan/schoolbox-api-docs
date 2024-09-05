/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";
import { MARK_TYPE_NONE } from "@schoolboxAssessment/Types/MarkType";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const markData = window.Vue.ref<AssessmentMarkData>({
        type: { name: "", slug: MARK_TYPE_NONE },
        submissionStates: [],
    }); // the mark type data linked to the assessment
    const statusPage = window.Vue.ref<string>(""); // to show the finalise review modal

    /**
     * store any passed through properties
     * @param $markData - the mark type data linked to the assessment
     * @param $statusPage - the link to the status page
     */
    function init($markData: AssessmentMarkData, $statusPage: string): void {
        markData.value = $markData;
        statusPage.value = $statusPage;
    }

    return {
        isProcessing,
        isLoaded,
        init,
        isFinaliseReviewModalOpen,
        markData,
        statusPage,
    };
});
