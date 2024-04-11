/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListGlobalStore } from "./activitiesListGlobalStore";

export const useActivitiesListDeleteModalStore = defineStore("activitiesListDeleteModalStore", () => {
    const globalStore = useActivitiesListGlobalStore();

    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const activityToDelete = window.Vue.ref<Activity | null>(null); // The activity to be deleted.
    const callbackOnDeleted = window.Vue.ref<() => void>(() => {}); // callback function to be called on activity successful deletion

    /**
     * Display a prompt to confirm a node removal.
     * @param {Activity} $activityToDelete The activity to delete
     * @param { () => void } $callbackOnDeleted - callback function to be called on activity successful deletion
     */
    function prompt($activityToDelete: Activity, $callbackOnDeleted: () => void) {
        isOpen.value = true;
        activityToDelete.value = $activityToDelete;
        callbackOnDeleted.value = $callbackOnDeleted;
    }

    /**
     * To hide the confirmation prompt
     */
    function close() {
        isOpen.value = false;
        isProcessing.value = false;
        activityToDelete.value = null;
        callbackOnDeleted.value = () => {};
    }

    /**
     * Activity removal has been confirmed so ask the server to delete the activity,
     * then call the callback function and close the modal.
     */
    async function confirm() {
        if (activityToDelete.value === null) {
            return; // nothing to do
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
        globalStore.toastEl!.activate("Deleting...");
        isProcessing.value = true;

        try {
            const response = await window.fetch(activityToDelete.value._links.delete, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
            });

            if (!response.ok) {
                throw new Error(await extractErrorMessage(response));
            }
        } catch (error) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            globalStore.toastEl!.timeout(error, false, 2000);
            isProcessing.value = false;
            throw error;
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
        globalStore.toastEl!.timeout("Activity Deleted", true, 5000);

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
