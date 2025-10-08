import { defineStore } from "pinia";
import type { CohortReportEntry } from "./cohortReportPayload";

export enum CohortReportModalType {
    READ_MORE,
    CONFIRM_PUBLISH,
    CONFIRM_REJECT,
    CONFIRM_DELETE,
}

export const useCohortReportModalStore = defineStore("cohortReportModal", () => {
    const isModalOpen = window.Vue.ref(false);
    const selectedEntries = window.Vue.ref<CohortReportEntry[] | null>(null);
    const modalType = window.Vue.ref<CohortReportModalType | null>(null);

    /**
     * Open the modal
     * @param entries - The entry/entries to show in the modal
     * @param type - The type of modal to show
     */
    function openModal(entries: CohortReportEntry | CohortReportEntry[], type: CohortReportModalType): void {
        selectedEntries.value = Array.isArray(entries) ? entries : [entries];
        modalType.value = type;
        isModalOpen.value = true;
    }

    return {
        isModalOpen,
        selectedEntries,
        modalType,
        openModal,
    };
});
