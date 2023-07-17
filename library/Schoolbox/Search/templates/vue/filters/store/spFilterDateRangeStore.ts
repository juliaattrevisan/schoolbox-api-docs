/**
 * Store the date range filter
 */

import { defineStore } from "pinia";
import { ref, computed } from "vue";

export const useFilterDateRangeStore = defineStore("spFilterDateRange", () => {
    const startDate = ref(null); //
    const endDate = ref(null); //

    /**
     * Is the start date filter set
     * @returns {boolean}
     */
    const hasStartDate = computed(() => startDate.value !== null);

    /**
     * Is the end date filter set
     * @returns {boolean}
     */
    const hasEndDate = computed(() => endDate.value !== null);

    /**
     * Are both the start and end date filters set
     * @returns {boolean}
     */
    const hasDateRange = computed(() => hasStartDate.value && hasEndDate.value);

    /**
     * clear the date filter
     */
    function clear() {
        startDate.value = null;
        endDate.value = null;
    }

    return {
        startDate,
        endDate,
        hasEndDate,
        hasStartDate,
        hasDateRange,
        clear,
    };
});
