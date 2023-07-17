/**
 * Store all the filters
 */

import { defineStore } from "pinia";
import { computed } from "vue";
import { useFilterDateRangeStore } from "./spFilterDateRangeStore";
import { useFilterCreatorStore } from "./spFilterCreatorStore";
import { useFilterTypesStore } from "./spFilterTypesStore";

export const useFiltersStore = defineStore("spFilters", () => {
    /**
     * Are there any filters active
     * @returns {boolean}
     */
    const isActive = computed(
        () => useFilterDateRangeStore().isActive || useFilterCreatorStore().isActive || useFilterTypesStore().isActive
    );

    /**
     * clear all filters
     */
    function clear() {
        useFilterDateRangeStore().clear();
        useFilterCreatorStore().clear();
        useFilterTypesStore().clear();
    }

    return {
        isActive,
        clear,
    };
});
