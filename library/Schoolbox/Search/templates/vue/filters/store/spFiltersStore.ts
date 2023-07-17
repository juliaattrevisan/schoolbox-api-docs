/**
 * Store all the filters
 */

import { defineStore } from "pinia";
import { useFilterDateRangeStore } from "./spFilterDateRangeStore";
import { useFilterCreatorStore } from "./spFilterCreatorStore";
import { useFilterTypesStore } from "./spFilterTypesStore";

export const useFilters = defineStore("spFilters", () => {
    /**
     * clear all filters
     */
    function clear() {
        useFilterDateRangeStore().clear();
        useFilterCreatorStore().clear();
        useFilterTypesStore().clear();
    }

    return {
        clear,
    };
});
