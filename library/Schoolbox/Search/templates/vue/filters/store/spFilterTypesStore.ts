/**
 * Store the filter types
 */

import { defineStore } from "pinia";

export type FilterType = {
    title: string;
    value: string;
    subTypes?: FilterType[];
};

export const useFilterTypesStore = defineStore("spFilterTypes", () => {
    const allTypes = window.Vue.ref<FilterType[]>([]); //
    const selectedTypes = window.Vue.ref<string[]>([]); // the currently selected filters, just the value list
    const activeTypes = window.Vue.ref<string[]>([]); // the currently active filters, those selected at the time of the latest search

    /**
     * Are there any types selected
     * @returns {boolean}
     */
    const isSelected = window.Vue.computed(() => selectedTypes.value.length > 0);

    /**
     * Are there any types active
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeTypes.value.length > 0);

    /**
     * set the active types, linked to the currently active search,
     * so any filter changes are not applied to more result loads
     */
    function setActive() {
        activeTypes.value = selectedTypes.value;
    }

    /**
     * Get the types filter data
     * @returns {string} - the filter types
     */
    function getFormData() {
        return {
            types: selectedTypes.value,
        };
    }

    /**
     * clear the current types
     */
    function clear() {
        selectedTypes.value = [];
    }

    return {
        allTypes,
        selectedTypes,
        isSelected,
        isActive,
        setActive,
        getFormData,
        clear,
    };
});
