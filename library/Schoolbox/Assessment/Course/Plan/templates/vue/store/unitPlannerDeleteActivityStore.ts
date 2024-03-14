/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";

export const useDeleteActivityStore = defineStore("unitPlannerDeleteActivity", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const activity = window.Vue.ref<Activity>(null);

    /**
     * Display a prompt to confirm a node removal.
     * @param {Activity} activityToDelete The activity to delete
     */
    function prompt(activityToDelete: Activity) {
        isOpen.value = true;
        activity.value = activityToDelete;
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
        console.info("unitPlannerDeleteActivityStore.ts deleteActivity() e");
        try {
            isProcessing.value = true;
            console.info(
                "unitPlannerDeleteActivityStore.ts deleteActivity() TODO: Ask server to delete activity using POST /learning/assessments/{assessmentID}/delete",
            );
            const response = await window.fetch(activity.value._links.delete, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
            });
            if (response.ok) {
                console.info("unitPlannerDeleteActivityStore.ts deleteActivity() response is okay!");
                isProcessing.value = false;
                isOpen.value = false;
                return "Activity deleted";
            } else {
                console.info("unitPlannerDeleteActivityStore.ts deleteActivity() response is not okay.");
                throw new Error();
            }
        } catch (error) {
            throw new Error();
        } finally {
            isProcessing.value = false;
        }
        console.info("unitPlannerDeleteActivityStore.ts deleteActivity() x");
    }

    return {
        isProcessing,
        isOpen,
        prompt,
        deleteActivity,
        cancel,
    };
});
