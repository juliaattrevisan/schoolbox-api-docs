import { defineStore } from "pinia";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export const useMomentFormStore = defineStore("momentForm", () => {
    const isCreate = window.Vue.ref<boolean>(true);
    const moment = window.Vue.ref<LearningMoment | null>(null);
    const folders = window.Vue.ref<{ id: number; title: string }[]>([]);
    const selectedFolders = window.Vue.ref<number[]>([]);

    /**
     * Initialise the store
     * @param $isCreate Whether we are creating a new learning moment (true) or editing an existing one (false)
     * @param $moment The learning moment relevant to this form
     * @param $folders The list of available folders to select from
     * @param $selectedFolders The list of currently selected folder IDs
     */
    function init(
        $isCreate: boolean,
        $moment: LearningMoment,
        $folders: { id: number; title: string }[],
        $selectedFolders: number[],
    ): void {
        isCreate.value = $isCreate;
        moment.value = $moment;
        folders.value = $folders;
        selectedFolders.value = $selectedFolders;
    }

    return {
        init,
        isCreate,
        moment,
        folders,
        selectedFolders,
    };
});
