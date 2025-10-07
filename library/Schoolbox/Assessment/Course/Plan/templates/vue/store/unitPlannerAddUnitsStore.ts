/**
 * Add new units, also contains the prompt to open/close the new unit display
 */

import { defineStore } from "pinia";
import { useUnitPlannerStore } from "./unitPlannerStore";
import type { Unit } from "../unitPlannerTypes";

export const useAddUnitsStore = defineStore("unitPlannerAddUnits", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const newUnits = window.Vue.ref<string[]>([""]); // to store the new units being created
    const validationErrors = window.Vue.ref<boolean[]>([]); // to track validation errors for each unit

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
    function validate(): boolean {
        validationErrors.value = newUnits.value.map((unit) => unit.trim() === "");
        return !validationErrors.value.some((hasError) => hasError);
    }

    /**s
     * Get the data to pass to the backend
     * @returns the form data
     */
    function getFormData(): string {
        return "temp";
    }

    /**
     * pass the new unit details to the backend
     */
    async function create(): Promise<void> {
        // Validate all units have names
        if (!validate()) {
            return; // Stop if validation fails
        }
        const unitPlannerStore = useUnitPlannerStore();
        const toast = window.sbProps.toastActivate("Adding...");
        try {
            isProcessing.value = true;
            const response = await fetch(`/course/plan/${unitPlannerStore.courseId}/newUnit`, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: getFormData(),
            });
            if (response.ok) {
                const newUnit = (await response.json()) as Unit;
                unitPlannerStore.units = [...unitPlannerStore.units, newUnit];
                toast.timeout({ text: "Unit created successfully" });
                cancel();
            } else {
                const responseText = (await response.json()) as { title: string; type: string; status: number };
                toast.timeout({ text: responseText.title, isSuccess: false });
            }
        } catch (error) {
            toast.timeout({ text: "There was an unexpected error", isSuccess: false });
        } finally {
            isProcessing.value = false;
        }
    }

    return {
        isProcessing,
        isOpen,
        newUnits,
        validationErrors,
        prompt,
        add,
        remove,
        validate,
        create,
        cancel,
    };
});
