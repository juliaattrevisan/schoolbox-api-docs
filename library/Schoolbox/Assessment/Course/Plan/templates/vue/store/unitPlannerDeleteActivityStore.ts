/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import type { Unit } from "../unitPlannerTypes";
import { useUnitPlannerStore } from "@schoolboxAssessment/Course/Plan/templates/vue/store/unitPlannerStore";

export const useDeleteActivityStore = defineStore("unitPlannerDeleteActivity", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const unit = window.Vue.ref<Unit>(null); // The unit to which the activity to be deleted belongs.
    const activity = window.Vue.ref<Activity>(null); // The activity to be deleted.

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
     * @returns {void}
     */
    async function deleteActivity() {
        try {
            isProcessing.value = true;
            const response = await window.fetch(activity.value._links.delete, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
            });
            if (response.ok) {
                isProcessing.value = false;
                isOpen.value = false;

                unitPlannerStore.removeActivityFromUnit(unit.value, activity.value);

                return "Activity deleted";
            } else {
                throw new Error();
            }
        } catch (error) {
            throw new Error();
        } finally {
            isProcessing.value = false;
        }
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        deleteActivity,
        cancel,
    };
});
