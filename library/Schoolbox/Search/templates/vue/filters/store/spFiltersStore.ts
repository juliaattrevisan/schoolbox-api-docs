/**
 * Store all the filters.
 * Selected filters relate to those currently selected
 * Active filters are those selected at the time of the latest search
 */

import { defineStore } from "pinia";
import { useFilterDateRangeStore } from "./spFilterDateRangeStore";
import { useFilterCreatorStore } from "./spFilterCreatorStore";
import { useFilterTypesStore } from "./spFilterTypesStore";

export const useFiltersStore = defineStore("spFilters", () => {
    /**
     * Are there any filters selected
     * @returns {boolean}
     */
    const isSelected = window.Vue.computed(
        () =>
            useFilterDateRangeStore().isSelected ||
            useFilterCreatorStore().isSelected ||
            useFilterTypesStore().isSelected
    );

    /**
     * Are there any filters active
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(
        () => useFilterDateRangeStore().isActive || useFilterCreatorStore().isActive || useFilterTypesStore().isActive
    );

    /**
     * set the active filters, linked to the currently active search
     */
    function setActive() {
        useFilterDateRangeStore().setActive();
        useFilterCreatorStore().setActive();
        useFilterTypesStore().setActive();
    }

    /**
     * clear all filters
     */
    function clear() {
        useFilterDateRangeStore().clear();
        useFilterCreatorStore().clear();
        useFilterTypesStore().clear();
    }

    return {
        isSelected,
        isActive,
        setActive,
        clear,
    };
});
