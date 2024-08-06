/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";
import type { AssessmentMarkData } from "@schoolboxLearnosity/js/types/AssessmentMarkData";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const isFinaliseReviewModalOpen = window.Vue.ref<boolean>(false); // to show the finalise review modal
    const assessmentId = window.Vue.ref<number>(0); // the id of the assessment
    const markData = window.Vue.ref<AssessmentMarkData>(); // the mark type data linked to the assessment
    const statusPage = window.Vue.ref<string>(""); // to show the finalise review modal

    /**
     * store any passed through properties
     * @param {number} $assessmentId - the id of the assessment
     * @param {AssessmentMarkData} $markData - the mark type data linked to the assessment
     * @param { string } $statusPage - the link to the status page
     */
    function init($assessmentId: number, $markData: AssessmentMarkData, $statusPage: string) {
        assessmentId.value = $assessmentId;
        markData.value = $markData;
        statusPage.value = $statusPage;
    }

    return {
        isProcessing,
        isLoaded,
        init,
        isFinaliseReviewModalOpen,
        assessmentId,
        markData,
        statusPage,
    };
});
