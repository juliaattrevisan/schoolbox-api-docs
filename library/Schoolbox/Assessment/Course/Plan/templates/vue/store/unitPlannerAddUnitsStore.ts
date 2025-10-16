/**
 * Add new units, also contains the prompt to open/close the new unit display
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import locale from "@js/terminology/rosettaStone";
import { useUnitPlannerStore } from "./unitPlannerStore";
import type { Unit } from "../unitPlannerTypes";

export const MAX_UNITS = 20; // maximum number of units that can be added at once

export const useAddUnitsStore = defineStore("unitPlannerAddUnits", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const newUnits = window.Vue.ref<string[]>([""]); // to store the new units being created
    const validationErrors = window.Vue.ref<boolean[]>([]); // to track validation errors for each unit
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create

    /**
     * to display a prompt to add a unit
     */
    function prompt(): void {
        isOpen.value = true;
    }

    /**
     * to hide the add prompt
     */
    function cancel(): void {
        isOpen.value = false;
        newUnits.value = [""]; // reset to one empty unit input
        validationErrors.value = []; // reset validation errors
    }

    /**
     * to add another unit to the list of new units being created
     */
    function add(): void {
        newUnits.value.push("");
    }

    /**
     * to remove a unit from the list of new units being created
     * @param index - the index of the unit to remove
     */
    function remove(index: number): void {
        newUnits.value.splice(index, 1);
        validationErrors.value.splice(index, 1); // remove corresponding validation error
    }

    /**
     * Validate all unit names are filled
     * @returns true if all units have names, false otherwise
     */
    function isValid(): boolean {
        validationErrors.value = newUnits.value.map((unit) => unit.trim() === "");
        return !validationErrors.value.some((hasError) => hasError);
    }

    /**
     * pass the new unit details to the backend
     */
    async function create(): Promise<void> {
        // Validate all units have names
        if (!isValid()) {
            return; // Stop if validation fails
        }
        const unitPlannerStore = useUnitPlannerStore();
        const toast = window.sbProps.toastActivate("Adding...");
        try {
            isProcessing.value = true;
            loadError.value = null;
            const response = await apiRequest<Unit[], Record<string, unknown>>({
                url: `/course/plan/${unitPlannerStore.courseId}/newUnits`,
                method: "POST",
                body: {
                    names: newUnits.value.filter((unit) => unit.trim() !== ""),
                },
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to create units");
            }
            const createdUnits = response.data;
            unitPlannerStore.units = [...unitPlannerStore.units, ...createdUnits];
            toast.timeout({ text: `${locale.unit.translate(newUnits.value.length > 1, true)} successfully created` });
            cancel();
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
            loadError.value = errorMessage;
            toast.timeout({ text: errorMessage, isSuccess: false });
        } finally {
            isProcessing.value = false;
        }
    }

    return {
        isProcessing,
        isOpen,
        newUnits,
        validationErrors,
        loadError,
        prompt,
        add,
        remove,
        create,
        cancel,
    };
});
