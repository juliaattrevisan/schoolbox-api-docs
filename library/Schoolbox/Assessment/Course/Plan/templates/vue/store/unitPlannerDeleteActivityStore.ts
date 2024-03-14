/**
 * Delete the clicked activity
 */

import { defineStore } from "pinia";
import { useUnitPlannerStore } from "./unitPlannerStore";

export const useDeleteActivityStore = defineStore("unitPlannerDeleteActivity", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed

    /**
     * to display a prompt to confirm a node removal
     */
    function prompt() {
        isOpen.value = true;
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
     */
    async function deleteActivity() {
        console.info("unitPlannerDeleteActivityStore.ts deleteActivity() e");
        try {
            isProcessing.value = true;
            console.info(
                "unitPlannerDeleteActivityStore.ts deleteActivity() TODO: Ask server to delete activity using POST /learning/assessments/{assessmentID}/delete",
            );
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
