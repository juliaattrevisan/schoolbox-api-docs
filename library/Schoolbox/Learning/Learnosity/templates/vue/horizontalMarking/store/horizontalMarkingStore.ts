/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking

    return {
        isProcessing,
    };
});
