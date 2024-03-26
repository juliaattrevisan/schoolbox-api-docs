/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useUnitPlannerStore } from "@schoolboxAssessment/Course/Plan/templates/vue/store/unitPlannerStore";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Unit } from "../unitPlannerTypes";

export const useDeleteActivityStore = defineStore("unitPlannerDeleteActivity", () => {
    const unitPlannerStore = useUnitPlannerStore();

    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const selectedUnit = window.Vue.ref<Unit | null>(null); // The unit to which the activity to be deleted belongs.
    const selectedActivity = window.Vue.ref<Activity | null>(null); // The activity to be deleted.

    /**
     * Display a prompt to confirm a node removal.
     * @param {Unit} unitToWhichActivityBelongs The unit to which the activity belongs
     * @param {Activity} activityToDelete The activity to delete
     */
    function prompt(unitToWhichActivityBelongs: Unit, activityToDelete: Activity) {
        isOpen.value = true;
        selectedUnit.value = unitToWhichActivityBelongs;
        selectedActivity.value = activityToDelete;
    }

    /**
     * To hide the confirmation prompt
     */
    function cancel() {
        isOpen.value = false;
    }

    /**
     * Remove the activity from the unit.
     * @param { number } unitId - the unit ID to remove the activity from
     * @param { number } activityId - the activity ID to remove
     */
    function removeActivityFromUnit(unitId: number, activityId: number) {
        const storeUnit = unitPlannerStore.units.find((unit) => unit.id === unitId);
        if (storeUnit === undefined) {
            return;
        }
        const storeActivity = storeUnit.activities.find((activity) => activity.id === activityId);
        if (storeActivity === undefined) {
            return;
        }

        // Remove the found activity.
        storeUnit.activities = storeUnit.activities.filter((activity) => activity.id !== activityId);
    }

    /**
     * Activity removal has been confirmed so ask the server to delete the activity,
     * and then remove it from the UI if the back end did its part.
     */
    async function deleteActivity() {
        if (selectedUnit.value === null || selectedActivity.value === null) {
            return; // nothing to do
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
        unitPlannerStore.toastEl!.activate("Deleting...");
        isProcessing.value = true;

        try {
            const response = await window.fetch(selectedActivity.value._links.delete, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
            });

            if (!response.ok) {
                throw new Error(await extractErrorMessage(response));
            }
        } catch (error) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            unitPlannerStore.toastEl!.timeout(error, false, 2000);
            throw error;
        }

        removeActivityFromUnit(selectedUnit.value.id, selectedActivity.value.id);
        isProcessing.value = false;
        isOpen.value = false;
        selectedUnit.value = null;
        selectedActivity.value = null;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
        unitPlannerStore.toastEl!.timeout("Activity Deleted", true, 5000);
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        deleteActivity,
        cancel,
    };
});
