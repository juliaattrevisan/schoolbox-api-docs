/**
 * Store the creator filter, results linked to a specific user
 */

import { defineStore } from "pinia";
import { ref, computed } from "vue";

export const useFilterCreatorStore = defineStore("spFilterCreator", () => {
    const creator = ref(null);

    /**
     * Is the creator filter set
     * @returns {boolean}
     */
    const isActive = computed(() => creator.value !== null);

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
