/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";
import type { StudentItemQuestionResponse } from "@schoolboxLearnosity/js/types/StudentItemQuestionResponse";
import type { StudentAttemptComment } from "@schoolboxLearnosity/js/types/StudentAttemptComment";
import type { StudentUrls } from "@schoolboxLearnosity/js/types/StudentUrls";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const itemResponses = window.Vue.ref<StudentItemQuestionResponse[]>([]);
    const attemptComments = window.Vue.ref<StudentAttemptComment[]>([]);
    const urls = window.Vue.ref<StudentUrls[]>([]);

    /**
     * store any passed through properties
     * @param {StudentItemQuestionResponse[]} $itemResponses - the item responses
     * @param {StudentAttemptComment[]} $attemptComments - the attempt responses
     * @param {StudentUrls[]} $urls - the urls
     */
    function init(
        $itemResponses: StudentItemQuestionResponse[],
        $attemptComments: StudentAttemptComment[],
        $urls: StudentUrls[]
    ) {
        itemResponses.value = $itemResponses;
        attemptComments.value = $attemptComments;
        urls.value = $urls;
    }

    return {
        isProcessing,
        isLoaded,
        itemResponses,
        attemptComments,
        urls,
        init,
    };
});
