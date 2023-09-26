/**
 * Store the date range filter
 */

import { defineStore } from "pinia";

export const EMPTY_DATE = ""; // the value to use when the date is not set
export const INVALID_DATE = "Invalid Date"; // the string dayjs returns when a date is not valid

export const useFilterDateRangeStore = defineStore("spFilterDateRange", () => {
    const startDate = window.Vue.ref(
        // Set start time to '00:00:00' to ensure date range is inclusive.
        window.dayjs().startOf("day").subtract(1, "year").format()
    );
    const endDate = window.Vue.ref(
        // Set end time to '23:59:59' to ensure date range is inclusive.
        window.dayjs().endOf("day").format()
    );

    /**
     * Is the start date filter set
     * @returns {boolean}
     */
    const hasStartDate = window.Vue.computed(() => startDate.value !== EMPTY_DATE && startDate.value !== INVALID_DATE);

    /**
     * Is the end date filter set
     * @returns {boolean}
     */
    const hasEndDate = window.Vue.computed(() => endDate.value !== EMPTY_DATE && endDate.value !== INVALID_DATE);

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
     * Get the date range filter data
     * @returns {object} - the date range filter data
     */
    function getFormData() {
        // only add from and to if they are set
        return {
            date: {
                ...(hasStartDate.value && { from: startDate.value }),
                ...(hasEndDate.value && { to: endDate.value }),
            },
        };
    }

    /**
     * clear the date filter
     */
    function clear() {
        startDate.value = EMPTY_DATE;
        endDate.value = EMPTY_DATE;
    }

    return {
        startDate,
        endDate,
        hasEndDate,
        hasStartDate,
        hasDateRange,
        isActive,
        getFormData,
        clear,
    };
});
