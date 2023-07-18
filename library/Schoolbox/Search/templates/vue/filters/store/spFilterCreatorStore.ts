/**
 * Store the creator filter, results linked to a specific user
 */

import { defineStore } from "pinia";

export const useFilterCreatorStore = defineStore("spFilterCreator", () => {
    const creator = window.Vue.ref<string>("");
    const activeCreator = window.Vue.ref<string>("");

    /**
     * Is the creator filter set
     * @returns {boolean}
     */
    const isSelected = window.Vue.computed(() => creator.value !== "");

    /**
     * Is the active creator filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeCreator.value !== "");

    /**
     * set the active creator filter, linked to the currently active search,
     * so any filter changes are not applied to more result loads
     */
    function setActive() {
        activeCreator.value = creator.value;
    }

    /**
     * Get the creator filter data
     * @returns {string} - the creator filter data
     */
    function getFormData() {
        const id = isActive.value ? activeCreator.value : "";
        return {
            creator: id,
        };
    }

    /**
     * clear the creator filter
     */
    function clear() {
        creator.value = "";
        activeCreator.value = "";
    }

    return {
        creator,
        isSelected,
        isActive,
        setActive,
        getFormData,
        clear,
    };
});
