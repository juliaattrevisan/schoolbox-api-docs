import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import {
    bulkPublishLearningMoments,
    bulkRejectLearningMoments,
} from "@schoolboxLearningMomentEvidence/learningMomentPayload";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { getCohortReport } from "./cohortReportPayload";
import type { CohortReportEntry, CohortReportApiPayload } from "./cohortReportPayload";

export const useCohortReportStore = defineStore("cohortReport", () => {
    let getCohortReportEndpoint: string | null = null; // the endpoint to fetch the cohort report data
    let putBulkRejectEndpoint: string | null = null; // the endpoint to bulk reject learning moments
    let putBulkPublishEndpoint: string | null = null; // the endpoint to bulk publish learning moments
    let deleteBulkEvidenceEndpoint: string | null = null; // the endpoint to bulk delete learning moments

    const results = window.Vue.ref<CohortReportEntry[]>([]);
    const selectedResults = window.Vue.ref<Record<CohortReportEntry["id"], boolean>>({});

    const hasSearched = window.Vue.ref<boolean>(false);
    const nextCursor = window.Vue.ref<string | null>(null);

    /**
     * The filters that were used for the last search so that we can keep loading more results
     * even if the user changes the filters without clicking "Apply filters" again
     */
    const activeFilters = window.Vue.ref<Omit<CohortReportApiPayload, "cursor">>({});

    const isLoading = window.Vue.ref(true);
    const loadError = window.Vue.ref<string | null>(null);

    // When processing actions on entries, this is used to track the state
    const loadingEntries = window.Vue.ref<Record<CohortReportEntry["id"], boolean>>({});

    /**
     * Clear the current data in the store
     * i.e. when applying new filters, since we don't want to keep old results
     */
    function clearData(): void {
        results.value = [];
        selectedResults.value = {};
        hasSearched.value = false;
        nextCursor.value = null;
        activeFilters.value = {};
        isLoading.value = true;
        loadError.value = null;
    }

    /**
     * Load the next page of results
     */
    async function loadNextPage(): Promise<void> {
        if (!getCohortReportEndpoint) {
            throw new Error("Cohort report endpoint is not set");
        }

        if (hasSearched.value && nextCursor.value === null) {
            return; // No next page to load
        }

        isLoading.value = true;
        const response = await getCohortReport(getCohortReportEndpoint, {
            cursor: nextCursor.value,
            ...activeFilters.value,
        });
        isLoading.value = false;
        if (response.success === false) {
            loadError.value = response.message;
            return;
        }

        nextCursor.value = response.data.metadata.cursor.next;
        hasSearched.value = true; // mark that nextCursor is now usable

        // merge with whatever results we already have, there shouldn't be dupes
        results.value.push(...response.data.data);
    }

    /**
     * Initialise the store
     * @param $getCohortReportEndpoint - getCohortReportPayload
     * @param $putBulkRejectEndpoint - learningMomentEvidence.putBulkReject
     * @param $putBulkPublishEndpoint - learningMomentEvidence.putBulkPublish
     * @param $deleteBulkEvidenceEndpoint - learningMomentEvidence.deleteBulkDelete
     */
    async function init(
        $getCohortReportEndpoint: string,
        $putBulkRejectEndpoint: string,
        $putBulkPublishEndpoint: string,
        $deleteBulkEvidenceEndpoint: string,
    ): Promise<void> {
        getCohortReportEndpoint = $getCohortReportEndpoint;
        putBulkRejectEndpoint = $putBulkRejectEndpoint;
        putBulkPublishEndpoint = $putBulkPublishEndpoint;
        deleteBulkEvidenceEndpoint = $deleteBulkEvidenceEndpoint;

        clearData();
        await loadNextPage();
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
     * Update the state of an entry based on the response from the API
     * @param entry - The entry to update
     * @param responseData - The response data from the API
     */
    function updateEntryState(
        entry: CohortReportEntry,
        responseData: Record<LearningMoment["id"], LearningMoment>,
    ): void {
        if (responseData[entry.id] === undefined) {
            console.error(`No response data found for entry ID ${entry.id}`);
            return;
        }

        entry.currentState = responseData[entry.id].currentState;
        Object.assign(entry._links, responseData[entry.id]._links);
    }

    /**
     * Publish learning moment(s)
     * @param entries The entry/entries to publish
     * @param deleteComments Whether to delete comments associated with the learning moment
     */
    async function publishEntries(entries: CohortReportEntry[], deleteComments: boolean): Promise<void> {
        if (putBulkPublishEndpoint === null) {
            throw new Error("Bulk publish endpoint is not set");
        }

        if (!entries.every((e) => e._links.publish)) {
            throw new Error("Unable to publish an entry");
        }

        entries.forEach((entry) => {
            loadingEntries.value[entry.id] = true;
        });
        const toast = window.sbProps.toastActivate(
            entries.length > 1 ? `Approving ${entries.length} learning moments` : "Approving learning moment",
        );

        const response = await bulkPublishLearningMoments(putBulkPublishEndpoint, {
            momentIds: entries.map((e) => e.id),
            deleteComments,
        });
        entries.forEach((entry) => {
            delete loadingEntries.value[entry.id];
        });

        if (response.success === false) {
            toast.timeout({ isSuccess: false, text: response.message });
            return;
        }

        toast.timeout({ isSuccess: true, text: "Saved." });

        // Figure out the new state from the response
        entries.forEach((entry) => {
            updateEntryState(entry, response.data);
            delete selectedResults.value[entry.id];

            if (deleteComments) {
                entry.hasComments = false; // assume comments are deleted
            }
        });
    }

    /**
     * Reject learning moment(s)
     * @param entries The entry/entries to reject
     * @param rejectionReason The reason for rejection
     */
    async function rejectEntries(entries: CohortReportEntry[], rejectionReason: string): Promise<void> {
        if (putBulkRejectEndpoint === null) {
            throw new Error("Bulk reject endpoint is not set");
        }

        if (!entries.every((e) => e._links.reject)) {
            throw new Error("Unable to reject an entry");
        }

        entries.forEach((entry) => {
            loadingEntries.value[entry.id] = true;
        });
        const toast = window.sbProps.toastActivate(
            entries.length > 1 ? `Requesting update for ${entries.length} learning moments` : "Requesting update",
        );

        const response = await bulkRejectLearningMoments(putBulkRejectEndpoint, {
            momentIds: entries.map((e) => e.id),
            rejectionReason,
        });
        entries.forEach((entry) => {
            delete loadingEntries.value[entry.id];
        });

        if (response.success === false) {
            toast.timeout({ isSuccess: false, text: response.message });
            return;
        }

        toast.timeout({ isSuccess: true, text: "Saved." });

        // Figure out the new state from the response
        entries.forEach((entry) => {
            updateEntryState(entry, response.data);
            delete selectedResults.value[entry.id];

            entry.hasComments = true; // assume this generated a comment, blank comments seem to count
        });
    }

    /**
     * Delete learning moment(s)
     * @param entries The entry/entries to delete
     */
    async function deleteEntries(entries: CohortReportEntry[]): Promise<void> {
        if (deleteBulkEvidenceEndpoint === null) {
            throw new Error("Bulk delete endpoint is not set");
        }

        if (!entries.every((e) => e._links.delete)) {
            throw new Error("Unable to delete an entry");
        }

        entries.forEach((entry) => {
            loadingEntries.value[entry.id] = true;
        });
        const toast = window.sbProps.toastActivate(
            entries.length > 1 ? `Deleting ${entries.length} learning moments` : "Deleting learning moment",
        );

        const response = await apiRequest({
            url: deleteBulkEvidenceEndpoint,
            method: "DELETE",
            body: {
                momentIds: entries.map((e) => e.id),
            },
        });

        entries.forEach((entry) => {
            delete loadingEntries.value[entry.id];
        });

        if (response.success === false) {
            toast.timeout({ isSuccess: false, text: response.message });
            return;
        }

        entries.forEach((entry) => {
            delete selectedResults.value[entry.id];
        });

        toast.timeout({ isSuccess: true, text: "Deleted." });
        results.value = results.value.filter((e) => !entries.includes(e));
    }

    return {
        init,
        results,
        selectedResults,
        isLoading,
        clearData,
        nextCursor,
        activeFilters,
        loadNextPage,
        loadError,
        isEntrySelected,
        toggleEntrySelection,
        publishEntries,
        rejectEntries,
        deleteEntries,
        loadingEntries,
    };
});
