/**
 * Add a new unit
 */

import { defineStore } from "pinia";
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
        const formData = new FormData();
        const store = useUnitPlannerStore();
        store.units.forEach((unit) => {
            formData.append(`unit[${unit.id}][name]`, unit.title);
            formData.append("spawn-unit", "1");
        });
        formData.append("name", name.value);
        return formData;
    }

    /**
     * Get the new unit
     * @param {HTMLElement} html - the html element that's used to extract the new unit details
     * @returns {Unit} the new Unit
     */
    function getNewUnit(html: HTMLElement) {
        const item = html.querySelector("[data-folder-id]") as HTMLElement;
        const folderId = item.getAttribute("data-folder-id") as string;
        const unit = {
            id: item.getAttribute("data-unit-spec"),
            title: name.value,
            homepage: `/homepage/${newFolderId}`,
            folderId,
            activities: {
                _routes: {
                    create:
                },
                items: [],
            },
            rubricCapabilities: [],
            textboxes: {
                "Text Box": {
                    body: "",
                    id: -1,
                }
            }
            extraTextboxes: 0,
            sequence: useUnitPlannerStore().units.length + 1,
        } as Unit;
        return unit;
    }

    /**
     * pass the new unit details to the backen
     */
    async function add() {
        try {
            isProcessing.value = true;
            const response = await fetch(`/course/${useUnitPlannerStore().courseId}/unitspecs/new`, {
                method: "POST",
                body: getFormData(),
            });
            // if (response.ok) {
            const responseHtml = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(responseHtml, "text/html");
            // const unit = getNewUnit(doc);

            console.warn(newFolderId);
            isProcessing.value = false;
            cancel();
            // }
            // throw new Error(responseJson.title, { ...responseJson });
        } catch (error) {
            // throw new Error(error.message, error.options);
        }
    }

    /**
     * to unset the id, called when the toast has already been displayed once
     */
    // function unsetId() {
    //     id.value = UNSET_ID;
    // }

    return {
        isProcessing,
        isOpen,
        // unit,
        // id,
        name,
        prompt,
        add,
        cancel,
        // unsetId,
    };
});
