import { defineStore } from "pinia";
import { getCohortReport } from "./cohortReportPayload";
import type { CohortReportEntry } from "./cohortReportPayload";
import { rejectLearningMoment } from "../../../Moderation/learningMomentModerationPayload";

export const useCohortReportStore = defineStore("cohortReport", () => {
    let endpoint: string | null = null; // the endpoint to fetch the cohort report data

    const results = window.Vue.ref<CohortReportEntry[]>([]);
    const selectedResults = window.Vue.ref<Record<CohortReportEntry["id"], boolean>>({});

    // When processing actions on entries, this is used to track the state
    const loadingEntries = window.Vue.ref<Record<CohortReportEntry["id"], boolean>>({});

    const isLoading = window.Vue.ref(true);
    const loadError = window.Vue.ref<string | null>(null);

    /**
     * Clear the current data in the store
     * i.e. when applying new filters, since we don't want to keep old results
     */
    function clearData(): void {
        results.value = [];
        selectedResults.value = {};
        isLoading.value = true;
        loadError.value = null;
    }

    /**
     * Load cohort report data
     */
    async function loadData(): Promise<void> {
        if (!endpoint) {
            throw new Error("Cohort report endpoint is not set");
        }

        isLoading.value = true;
        const response = await getCohortReport(endpoint);
        isLoading.value = false;
        if (response.success === false) {
            loadError.value = response.message;
            return;
        }

        // merge with whatever results we already have, there shouldn't be dupes
        results.value.push(...response.data.content);
    }

    /**
     * Initialise the store
     * @param $endpoint - getCohortReportPayload
     */
    async function init($endpoint: string): Promise<void> {
        endpoint = $endpoint;
        clearData();
        await loadData();
    }

    /**
     * @param entryId The entry ID to check
     * @returns whether the entry is selected
     */
    function isEntrySelected(entryId: number): boolean {
        return !!selectedResults.value[entryId];
    }

    /**
     * Toggle selection of a specific entry
     * @param entryId The entry ID to toggle
     */
    function toggleEntrySelection(entryId: number): void {
        if (selectedResults.value[entryId]) {
            delete selectedResults.value[entryId];
        } else {
            selectedResults.value[entryId] = true;
        }
    }

    /**
     * Reject a learning moment
     * @param entryId The entry ID to reject
     * @param rejectionReason The reason for rejection
     */
    async function rejectEntry(entryId: number, rejectionReason: string): Promise<void> {
        const entry = results.value.find((e) => e.id === entryId);
        if (!entry) {
            throw new Error(`Entry with ID ${entryId} not found`);
        }
        if (!entry._links.reject) {
            throw new Error(`Entry with ID ${entryId} cannot be rejected`);
        }

        loadingEntries.value[entryId] = true;
        const toast = window.sbProps.toastActivate("Updating... Please wait");
        const response = await rejectLearningMoment(entry._links.reject, { rejectionReason });
        delete loadingEntries.value[entryId];

        if (response.success === false) {
            toast.timeout({ isSuccess: false, text: response.message });
            return;
        }

        toast.timeout({ isSuccess: true, text: "Saved" });

        // Figure out the new state from the response
        entry.currentState = response.data.currentState;
        Object.assign(entry._links, response.data._links);
    }

    return {
        init,
        results,
        selectedResults,
        isLoading,
        clearData,
        loadData,
        loadError,
        isEntrySelected,
        toggleEntrySelection,
        rejectEntry,
    };
});
