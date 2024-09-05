/**
 * Add a new unit, also contains the prompt to open/close the new unit display
 */

import { defineStore } from "pinia";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import { useUnitPlannerStore } from "./unitPlannerStore";
import type { Unit } from "../unitPlannerTypes";

export const useAddUnitStore = defineStore("unitPlannerAddUnit", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const name = window.Vue.ref(""); // the name of the new unit

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
        name.value = "";
    }

    /**
     * Get the data to pass to the backend
     * @returns the form data
     */
    function getFormData(): string {
        return JSON.stringify({
            name: name.value,
        });
    }

    /**
     * pass the new unit details to the backend
     */
    async function add(): Promise<void> {
        const unitPlannerStore = useUnitPlannerStore();
        try {
            activate("Adding...");
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
                timeout({ text: "Unit created successfully" });
                cancel();
            } else {
                const responseText = (await response.json()) as { title: string; type: string; status: number };
                timeout({ text: responseText.title, isSuccess: false });
            }
        } catch (error) {
            timeout({ text: "There was an unexpected error", isSuccess: false });
        } finally {
            isProcessing.value = false;
        }
    }

    return {
        isProcessing,
        isOpen,
        name,
        prompt,
        add,
        cancel,
    };
});
