import { defineStore } from "pinia";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Activity, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import { useActivitiesListGlobalStore } from "./activitiesListGlobalStore";

export const useActivitiesListStore = (activitiesListStoreId: string) =>
    defineStore(`activitiesListStore/${activitiesListStoreId}`, () => {
        const globalStore = useActivitiesListGlobalStore();

        const routes = window.Vue.ref<ActivitiesListRoutes>({
            create: null,
            reorder: null,
        });
        const activities = window.Vue.ref<Activity[]>([]);

        /**
         * store any passed through properties
         * @param { Activity[] } $activities - the initial set of activities, in sequence
         * @param { ActivitiesListRoutes } $activitiesListRoutes - the routes relevant to actions on an activities list
         */
        function init($activities: Activity[], $activitiesListRoutes: ActivitiesListRoutes) {
            activities.value = $activities;
            routes.value = $activitiesListRoutes;
        }

        /**
         * Save the sequence of the activities to the database
         * @param { number[] } newActivityIDSequence - the new sequence of activity ids
         */
        async function saveActivitySequence(newActivityIDSequence: number[]) {
            if (!routes.value.reorder) {
                return; // User does not have access to resequence
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            globalStore.toastEl!.activate("Saving...");
            try {
                const formData = new FormData();
                newActivityIDSequence.forEach((activityID) => {
                    formData.append("id[]", activityID.toString());
                });

                const response = await fetch(routes.value.reorder, {
                    method: "POST",
                    body: formData,
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!response.ok) {
                    throw new Error(await extractErrorMessage(response));
                }

                // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
                globalStore.toastEl!.timeout("Save successful", true, 2000);
            } catch (error) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
                globalStore.toastEl!.timeout(error, false, 2000);
            }
        }

        /**
         * Resequences and saves a new activity order
         * @param { number[] } newActivityIDSequence - the new sequence of activity ids
         */
        function resequenceActivities(newActivityIDSequence: number[]) {
            let hasChanged = false;

            const newActivitiesOrder = newActivityIDSequence.map((activityId) => {
                const activity = activities.value.find((a) => a.id === activityId);
                if (!activity) {
                    throw new Error(`Activity with ID ${activityId} not found`);
                }
                return activity;
            });

            hasChanged = newActivitiesOrder.some((activity, index) => activity.id !== activities.value[index].id);

            if (hasChanged) {
                activities.value = newActivitiesOrder;
                saveActivitySequence(newActivityIDSequence).catch((error) => {
                    throw error;
                });
            }
        }

        return {
            init,
            activities,
            routes,
            saveActivitySequence,
            resequenceActivities,
        };
    });
