import { defineStore } from "pinia";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Activity, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";

// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- we want the inferred type from defineStore
export const useActivitiesListStore = (activitiesListStoreId: string) =>
    defineStore(`activitiesListStore/${activitiesListStoreId}`, () => {
        const routes = window.Vue.ref<ActivitiesListRoutes>({
            create: null,
            reorder: null,
        });
        const activities = window.Vue.ref<Activity[]>([]);

        /**
         * store any passed through properties
         * @param $activities - the initial set of activities, in sequence
         * @param $activitiesListRoutes - the routes relevant to actions on an activities list
         */
        function init($activities: Activity[], $activitiesListRoutes: ActivitiesListRoutes): void {
            activities.value = $activities;
            routes.value = $activitiesListRoutes;
        }

        /**
         * Save the sequence of the activities to the database
         * @param newActivityIDSequence - the new sequence of activity ids
         */
        async function saveActivitySequence(newActivityIDSequence: number[]): Promise<void> {
            if (!routes.value.reorder) {
                return; // User does not have access to resequence
            }

            activate("Saving...");
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

                timeout({ text: "Activity order saving successfully" });
            } catch (error) {
                timeout({ text: error as string, isSuccess: false });
            }
        }

        /**
         * Resequences and saves a new activity order
         * @param newActivityIDSequence - the new sequence of activity ids
         */
        function resequenceActivities(newActivityIDSequence: number[]): void {
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

        /**
         * Remove an activity from the store
         * @param activityId - the activity ID to remove
         */
        function removeActivity(activityId: number): void {
            activities.value = activities.value.filter((activity) => activity.id !== activityId);
        }

        return {
            init,
            activities,
            routes,
            saveActivitySequence,
            resequenceActivities,
            removeActivity,
        };
    });
