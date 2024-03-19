import { defineStore } from "pinia";
import { extractErrorMessage } from "@js/util/fetchUtils";
import { Column } from "../unitPlannerConfig";
import { useUnitPlannerStore } from "./unitPlannerStore";

export const useUnitPlannerTextboxStore = defineStore("unitPlannerTextboxStore", () => {
    const unitPlannerStore = useUnitPlannerStore();
    const unsavedTextboxes = window.Vue.ref<Record<number, Record<string, string>>>({});

    /**
     * Update the textbox content for a unit+column
     * @param { number } unitId - the unit to update the textbox content for
     * @param { Column } column - the column to update the textbox content for
     * @param { string } content - the content to update
     */
    function updateTextboxContent(unitId: number, column: Column, content: string) {
        if (unsavedTextboxes.value[unitId] === undefined) {
            unsavedTextboxes.value[unitId] = {};
        }

        // If the content is the same as the current content, then it's no longer unsaved
        if (unitPlannerStore.units.find((u) => u.id === unitId)?.textboxes[column.label]?.body === content) {
            delete unsavedTextboxes.value[unitId][column.id];
            return;
        }

        unsavedTextboxes.value[unitId][column.id] = content;
    }

    /**
     * Save the updated textbox content for a unit+column
     * @param { number } unitId - the unit to save the textbox content for
     * @param { Column } column - the column to save the textbox content for
     */
    async function saveTextboxContent(unitId: number, column: Column) {
        const storeUnit = unitPlannerStore.units.find((u) => u.id === unitId);
        if (!storeUnit) {
            throw new Error("Unit not found");
        }
        const selectedTextbox = storeUnit.textboxes[column.label];

        const content = unsavedTextboxes.value[unitId]?.[column.id];
        if (content === undefined) {
            return;
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
        unitPlannerStore.toastEl!.activate("Saving...");

        try {
            let response = null;
            if (selectedTextbox.id < 0) {
                // Textbox has not been created yet, so create one

                // First create a new component
                const formData = new FormData();
                formData.append("cid", storeUnit.folderId.toString());
                formData.append("add", "44");

                const responseComponent = await fetch("/homepage/configForm.php", {
                    method: "POST",
                    body: formData,
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!responseComponent.ok) {
                    throw new Error(await extractErrorMessage(responseComponent));
                }

                const responseComponentJSON = (await responseComponent.json()) as { instanceID: number };

                // Then create the textbox
                response = await fetch(
                    `/textbox/folder/${storeUnit.folderId}/component/${responseComponentJSON.instanceID}`,
                    {
                        method: "POST",
                        body: JSON.stringify({
                            body: content,
                            heading: column.label,
                            id: null,
                        }),
                        headers: {
                            Accept: "application/json",
                            "Content-Type": "application/json",
                        },
                    },
                );
            } else {
                response = await fetch(`/textbox/${selectedTextbox.id}`, {
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
            }

            if (!response.ok) {
                throw new Error(await extractErrorMessage(response));
            }

            if (selectedTextbox.id < 0) {
                // If we just created a textbox, then we need to update the store with the new textbox id
                const responseJSON = (await response.json()) as { id: number };
                storeUnit.textboxes[column.label].id = responseJSON.id;
            }

            // To finish off, update the store with the new content and remove the unsaved state
            storeUnit.textboxes[column.label].body = content;
            delete unsavedTextboxes.value[unitId][column.id];

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            unitPlannerStore.toastEl!.timeout("Save successful", true, 2000);
        } catch (error) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- toasts are untyped
            unitPlannerStore.toastEl!.timeout(error, false, 2000);
        }
    }

    return {
        unsavedTextboxes,
        updateTextboxContent,
        saveTextboxContent,
    };
});
