import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import {
    bulkPublishLearningMoments,
    bulkRejectLearningMoments,
} from "@schoolboxLearningMomentEvidence/learningMomentModerationPayload";
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { getCohortReport } from "./cohortReportPayload";
import type { CohortReportEntry, CohortReportApiPayload } from "./cohortReportPayload";

export const useCohortReportStore = defineStore("cohortReport", () => {
    let getCohortReportEndpoint: string | null = null; // the endpoint to fetch the cohort report data
    let putBulkRejectEndpoint: string | null = null; // the endpoint to bulk reject learning moments
    let putBulkPublishEndpoint: string | null = null; // the endpoint to bulk publish learning moments

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
     * @param $putBulkRejectEndpoint - learningMomentModeration.putBulkReject
     * @param $putBulkPublishEndpoint - learningMomentModeration.putBulkPublish
     */
    async function init(
        $getCohortReportEndpoint: string,
        $putBulkRejectEndpoint: string,
        $putBulkPublishEndpoint: string,
    ): Promise<void> {
        getCohortReportEndpoint = $getCohortReportEndpoint;
        putBulkRejectEndpoint = $putBulkRejectEndpoint;
        putBulkPublishEndpoint = $putBulkPublishEndpoint;
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
     * @param entryIds The entry ID(s) to publish
     * @param deleteComments Whether to delete comments associated with the learning moment
     */
    async function publishEntries(entryIds: number[], deleteComments: boolean): Promise<void> {
        if (putBulkPublishEndpoint === null) {
            throw new Error("Bulk publish endpoint is not set");
        }

        const entries = results.value.filter((e) => entryIds.includes(e.id));
        if (entries.length === 0) {
            throw new Error(`No entries found for IDs ${entryIds.join(", ")}`);
        }
        if (!entries.every((e) => e._links.publish)) {
            throw new Error(`Unable to publish an entry (${entryIds.join(", ")})`);
        }

        entries.forEach((entry) => {
            loadingEntries.value[entry.id] = true;
        });
        const toast = window.sbProps.toastActivate(
            entries.length > 1 ? `Approving ${entries.length} learning moments` : "Approving learning moment",
        );

        const response = await bulkPublishLearningMoments(putBulkPublishEndpoint, {
            momentIds: entryIds,
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

            if (deleteComments) {
                entry.hasComments = false; // assume comments are deleted
            }
        });
    }

    /**
     * Reject learning moment(s)
     * @param entryIds The entry ID(s) to reject
     * @param rejectionReason The reason for rejection
     */
    async function rejectEntries(entryIds: number[], rejectionReason: string): Promise<void> {
        if (putBulkRejectEndpoint === null) {
            throw new Error("Bulk reject endpoint is not set");
        }

        const entries = results.value.filter((e) => entryIds.includes(e.id));
        if (entries.length === 0) {
            throw new Error(`No entries found for IDs ${entryIds.join(", ")}`);
        }
        if (!entries.every((e) => e._links.reject)) {
            throw new Error(`Unable to reject an entry (${entryIds.join(", ")})`);
        }

        entries.forEach((entry) => {
            loadingEntries.value[entry.id] = true;
        });
        const toast = window.sbProps.toastActivate(
            entries.length > 1 ? `Requesting update for ${entries.length} learning moments` : "Requesting update",
        );

        const response = await bulkRejectLearningMoments(putBulkRejectEndpoint, {
            momentIds: entryIds,
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

            entry.hasComments = true; // assume this generated a comment, blank comments seem to count
        });
    }

    /**
     * Delete learning moment(s)
     * @param entryIds The entry ID(s) to delete
     */
    async function deleteEntries(entryIds: number[]): Promise<void> {
        // TODO find a bulk delete endpoint?
        const entries = results.value.filter((e) => entryIds.includes(e.id));
        if (entries.length === 0) {
            throw new Error(`No entries found for IDs ${entryIds.join(", ")}`);
        }

        const toast = window.sbProps.toastActivate(
            entries.length > 1 ? `Deleting ${entries.length} learning moments` : "Deleting learning moment",
        );
        const unsuccessfulIds: number[] = [];
        await Promise.all(
            entries.map(async (entry) => {
                if (!entry._links.delete) {
                    throw new Error(`Unable to delete an entry (${entry.id})`);
                }

                loadingEntries.value[entry.id] = true;
                const response = await apiRequest({
                    url: entry._links.delete,
                    method: "DELETE",
                });

                if (response.success === false) {
                    toast.timeout({ isSuccess: false, text: response.message });
                    delete loadingEntries.value[entry.id];
                    unsuccessfulIds.push(entry.id);
                }
            }),
        );

        if (unsuccessfulIds.length === 0) {
            toast.timeout({ isSuccess: true, text: "Deleted." });
        }

        results.value = results.value.filter((e) => !entryIds.includes(e.id) || unsuccessfulIds.includes(e.id));
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
