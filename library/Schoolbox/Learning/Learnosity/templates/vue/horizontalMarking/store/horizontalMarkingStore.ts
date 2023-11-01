/**
 * General store for horizontal marking
 */

import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";

export const useStore = defineStore("horizontalMarking", () => {
    const isProcessing = window.Vue.ref<boolean>(false); // to disable various events while processing, like trying to mark the next card before the current one has finished marking
    const isLoaded = window.Vue.ref<boolean>(false); // to show the loading spinner while the reports are loading
    const toastEl = window.Vue.ref<typeof Toast | null>(null); // so we don't need to pass it all around as a prop

    /**
     * store any passed through properties
     * @param { ref } toastElement - the toast element that can pop up
     */
    function init(toastElement: typeof Toast) {
        toastEl.value = toastElement;
    }

    return {
        isProcessing,
        isLoaded,
        init,
        toastEl,
    };
});
