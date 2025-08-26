import { defineStore } from "pinia";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export const useMomentFormStore = defineStore("momentForm", () => {
    const isCreate = window.Vue.ref<boolean>(true);
    const moment = window.Vue.ref<LearningMoment | null>(null);

    /**
     * Initialise the store
     * @param $isCreate Whether we are creating a new learning moment (true) or editing an existing one (false)
     * @param $moment The learning moment relevant to this form
     */
    function init($isCreate: boolean, $moment: LearningMoment): void {
        isCreate.value = $isCreate;
        moment.value = $moment;
    }

    return {
        init,
        isCreate,
        moment,
    };
});
