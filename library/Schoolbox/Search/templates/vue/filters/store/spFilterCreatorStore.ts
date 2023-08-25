/**
 * Store the creator filter, results linked to a specific user
 */

import { defineStore } from "pinia";

export type Creator = {
    id: number;
    name: string;
};

const emptyCreator: Creator = { id: 0, name: "" };

export const useFilterCreatorStore = defineStore("spFilterCreator", () => {
    const creator = window.Vue.ref<Creator>(emptyCreator);
    const activeCreator = window.Vue.ref<Creator>(emptyCreator);

    /**
     * Is the creator filter set
     * @returns {boolean}
     */
    const isSelected = window.Vue.computed(() => creator.value.id !== 0);

    /**
     * Is the active creator filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeCreator.value.id !== 0);

    /**
     * set the active creator filter, linked to the currently active search,
     * so any filter changes are not applied to more result loads
     */
    function setActive() {
        activeCreator.value = creator.value;
    }

    /**
     * Get the creator filter data
     * @returns {string} - the creator filter data
     */
    function getFormData() {
        // only add creator filter if it is set
        return {
            creator: {
                ...(isSelected.value && { id: activeCreator.value.id }),
            },
        };
    }

    /**
     * clear the creator filter
     */
    function clear() {
        creator.value = emptyCreator;
        activeCreator.value = emptyCreator;
    }

    return {
        creator,
        isSelected,
        isActive,
        setActive,
        getFormData,
        clear,
    };
});
