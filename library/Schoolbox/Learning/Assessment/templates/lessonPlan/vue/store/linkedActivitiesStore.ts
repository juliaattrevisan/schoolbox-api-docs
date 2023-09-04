import { defineStore } from "pinia";
import Toast from "@schoolboxVue/components/toast.vue";
import { Activity } from "../linkedActivitiesTypes";

export const useLinkedActivitiesStore = defineStore("linkedActivitiesStore", () => {
    const sourceId = window.Vue.ref(0); // The id of the source lesson plan
    const linkedActivities = window.Vue.ref<Activity[]>([]);
    const selectedActivities = window.Vue.ref<Activity[]>([]); // Current state of the selected activities in the modal

    const isSingleUnlinkOpen = window.Vue.ref(false); // Whether the single unlink modal is open or not

    const isOpen = window.Vue.ref(false); // Whether the modal is open or not
    const isNextSelected = window.Vue.ref(false);
    const isPreviousSelected = window.Vue.ref(false);

    const saveInProgress = window.Vue.ref(false);
    const toastEl = window.Vue.ref<typeof Toast | null>(null); // so we don't need to pass it all around as a prop

    /**
     * store any passed through properties
     * @param {number} $sourceId - the id of the source lesson plan
     * @param {Activity[]} $linkedActivities - the linked activities
     * @param {typeof Toast} toastElement - the toast element
     */
    function init($sourceId: number, $linkedActivities: Activity[], toastElement: typeof Toast) {
        sourceId.value = $sourceId;
        linkedActivities.value = $linkedActivities;
        selectedActivities.value = [...linkedActivities.value];
        toastEl.value = toastElement.value;
    }

    /**
     * Return the activities that have been added to the linked activities
     * @returns {Activity[]} - the added activities
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
     * @returns {Activity[]} - the removed activities
     */
    function getRemovedActivities(): Activity[] {
        return linkedActivities.value.filter((linkedActivity) => {
            return !selectedActivities.value.some((selectedActivity) => {
                return selectedActivity.id === linkedActivity.id;
            });
        });
    }

    /**
     * Save the selected activities
     * @param {boolean} confirmUnlinks - whether the user has confirmed they want to unlink activities
     */
    async function saveSelectedActivities(confirmUnlinks = false) {
        const addedActivities = getAddedActivities();
        const removedActivities = getRemovedActivities();

        if (removedActivities.length > 0 && !confirmUnlinks) {
            isNextSelected.value = true;
            return;
        }

        saveInProgress.value = true;
        toastEl.value.activate("Saving...");
        try {
            // Delete the removed activities
            const deleteRequests = removedActivities.map((removedActivity) =>
                fetch(`/learning/assessments/${sourceId.value}/activities/${removedActivity.id}`, {
                    method: "DELETE",
                })
            );

            await Promise.all(deleteRequests);

            // Add the added activities
            const postRequests = addedActivities.map((addedActivity) =>
                fetch(`/learning/assessments/${sourceId.value}/activities/${addedActivity.id}`, {
                    method: "POST",
                })
            );

            await Promise.all(postRequests);

            toastEl.value.timeout("Saved", true, 2000);
        } catch (error) {
            toastEl.value.timeout("Save failed", false, 5000);
        } finally {
            saveInProgress.value = false;
        }

        linkedActivities.value = [...selectedActivities.value];

        // Close any open modals
        isOpen.value = false;
        isSingleUnlinkOpen.value = false;
    }

    /**
     * Add an activity to the selected activities
     * @param {Activity} activity - the activity to add
     * @param {boolean} save - whether to save the changes
     */
    function addActivity(activity: Activity, save = false) {
        selectedActivities.value.push(activity);

        if (save) {
            saveSelectedActivities().catch(() => {
                // noop
            });
        }
    }

    /**
     * Remove an activity from the selected activities
     * @param {Activity} activity - the activity to remove
     * @param {boolean} save - whether to save the changes
     */
    function removeActivity(activity: Activity, save = false) {
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
    function clearChanges() {
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
        toastEl,
        isSingleUnlinkOpen,
        isOpen,
        isNextSelected,
        isPreviousSelected,
        clearChanges,
        linkedActivities,
        selectedActivities,
        addActivity,
        removeActivity,
        getAddedActivities,
        getRemovedActivities,
        saveSelectedActivities,
    };
});
