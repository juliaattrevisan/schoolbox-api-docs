/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";
import type { StudentItemResponse } from "../types/StudentItemResponse";
import type { StudentAttemptResponse } from "../types/StudentAttemptResponse";
import type { StudentUrls } from "../types/StudentUrls";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const itemResponses = window.Vue.ref<StudentItemResponse[]>([]);
    const attemptResponses = window.Vue.ref<StudentAttemptResponse[]>([]);
    const urls = window.Vue.ref<StudentUrls[]>([]);

    /**
     * store any passed through properties
     * @param {StudentItemResponse[]} $itemResponses - the item responses
     * @param {StudentAttemptResponse[]} $attemptResponses - the attempt responses
     * @param {StudentUrls[]} $urls - the urls
     */
    function init(
        $itemResponses: StudentItemResponse[],
        $attemptResponses: StudentAttemptResponse[],
        $urls: StudentUrls[]
    ) {
        itemResponses.value = $itemResponses;
        attemptResponses.value = $attemptResponses;
        urls.value = $urls;
    }

    return {
        isProcessing,
        isLoaded,
        itemResponses,
        attemptResponses,
        urls,
        init,
    };
});
