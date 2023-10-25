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
     * get the initial creators from the url if they exist
     * @returns {{id: number|null, name: string}[]} - the initial creator ID or null
     */
    function getInitialCreators(): { id: number | null; name: string }[] {
        const searchParams = new URLSearchParams(document.location.search);
        const filtersJson = searchParams.get("filter") as string;

        let filters;

        try {
            filters = JSON.parse(filtersJson) as FilterData; // This can return null on a successful parse.
        } catch (e) {
            filters = null;
        }

        if (filters === null) {
            return [];
        }

        // Note the filters JSON data structure only supports one creator currently.
        // The client is partially prepared to accommodate multiple creators.
        return filters.creator.id ? [{ id: filters.creator.id, name: "TODO: Get Username here somehow" }] : [];
    }

    const creator = window.Vue.ref<Creator[]>(getInitialCreators());

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
