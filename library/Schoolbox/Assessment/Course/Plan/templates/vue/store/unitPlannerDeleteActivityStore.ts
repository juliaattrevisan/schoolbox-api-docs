/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useUnitPlannerStore } from "@schoolboxAssessment/Course/Plan/templates/vue/store/unitPlannerStore";
import type { Unit } from "../unitPlannerTypes";

export const useDeleteActivityStore = defineStore("unitPlannerDeleteActivity", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const unit = window.Vue.ref<Unit | null>(null); // The unit to which the activity to be deleted belongs.
    const activity = window.Vue.ref<Activity | null>(null); // The activity to be deleted.

    const unitPlannerStore = useUnitPlannerStore();

    /**
     * Display a prompt to confirm a node removal.
     * @param {Unit} unitToWhichActivityBelongs The unit to which the activity belongs
     * @param {Activity} activityToDelete The activity to delete
     */
    function prompt(unitToWhichActivityBelongs: Unit, activityToDelete: Activity) {
        isOpen.value = true;
        activity.value = activityToDelete;
        unit.value = unitToWhichActivityBelongs;
    }

    /**
     * to hide the confirmation prompt
     */
    function cancel() {
        isOpen.value = false;
    }

    /**
     * Activity removal has been confirmed so ask the server to delete the activity,
     * and then remove it from the UI if the back end did its part.
     * @returns {string} The toast message to display
     */
    async function deleteActivity(): Promise<string> {
        if (activity.value !== null) {
            try {
                isProcessing.value = true;
                const response = await window.fetch(activity.value._links.delete, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                });
                isProcessing.value = false;

                if (response.ok) {
                    isOpen.value = false;

                    if (unit.value !== null) {
                        unitPlannerStore.removeActivityFromUnit(unit.value, activity.value);
                    }

                    return "Activity deleted";
                }
            } catch (error) {
                throw new Error();
            }
        }

        // It is expected that the request will be sent, and the response will be okay.
        // So if execution gets to here, an error has occurred.
        throw new Error();
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        deleteActivity,
        cancel,
    };
});
