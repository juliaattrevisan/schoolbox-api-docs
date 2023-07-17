/**
 * Store the filter types
 */

import { defineStore } from "pinia";

export const useFilterTypesStore = defineStore("spFilterTypes", () => {
    const allTypes = window.Vue.ref([]); // all filters passed in
    const activeTypes = window.Vue.ref([]); // the currently active filters

    /**
     * Are there any types active
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeTypes.value.length > 0);

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
        clear,
    };
});
