/**
 * Store the current search results when searching from sp-input
 */

import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useResultsStore } from "../../results/store/spResultsStore";

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

    /**
     * clear the current filters
     */
    async function clearAndSearch() {
        clear();
        await useResultsStore().search();
    }

    return {
        activeFilters,
        hasActiveFilters,
        clear,
        clearAndSearch,
    };
});
