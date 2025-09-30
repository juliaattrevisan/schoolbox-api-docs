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
    const onConfirm = window.Vue.ref<(() => void) | null>(null);

    /**
     * Open the modal
     * @param entries - The entry/entries to show in the modal
     * @param type - The type of modal to show
     * @param onConfirmCallback - The callback to run when the action is confirmed
     */
    function openModal(
        entries: CohortReportEntry | CohortReportEntry[],
        type: CohortReportModalType,
        onConfirmCallback: (() => void) | null = null,
    ): void {
        selectedEntries.value = Array.isArray(entries) ? entries : [entries];
        modalType.value = type;
        isModalOpen.value = true;
        onConfirm.value = onConfirmCallback;
    }

    return {
        isModalOpen,
        selectedEntries,
        modalType,
        openModal,
        onConfirm,
    };
});
