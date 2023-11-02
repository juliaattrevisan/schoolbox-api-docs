/**
 * Store the creator filter, results linked to a specific user
 * Note that currently only one creator can be selected at a time,
 * as this is what the API supports for now
 */

import { defineStore } from "pinia";

export type Creator = {
    id: number;
    name: string;
};

export const useFilterCreatorsStore = defineStore("spFiltersCreator", () => {
    const creator = window.Vue.ref<Creator[]>([]);

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
