/**
 * Add a new unit
 */

import { defineStore } from "pinia";
import { ToastEvent } from "@schoolboxVue/components/toast/sbxToastEvent";
import type { ToastActivateEventDetail, ToastTimeoutEventDetail } from "@schoolboxVue/components/toast/sbxToastEvent";
import { useUnitPlannerStore } from "./unitPlannerStore";
import type { Unit } from "../unitPlannerTypes";

export const useAddUnitStore = defineStore("unitPlannerAddUnit", () => {
    const isProcessing = window.Vue.ref(false);
    const isOpen = window.Vue.ref(false); // to notify that the prompt is opened / closed
    const name = window.Vue.ref(""); // the name of the new unit

    /**
     * to display a prompt to add a unit
     */
    function prompt() {
        isOpen.value = true;
    }

    /**
     * to hide the add prompt
     */
    function cancel() {
        isOpen.value = false;
        name.value = "";
    }

    /**
     * Get the data to pass to the backend
     * @returns {FormData} the form data
     */
    function getFormData() {
        return JSON.stringify({
            name: name.value,
        });
    }

    /**
     * pass the new unit details to the backend
     */
    async function add() {
        const unitPlannerStore = useUnitPlannerStore();
        try {
            document.dispatchEvent(
                new CustomEvent(ToastEvent.ACTIVATE, {
                    detail: { text: "Adding..." } as ToastActivateEventDetail,
                }),
            );
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
                isProcessing.value = false;
                cancel();
            } else {
                const responseText = (await response.json()) as { title: string; type: string; status: number };
                document.dispatchEvent(
                    new CustomEvent(ToastEvent.TIMEOUT, {
                        detail: { text: responseText.title, isSuccess: false } as ToastTimeoutEventDetail,
                    }),
                );
            }
        } catch (error) {
            document.dispatchEvent(
                new CustomEvent(ToastEvent.TIMEOUT, {
                    detail: { text: "There was an unexpected error", isSuccess: false } as ToastTimeoutEventDetail,
                }),
            );
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
