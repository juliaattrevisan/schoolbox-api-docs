/**
 * Store the creator filter, results linked to a specific user
 * Note that currently only one creator can be selected at a time,
 * as this is what the API supports for now
 */

import { defineStore } from "pinia";
import { FilterData } from "../../types/FilterData";

export type Creator = {
    id: number | null;
    name: string;
};

export const useFilterCreatorsStore = defineStore("spFiltersCreator", () => {
    /**
     * get the initial creator from the url if it exists
     * @returns {number|null} - the initial creator ID or null
     */
    function getInitialCreatorId(): number | null {
        const searchParams = new URLSearchParams(document.location.search);
        const filtersJson = searchParams.get("filter") as string;

        let filters;

        try {
            filters = JSON.parse(filtersJson) as FilterData; // This can return null on a successful parse.
        } catch (e) {
            filters = null;
        }

        if (filters === null) {
            return null;
        }

        return filters.creator.id ?? null;
    }

    const creator = window.Vue.ref<Creator[]>([{ id: getInitialCreatorId(), name: "" }]);

    /**
     * Is the creator filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => creator.value.length > 0);

    /**
     * Get the creator filter data
     * @returns {object} - the creator filter data
     */
    function getFormData() {
        // only add creator filter if it is set
        return {
            creator: {
                ...(isActive.value && { id: creator.value[0].id }),
            },
        };
    }

    /**
     * clear the creator filter
     */
    function clear() {
        creator.value = [];
    }

    return {
        creator,
        isActive,
        getFormData,
        clear,
    };
});
