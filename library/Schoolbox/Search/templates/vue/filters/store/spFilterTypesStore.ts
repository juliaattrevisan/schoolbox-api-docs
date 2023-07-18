/**
 * Store the filter types
 */

import { defineStore } from "pinia";

export const useFilterTypesStore = defineStore("spFilterTypes", () => {
    const allTypes = window.Vue.ref([]); // all filters passed in
    const selectedTypes = window.Vue.ref([]); // the currently selected filters
    const activeTypes = window.Vue.ref([]); // the currently selected filters

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
        clear,
    };
});
