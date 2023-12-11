/**
 * Store the filter types, which will vary depending on the Schoolbox plan
 */

import { defineStore } from "pinia";
import type { FiltersFormData } from "../../../js/types/FiltersFormData";

export type FilterType = {
    title: string;
    value: string;
    subTypes?: FilterType[];
};

export const useFilterTypesStore = defineStore("spFilterTypes", () => {
    const allTypes = window.Vue.ref<FilterType[]>([]); // all filter types available
    const selectedTypes = window.Vue.ref<string[]>([]); // the currently selected filters, just the value list
    const activeTypes = window.Vue.ref<string[]>([]); // the currently active filters, those selected at the time of the latest search
    const isExpanded = window.Vue.ref<boolean>(false); // to show/hide all the sub types

    /**
     * Are there any types selected
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => selectedTypes.value.length > 0);

    /**
     * Add a parent type to the active types
     * @param {FilterType} type - the parent type
     */
    function addType(type: FilterType) {
        if (!selectedTypes.value.includes(type.value)) {
            selectedTypes.value.push(type.value);
        }
    }

    /**
     * Remove a parent type from the active types
     * @param {FilterType} type - the parent type
     */
    function removeType(type: FilterType) {
        if (selectedTypes.value.includes(type.value)) {
            selectedTypes.value = selectedTypes.value.filter((activeType) => activeType !== type.value);
        }
    }

    /**
     * When a parent type is selected, add all the sub types as well
     * @param {FilterType} type - the parent type
     */
    function addSubTypes(type: FilterType) {
        type.subTypes?.forEach((subType) => {
            if (!selectedTypes.value.includes(subType.value)) {
                selectedTypes.value.push(subType.value);
            }
        });
    }

    /**
     * When a parent type is unselected, remove all the sub types as well
     * @param {FilterType} type - the parent type
     */
    function removeSubTypes(type: FilterType) {
        type.subTypes?.forEach((subType) => {
            selectedTypes.value = selectedTypes.value.filter((activeType) => activeType !== subType.value);
        });
    }

    /**
     * Get the types filter data
     * @returns {FiltersFormData["types"]} - the filter types
     */
    function getFormData() {
        return {
            types: selectedTypes.value,
        };
    }

    /**
     * search has been triggered so set the active types filter
     */
    function setActive() {
        activeTypes.value = selectedTypes.value;
    }

    /**
     * Whether a search can be performed, can be if there's a change of query or filters
     * @returns {boolean} - whether the two arrays are different
     */
    const canSearch = window.Vue.computed(() => {
        return selectedTypes.value.join("") !== activeTypes.value.join("");
    });

    /**
     * clear the current types
     */
    function clear() {
        selectedTypes.value = [];
    }

    return {
        allTypes,
        selectedTypes,
        activeTypes,
        isExpanded,
        addType,
        removeType,
        addSubTypes,
        removeSubTypes,
        isActive,
        getFormData,
        setActive,
        canSearch,
        clear,
    };
});
