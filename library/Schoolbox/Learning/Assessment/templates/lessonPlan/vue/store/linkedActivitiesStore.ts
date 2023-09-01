/**
 * Store all the filters.
 * Selected filters relate to those currently selected
 * Active filters are those selected at the time of the latest search
 */

import { defineStore } from "pinia";

export type Activity = {
    id: number;
    title: string;
    assessmentType: string;
    closeAt: string;
    closeAtFormatted: string;
};

export const useLinkedActivitiesStore = defineStore("linkedActivitiesStore", () => {
    const linkedActivities = window.Vue.ref<Activity[]>([]);
    const selectedActivities = window.Vue.ref<Activity[]>([]); // Current state of the selected activities in the modal

    /**
     * store any passed through properties
     * @param {Activity[]} $linkedActivities - the linked activities
     */
    function init($linkedActivities: Activity[]) {
        linkedActivities.value = $linkedActivities;
        selectedActivities.value = [...linkedActivities.value];
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
     */
    function saveSelectedActivities() {
        const addedActivities = getAddedActivities();
        const removedActivities = getRemovedActivities();

        console.log("Added activities", addedActivities);
        console.log("Removed activities", removedActivities);

        linkedActivities.value = [...selectedActivities.value];
    }

    /**
     * Add an activity to the selected activities
     * @param {Activity} activity - the activity to add
     * @param {boolean} save - whether to save the changes
     */
    function addActivity(activity: Activity, save = false) {
        selectedActivities.value.push(activity);

        if (save) {
            saveSelectedActivities();
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
            saveSelectedActivities();
        }
    }

    return {
        init,
        linkedActivities,
        selectedActivities,
        addActivity,
        removeActivity,
        getAddedActivities,
        getRemovedActivities,
        saveSelectedActivities,
    };
});
