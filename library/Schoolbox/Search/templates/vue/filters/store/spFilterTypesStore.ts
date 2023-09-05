/**
 * Store the filter types, which will vary depending on the Schoolbox plan
 */

import { defineStore } from "pinia";

export type FilterType = {
    title: string;
    value: string;
    subTypes?: FilterType[];
};

export const useFilterTypesStore = defineStore("spFilterTypes", () => {
    const allTypes = window.Vue.ref<FilterType[]>([]); // all filter types available
    const activeTypes = window.Vue.ref<string[]>([]); // the currently selected filters, just the value list

    /**
     * Are there any types selected
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeTypes.value.length > 0);

    /**
     * Get the types filter data
     * @returns {string} - the filter types
     */
    function getFormData() {
        return {
            types: activeTypes.value,
        };
    }

    /**
     * clear the current types
     */
    function clear() {
        activeTypes.value = [];
    }

    return {
        allTypes,
        activeTypes,
        isActive,
        getFormData,
        clear,
    };
});
