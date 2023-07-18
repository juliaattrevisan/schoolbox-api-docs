/**
 * Store the creator filter, results linked to a specific user
 */

import { defineStore } from "pinia";

export const useFilterCreatorStore = defineStore("spFilterCreator", () => {
    const creator = window.Vue.ref(null);
    const activeCreator = window.Vue.ref(null);

    /**
     * Is the creator filter set
     * @returns {boolean}
     */
    const isSelected = window.Vue.computed(() => creator.value !== null);

    /**
     * Is the active creator filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeCreator.value !== null);

    /**
     * set the active creator filter, linked to the currently active search,
     * so any filter changes are not applied to more result loads
     */
    function setActive() {
        activeCreator.value = creator.value;
    }

    /**
     * clear the creator filter
     */
    function clear() {
        creator.value = null;
        activeCreator.value = null;
    }

    return {
        creator,
        isSelected,
        isActive,
        setActive,
        clear,
    };
});
