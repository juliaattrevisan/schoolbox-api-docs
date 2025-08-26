import { defineStore } from "pinia";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export const useMomentFormStore = defineStore("momentForm", () => {
    const createRoutes = window.Vue.ref<null | LearningMoment["_links"]>(null);
    const moment = window.Vue.ref<LearningMoment | null>(null);
    const folders = window.Vue.ref<{ id: number; title: string }[]>([]);
    const selectedFolders = window.Vue.ref<number[]>([]);

    /**
     * Initialise the store
     * @param $createRoutes The routes to use when creating a new moment, null if editing an existing one
     * @param $moment The learning moment relevant to this form
     * @param $folders The list of available folders to select from
     * @param $selectedFolders The list of currently selected folder IDs
     */
    function init(
        $createRoutes: null | LearningMoment["_links"],
        $moment: LearningMoment,
        $folders: { id: number; title: string }[],
        $selectedFolders: number[],
    ): void {
        createRoutes.value = $createRoutes;
        moment.value = $moment;
        folders.value = $folders;
        selectedFolders.value = $selectedFolders;
    }
    return {
        init,
        createRoutes,
        moment,
        folders,
        selectedFolders,
    };
});
