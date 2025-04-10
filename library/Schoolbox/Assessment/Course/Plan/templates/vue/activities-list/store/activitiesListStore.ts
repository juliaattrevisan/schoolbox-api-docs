import { defineStore } from "pinia";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Activity, ActivitiesListRoutes } from "@schoolboxAssessment/Types/assessmentTypes";
import type { CourseBuilderComponent, Unit } from "../../unitPlannerTypes";
import type { Column } from "../../unitPlannerConfig";
import { useUnitPlannerStore } from "../../store/unitPlannerStore";

// eslint-disable-next-line @typescript-eslint/explicit-function-return-type -- we want the inferred type from defineStore
export const useActivitiesListStore = (activitiesListStoreId: string) =>
    defineStore(`activitiesListStore/${activitiesListStoreId}`, () => {
        const routes = window.Vue.ref<ActivitiesListRoutes>({
            create: null,
            reorder: null,
            createComponent: null,
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

        /**
         * To retrieve the curriculum component id for the column if it's available,
         * so we can retrieve the correct folderNodesStore and load in the appropriate node tree
         * @param components - the components on the unit
         * @param column - the column to match
         * @returns - the component found or null
         */
        function getComponent(components: CourseBuilderComponent[], column: Column): CourseBuilderComponent | null {
            // loop through the curriculum components on the unit, matching the component heading to the column label
            const matchedComponent = components.find(
                (component) => component.heading.trim() === column.label.trim(), // Ignore trailing or leading whitespace
            );
            if (matchedComponent) {
                return matchedComponent;
            }
            return null;
        }

        /**
         * Create a new course builder component for the unit,
         * placing the component on units homepage
         * @param unit - the unit to create the component for
         * @param column - the column to create the component for
         */
        async function createComponent(unit: Unit, column: Column): Promise<void> {
            const unitPlannerStore = useUnitPlannerStore();
            const componentDefinition = unitPlannerStore.template?.activityDefId ?? null;

            if (componentDefinition === null) {
                throw new Error("No Course Builder components exist on the template,");
            }

            activate("Creating course builder...");
            try {
                // First create a new component
                const createFormData = new FormData();
                createFormData.append("cid", unit.folderId.toString());
                createFormData.append("add", componentDefinition.toString());

                const createResponse = await fetch(`${unit.activities._routes.createComponent}`, {
                    method: "POST",
                    body: createFormData,
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!createResponse.ok) {
                    throw new Error(await extractErrorMessage(createResponse));
                }

                const createResponseJSON = (await createResponse.json()) as { instanceID: number };

                const configSearchParams = new FormData();
                configSearchParams.append("cid", unit.folderId.toString());
                configSearchParams.append("type", "config");
                configSearchParams.append("component", createResponseJSON.instanceID.toString());
                configSearchParams.append("componentname", column.label);

                // Then create the component
                const configResponse = await fetch(`${unit.activities._routes.createComponent}`, {
                    method: "POST",
                    body: configSearchParams,
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!configResponse.ok) {
                    throw new Error(await extractErrorMessage(configResponse));
                }

                const newComponent: CourseBuilderComponent = {
                    componentId: createResponseJSON.instanceID,
                    heading: column.label,
                    inTemplate: true,
                };
                unitPlannerStore.addCourseBuilderComponent(unit.id, newComponent);

                timeout({ text: "Course builder created successfully" });
            } catch (error) {
                timeout({ text: error as string, isSuccess: false });
            }
        }

        return {
            init,
            activities,
            routes,
            saveActivitySequence,
            resequenceActivities,
            removeActivity,
            getComponent,
            createComponent,
        };
    });
