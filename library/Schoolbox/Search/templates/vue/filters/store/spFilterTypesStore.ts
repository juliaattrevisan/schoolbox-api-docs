/**
 * Store the filter types
 */

import { defineStore } from "pinia";
import { ref, computed } from "vue";

export const useFilterTypesStore = defineStore("spFilterTypes", () => {
    const allTypes = ref([]); // all filters passed in
    const activeTypes = ref([]); // the currently active filters

    /**
     * Are there any types active
     * @returns {boolean}
     */
    const hasActiveTypes = computed(() => activeTypes.value.length > 0);

    /**
     * clear the current types
     */
    function clear() {
        activeTypes.value = [];
    }

    return {
        allTypes,
        activeTypes,
        hasActiveTypes,
        clear,
    };
});
