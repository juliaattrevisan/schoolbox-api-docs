/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

export const useActivitiesListDeleteModalStore = defineStore("activitiesListDeleteModalStore", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const activityToDelete = window.Vue.ref<Activity | null>(null); // The activity to be deleted.
    const callbackOnDeleted = window.Vue.ref<() => void>(() => {}); // callback function to be called on activity successful deletion

    /**
     * Display a prompt to confirm a node removal.
     * @param $activityToDelete The activity to delete
     * @param $callbackOnDeleted - callback function to be called on activity successful deletion
     */
    function prompt($activityToDelete: Activity, $callbackOnDeleted: () => void): void {
        isOpen.value = true;
        activityToDelete.value = $activityToDelete;
        callbackOnDeleted.value = $callbackOnDeleted;
    }

    /**
     * To hide the confirmation prompt
     */
    function close(): void {
        isOpen.value = false;
        isProcessing.value = false;
        activityToDelete.value = null;
        callbackOnDeleted.value = (): void => {};
    }

    /**
     * Activity removal has been confirmed so ask the server to delete the activity,
     * then call the callback function and close the modal.
     */
    async function confirm(): Promise<void> {
        if (activityToDelete.value === null) {
            return; // nothing to do
        }

        if (activityToDelete.value._links === null || activityToDelete.value._links.delete === null) {
            return; // user does not have access to delete
        }

        activate("Deleting...");
        isProcessing.value = true;

        try {
            const response = await window.fetch(activityToDelete.value._links.delete, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
            });

            if (!response.ok) {
                throw new Error(await extractErrorMessage(response));
            }
        } catch (error) {
            timeout({ text: error as string, isSuccess: false });
            isProcessing.value = false;
            throw error;
        }

        timeout({ text: "Activity Deleted", isSuccess: true });

        callbackOnDeleted.value();
        close();
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        confirm,
        close,
    };
});
