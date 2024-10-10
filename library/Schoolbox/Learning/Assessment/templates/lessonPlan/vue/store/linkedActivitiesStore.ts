import * as toast from "@schoolboxVue/components/toast/sbxToastEvent";
import { defineStore } from "pinia";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { LessonPlanEvent } from "../../js/event/lessonPlanEvent";

export const useLinkedActivitiesStore = defineStore("linkedActivitiesStore", () => {
    const sourceId = window.Vue.ref(0); // The id of the source lesson plan
    const linkedActivities = window.Vue.ref([] as Activity[]);
    const gradeGradientEnable = window.Vue.ref(false); // Whether the grade gradient is enabled or not
    const isStudentView = window.Vue.ref(false); // Whether the page is in student view or not
    const selectedActivities = window.Vue.ref([] as Activity[]); // Current state of the selected activities in the modal

    const isSingleUnlinkOpen = window.Vue.ref(false); // Whether the single unlink modal is open or not

    const isOpen = window.Vue.ref(false); // Whether the modal is open or not
    const isNextSelected = window.Vue.ref(false);
    const isPreviousSelected = window.Vue.ref(false);

    const saveInProgress = window.Vue.ref(false);

    /**
     * Listen for switches between student and staff view on the page
     */
    function addListeners(): void {
        document.addEventListener(LessonPlanEvent.TO_STUDENT_VIEW, () => {
            isStudentView.value = true;
        });
        document.addEventListener(LessonPlanEvent.TO_STAFF_VIEW, () => {
            isStudentView.value = false;
        });
    }

    /**
     * store any passed through properties
     * @param $sourceId - the id of the source lesson plan
     * @param $linkedActivities - the linked activities
     * @param $gradeGradientEnable - whether the grade gradient is enabled
     */
    function init($sourceId: number, $linkedActivities: Activity[], $gradeGradientEnable: boolean): void {
        sourceId.value = $sourceId;
        linkedActivities.value = $linkedActivities;
        gradeGradientEnable.value = $gradeGradientEnable;
        selectedActivities.value = [...linkedActivities.value];
        addListeners();
    }

    /**
     * Return the activities that have been added to the linked activities
     * @returns - the added activities
     */
    function getAddedActivities(): Activity[] {
        return selectedActivities.value.filter((selectedActivity) => {
            return !linkedActivities.value.some((linkedActivity) => {
                return linkedActivity.id === selectedActivity.id;
            });
        });
    }

    /**
     * Return the activities that have been removed from the linked activities
     * @returns - the removed activities
     */
    function getRemovedActivities(): Activity[] {
        return linkedActivities.value.filter((linkedActivity) => {
            return !selectedActivities.value.some((selectedActivity) => {
                return selectedActivity.id === linkedActivity.id;
            });
        });
    }

    /**
     * Raise some sort of error if a request from `fetch` does not return a
     * success response.
     * @param response - the response from `fetch`
     */
    function throwErrorOnInvalidResponse(response: Response): void {
        if (!response.ok) {
            throw new Error(`Something went wrong (response code: ${String(response.status)})`);
        }
    }

    /**
     * Save the selected activities
     * @param confirmUnlinks - whether the user has confirmed they want to unlink activities
     */
    async function saveSelectedActivities(confirmUnlinks: boolean = false): Promise<void> {
        const addedActivities = getAddedActivities();
        const removedActivities = getRemovedActivities();

        if (removedActivities.length > 0 && !confirmUnlinks) {
            isNextSelected.value = true;
            return;
        }

        saveInProgress.value = true;
        toast.activate("Saving...");
        try {
            // Delete the removed activities
            const deleteRequests = removedActivities.map((removedActivity) =>
                fetch(`/learning/assessments/${sourceId.value}/activities/${removedActivity.id}`, {
                    method: "DELETE",
                }).then(throwErrorOnInvalidResponse),
            );

            await Promise.all(deleteRequests);

            // Add the added activities
            const postRequests = addedActivities.map((addedActivity) =>
                fetch(`/learning/assessments/${sourceId.value}/activities/${addedActivity.id}`, {
                    method: "POST",
                }).then(throwErrorOnInvalidResponse),
            );

            await Promise.all(postRequests);
            toast.timeout({ text: "Saved", isSuccess: true });

            // Only update linked if all activities are updated successfully.
            // This means that if one fails but others succeed, then the
            // activity list is out of sync with the backend.
            linkedActivities.value = [...selectedActivities.value];
        } catch (error) {
            toast.timeout({ text: "Save failed", isSuccess: false });
        } finally {
            saveInProgress.value = false;
        }

        // Close any open modals
        isOpen.value = false;
        isSingleUnlinkOpen.value = false;
    }

    /**
     * Add an activity to the selected activities
     * @param activity - the activity to add
     * @param save - whether to save the changes
     */
    function addActivity(activity: Activity, save: boolean = false): void {
        selectedActivities.value.push(activity);

        if (save) {
            saveSelectedActivities().catch(() => {
                // noop
            });
        }
    }

    /**
     * Remove an activity from the selected activities
     * @param activity - the activity to remove
     * @param save - whether to save the changes
     */
    function removeActivity(activity: Activity, save: boolean = false): void {
        selectedActivities.value = selectedActivities.value.filter((selectedActivity) => {
            return selectedActivity.id !== activity.id;
        });

        if (save) {
            saveSelectedActivities().catch(() => {
                // noop
            });
        }
    }

    /**
     * Clear any changes
     */
    function clearChanges(): void {
        selectedActivities.value = [...linkedActivities.value];
    }

    /**
     * Watch for if isOpen changes and reset the selected activities if it does
     */
    window.Vue.watch(isOpen, (newValue) => {
        if (!newValue) {
            clearChanges();
        }
    });

    return {
        init,
        saveInProgress,
        isSingleUnlinkOpen,
        isOpen,
        isNextSelected,
        isPreviousSelected,
        clearChanges,
        linkedActivities,
        gradeGradientEnable,
        isStudentView,
        selectedActivities,
        addActivity,
        removeActivity,
        getAddedActivities,
        getRemovedActivities,
        saveSelectedActivities,
    };
});
