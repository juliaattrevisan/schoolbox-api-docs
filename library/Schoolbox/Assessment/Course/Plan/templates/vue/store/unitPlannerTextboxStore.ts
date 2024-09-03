import { defineStore } from "pinia";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { Column } from "../unitPlannerConfig";
import { useUnitPlannerStore } from "./unitPlannerStore";

interface TextboxCreateComponentResponseJSON {
    instanceID: number;
}

interface TextboxCreateTextboxResponseJSON {
    id: number;
    heading: string;
    body: string;
    folder: unknown; // It's set, but not relevant to us (should be an object)
    _links: {
        update: string;
        delete: unknown; // It's set, but not relevant to us (should be a string)
    };
}

export const useUnitPlannerTextboxStore = defineStore("unitPlannerTextboxStore", () => {
    const unitPlannerStore = useUnitPlannerStore();
    const unsavedTextboxes = window.Vue.ref<Record<number, Record<string, string>>>({});

    /**
     * Update the textbox content for a unit+column
     * @param unitId - the unit to update the textbox content for
     * @param column - the column to update the textbox content for
     * @param content - the content to update
     */
    function updateTextboxContent(unitId: number, column: Column, content: string): void {
        if (unsavedTextboxes.value[unitId] === undefined) {
            unsavedTextboxes.value[unitId] = {};
        }

        // If the content is the same as the current content, then it's no longer unsaved
        if (unitPlannerStore.units.find((u) => u.id === unitId)?.textboxes.items[column.label]?.body === content) {
            delete unsavedTextboxes.value[unitId][column.id];
            return;
        }

        unsavedTextboxes.value[unitId][column.id] = content;
    }

    /**
     * Create a new textbox in the backend if it doesn't exist
     * @param unitId - the unit to create the textbox for
     * @param column - the column to create the textbox for
     */
    async function createTextbox(unitId: number, column: Column): Promise<void> {
        const storeUnit = unitPlannerStore.units.find((u) => u.id === unitId);
        const componentDefinition = unitPlannerStore.template?.textboxDefId ?? null;

        if (componentDefinition === null) {
            throw new Error("No Textboxes exist on the template,");
        }

        if (!storeUnit) {
            throw new Error("Unit not found");
        }

        if (storeUnit.textboxes._routes.createComponent === null) {
            throw new Error("No route to create a component");
        }

        if (storeUnit.textboxes._routes.createTextbox === null) {
            throw new Error("No route to create a textbox");
        }

        const selectedTextbox = storeUnit.textboxes.items[column.label];

        if (selectedTextbox === undefined) {
            throw new Error(`A textbox does not exist for the column '${column.label}'`);
        }

        if (selectedTextbox.textboxId >= 0) {
            return; // The textbox already exists, no action required
        }

        activate("Creating text box...");

        try {
            // First create a new component if it doesn't already exist
            if (selectedTextbox.componentId < 0) {
                const formData = new FormData();
                formData.append("cid", storeUnit.folderId.toString());
                formData.append("add", componentDefinition.toString());

                const responseComponent = await fetch(storeUnit.textboxes._routes.createComponent, {
                    method: "POST",
                    body: formData,
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!responseComponent.ok) {
                    throw new Error(await extractErrorMessage(responseComponent));
                }

                const responseComponentJSON = (await responseComponent.json()) as TextboxCreateComponentResponseJSON;
                selectedTextbox.componentId = responseComponentJSON.instanceID;
            }

            // Then create the textbox
            const response = await fetch(
                `${storeUnit.textboxes._routes.createTextbox}/component/${selectedTextbox.componentId}`,
                {
                    method: "POST",
                    body: JSON.stringify({
                        body: "",
                        heading: column.label,
                        id: null,
                    }),
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                    },
                },
            );

            if (!response.ok) {
                throw new Error(await extractErrorMessage(response));
            }

            // If we just created a textbox, then we need to update the store with the new textbox.
            const responseJSON = (await response.json()) as TextboxCreateTextboxResponseJSON;
            storeUnit.textboxes.items[column.label] = {
                componentId: selectedTextbox.componentId,
                textboxId: responseJSON.id,
                body: responseJSON.body,
                inTemplate: true,
                _links: {
                    update: responseJSON._links.update,
                },
            };

            timeout({ text: "Text box created successfully" });
        } catch (error) {
            timeout({ text: (error as Error).message, isSuccess: false });
        }
    }

    /**
     * Save the updated textbox content for a unit+column
     * @param unitId - the unit to save the textbox content for
     * @param column - the column to save the textbox content for
     */
    async function saveTextboxContent(unitId: number, column: Column): Promise<void> {
        const storeUnit = unitPlannerStore.units.find((u) => u.id === unitId);
        if (!storeUnit) {
            throw new Error("Unit not found");
        }
        const selectedTextbox = storeUnit.textboxes.items[column.label];

        if (selectedTextbox === undefined) {
            throw new Error(`A textbox does not exist for the column '${column.label}'`);
        }

        if (selectedTextbox.textboxId < 0) {
            throw new Error("This textbox does not exist yet, it must be created first");
        }

        if (selectedTextbox._links.update === null) {
            throw new Error("No route to update the textbox");
        }

        const content = unsavedTextboxes.value[unitId]?.[column.id];
        if (content === undefined) {
            return;
        }

        activate("Saving...");

        try {
            const response = await fetch(selectedTextbox._links.update, {
                method: "PUT",
                body: JSON.stringify({
                    body: content,
                    heading: column.label,
                }),
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
            });

            if (!response.ok) {
                throw new Error(await extractErrorMessage(response));
            }

            // To finish off, update the store with the new content and remove the unsaved state
            selectedTextbox.body = content;
            delete unsavedTextboxes.value[unitId][column.id];

            timeout({ text: "Text box saved successfully" });
        } catch (error) {
            timeout({ text: (error as Error).message, isSuccess: false });
        }
    }

    /**
     * Determine for a given unit+column if the textbox exists in the backend
     * @param unitId - the unit to check the textbox for
     * @param column - the column to check the textbox for
     * @returns true if the textbox and component exist, false otherwise
     */
    function textboxIsCreated(unitId: number, column: Column): boolean {
        const storeUnit = unitPlannerStore.units.find((u) => u.id === unitId);
        if (!storeUnit) {
            return false;
        }

        const selectedTextbox = storeUnit.textboxes.items[column.label];
        if (!selectedTextbox) {
            return false;
        }

        return selectedTextbox.textboxId >= 0 && selectedTextbox.componentId >= 0;
    }

    return {
        unsavedTextboxes,
        createTextbox,
        updateTextboxContent,
        saveTextboxContent,
        textboxIsCreated,
    };
});
