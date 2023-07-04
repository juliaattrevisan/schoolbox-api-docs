/**
 * Store the current search results when searching from sp-input
 */

import { defineStore } from "pinia";
import { ref, computed } from "vue";

export const useFiltersStore = defineStore("spFilters", () => {
    const activeFilters = ref([]); // the currently active filters

    /**
     * Are there any filters active
     * @returns {boolean}
     */
    const hasActiveFilters = computed(() => activeFilters.value.length > 0);

    /**
     * clear the current filters
     */
    function clear() {
        activeFilters.value = [];
    }

    return {
        activeFilters,
        hasActiveFilters,
        clear,
    };
});
