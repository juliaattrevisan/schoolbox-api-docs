/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

import { defineStore } from "pinia";
import type { CourseBuilderComponent, Unit } from "@schoolboxAssessment/Course/Plan/templates/vue/unitPlannerTypes";
import type { Column } from "@schoolboxAssessment/Course/Plan/templates/vue/unitPlannerConfig";
import { useUnitPlannerStore } from "@schoolboxAssessment/Course/Plan/templates/vue/store/unitPlannerStore";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";

export const useUnitPlannerActivityListStore = defineStore(`unitPlannerActivityList`, () => {
    const unitPlannerStore = useUnitPlannerStore();

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
        const componentDefinition = unitPlannerStore.template?.activityDefId ?? null;

        if (componentDefinition === null) {
            throw new Error("No Course Builder components exist on the template,");
        }

        const toast = window.sbProps.toastActivate("Creating course builder...");
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

            toast.timeout({ text: "Course builder created successfully" });
        } catch (error) {
            toast.timeout({ text: error as string, isSuccess: false });
        }
    }
    return {
        getComponent,
        createComponent,
    };
});
