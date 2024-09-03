/**
 * Store the creator filter, results linked to a specific user
 * Note that currently only one creator can be selected at a time,
 * as this is what the API supports for now
 */

import { defineStore } from "pinia";
import type { FiltersFormData } from "../../../js/types/FiltersFormData";

export type Creator = {
    id: number;
    name: string;
};

export const useFilterCreatorsStore = defineStore("spFiltersCreator", () => {
    const creator = window.Vue.ref<Creator[]>([]);
    const activeCreator = window.Vue.ref<Creator[]>([]);

    /**
     * Is the creator filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => creator.value.length > 0);

    /**
     * Get the creator filter data
     * @returns - the creator filter data
     */
    function getFormData(): Pick<FiltersFormData, "creator"> {
        // only add creator filter if it is set
        return {
            creator: {
                ...(isActive.value && { id: creator.value[0].id }),
            },
        };
    }

    /**
     * search has been triggered so set the active creator filter
     */
    function setActive(): void {
        activeCreator.value = [...creator.value];
    }

    /**
     * Whether a search can be performed, can be if there's a change of query or filters
     * @returns {boolean} - whether the current ids match
     */
    const canSearch = window.Vue.computed(() => {
        return (
            activeCreator.value.length !== creator.value.length || activeCreator.value[0]?.id !== creator.value[0]?.id
        );
    });

    /**
     * clear the creator filter
     */
    function clear(): void {
        creator.value = [];
    }

    return {
        creator,
        isActive,
        getFormData,
        setActive,
        canSearch,
        clear,
    };
});
