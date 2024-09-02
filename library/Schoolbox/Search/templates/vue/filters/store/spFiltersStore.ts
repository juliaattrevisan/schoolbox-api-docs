/**
 * Store all the filters.
 * Selected filters relate to those currently selected
 * Active filters are those selected at the time of the latest search
 */

import { defineStore } from "pinia";
import { useFilterDateRangeStore } from "./spFilterDateRangeStore";
import { useFilterCreatorsStore } from "./spFilterCreatorsStore";
import { useFilterTypesStore } from "./spFilterTypesStore";
import type { FiltersFormData } from "../../../js/types/FiltersFormData";

export const useFiltersStore = defineStore("spFilters", () => {
    /**
     * Are there any filters active
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(
        () => useFilterDateRangeStore().isActive || useFilterCreatorsStore().isActive || useFilterTypesStore().isActive,
    );

    /**
     * Get the filter data
     * @returns {FiltersFormData} - the filter data
     */
    function getFormData() {
        const filterData = {
            ...useFilterDateRangeStore().getFormData(),
            ...useFilterCreatorsStore().getFormData(),
            ...useFilterTypesStore().getFormData(),
        };
        return JSON.stringify(filterData);
    }

    /**
     * search has been triggered so set the active filters
     */
    function setActive() {
        useFilterDateRangeStore().setActive();
        useFilterCreatorsStore().setActive();
        useFilterTypesStore().setActive();
    }

    /**
     * Whether a search can be performed, can be if there's a change of query or filters
     * @returns {boolean} - whether there's been a change in the date range
     */
    const canSearch = window.Vue.computed(() => {
        return (
            useFilterDateRangeStore().canSearch || useFilterCreatorsStore().canSearch || useFilterTypesStore().canSearch
        );
    });

    /**
     * clear all filters
     */
    function clear() {
        useFilterDateRangeStore().clear();
        useFilterCreatorsStore().clear();
        useFilterTypesStore().clear();
    }

    return {
        isActive,
        getFormData,
        setActive,
        canSearch,
        clear,
    };
});
