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
    const isExpanded = window.Vue.ref<boolean>(false); // to show/hide all the sub types

    /**
     * Are there any types selected
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeTypes.value.length > 0);

    /**
     * Add a parent type to the active types
     * @param {FilterType} type - the parent type
     */
    function addType(type: FilterType) {
        if (!activeTypes.value.includes(type.value)) {
            activeTypes.value.push(type.value);
        }
    }

    /**
     * Remove a parent type from the active types
     * @param {FilterType} type - the parent type
     */
    function removeType(type: FilterType) {
        if (activeTypes.value.includes(type.value)) {
            activeTypes.value = activeTypes.value.filter((activeType) => activeType !== type.value);
        }
    }

    /**
     * When a parent type is selected, add all the sub types as well
     * @param {FilterType} type - the parent type
     */
    function addSubTypes(type: FilterType) {
        type.subTypes?.forEach((subType) => {
            if (!activeTypes.value.includes(subType.value)) {
                activeTypes.value.push(subType.value);
            }
        });
    }

    /**
     * When a parent type is unselected, remove all the sub types as well
     * @param {FilterType} type - the parent type
     */
    function removeSubTypes(type: FilterType) {
        type.subTypes?.forEach((subType) => {
            activeTypes.value = activeTypes.value.filter((activeType) => activeType !== subType.value);
        });
    }

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
        isExpanded,
        addType,
        removeType,
        addSubTypes,
        removeSubTypes,
        isActive,
        getFormData,
        clear,
    };
});
