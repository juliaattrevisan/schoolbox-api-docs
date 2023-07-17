/**
 * Store the date range filter
 */

import { defineStore } from "pinia";

export const useFilterDateRangeStore = defineStore("spFilterDateRange", () => {
    const startDate = window.Vue.ref(null); //
    const endDate = window.Vue.ref(null); //

    /**
     * Is the start date filter set
     * @returns {boolean}
     */
    const hasStartDate = window.Vue.computed(() => startDate.value !== null);

    /**
     * Is the end date filter set
     * @returns {boolean}
     */
    const hasEndDate = window.Vue.computed(() => endDate.value !== null);

    /**
     * Are both the start and end date filters set
     * @returns {boolean}
     */
    const hasDateRange = window.Vue.computed(() => hasStartDate.value && hasEndDate.value);

    /**
     * Is either the start or end date filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => hasStartDate.value || hasEndDate.value);

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
        isActive,
        clear,
    };
});
