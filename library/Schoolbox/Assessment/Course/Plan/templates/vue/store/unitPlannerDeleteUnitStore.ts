/**
 * Delete a unit, also contains the prompt to open/close the delete unit display
 */

import { defineStore } from "pinia";
import { apiRequest } from "@js/util/sbx/apiUtils";
import locale from "@js/terminology/rosettaStone";
import { useUnitPlannerStore } from "./unitPlannerStore";
import type { Unit } from "../unitPlannerTypes";

export const useDeleteUnitsStore = defineStore("unitPlannerDeleteUnit", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create
    const unitToDelete = window.Vue.ref<Unit | null>(null);

    /**
     * to display a prompt to delete a unit
     * @param deleteRoute the route to use to delete the unit
     */
    function prompt($unitToDelete: Unit): void {
        unitToDelete.value = $unitToDelete;
        isOpen.value = true;
    }

    /**
     * to hide the delete prompt
     */
    function cancel(): void {
        isOpen.value = false;
    }

    /**
     * Delete the unit
     */
    async function deleteUnit(): Promise<void> {
        if (unitToDelete.value === null || unitToDelete.value._routes.delete === null) {
            loadError.value = "No delete route specified";
            return;
        }
        const unitPlannerStore = useUnitPlannerStore();
        const toast = window.sbProps.toastActivate("Deleting...");
        try {
            isProcessing.value = true;
            loadError.value = null;
            const response = await apiRequest<{ id: number }>({
                url: unitToDelete.value._routes.delete,
                method: "POST",
            });
            if (!response.success) {
                throw new Error(response.message || `Failed to delete ${locale.unit.translate(false, true)}`);
            }
            unitPlannerStore.units = unitPlannerStore.units.filter((unit) => unit.id !== unitToDelete.value?.id);
            toast.timeout({ text: `${locale.unit.translate(false, true)} successfully deleted` });
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
        loadError,
        prompt,
        cancel,
        deleteUnit,
    };
});
