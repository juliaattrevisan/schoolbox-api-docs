/**
 * Store the creator filter, results linked to a specific user
 */

import { defineStore } from "pinia";

export const useFilterCreatorStore = defineStore("spFilterCreator", () => {
    const creator = window.Vue.ref(null);

    /**
     * Is the creator filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => creator.value !== null);

    /**
     * clear the creator filter
     */
    function clear() {
        creator.value = null;
    }

    return {
        creator,
        isActive,
        clear,
    };
});
