import { defineStore } from "pinia";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Activity } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListGlobalStore } from "./activitiesListGlobalStore";

export const useActivitiesListStore = (activitiesListStoreId: string) =>
    defineStore(`activitiesListStore/${activitiesListStoreId}`, () => {
        const activitiesListGlobalStore = useActivitiesListGlobalStore();

        const activities = window.Vue.ref<Activity[]>([]);

        /**
         * store any passed through properties
         * @param { Activity[] } $activities - the initial set of activities, in sequence
         */
        function init($activities: Activity[]) {
            activities.value = $activities;
        }

        return {
            init,
            activities,
        };
    });
