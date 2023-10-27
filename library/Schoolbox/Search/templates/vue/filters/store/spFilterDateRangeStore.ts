/**
 * Store the date range filter
 */

import { defineStore } from "pinia";
import { FilterData } from "../../types/FilterData";

export type FilterDateRange = {
    startDate: string;
    endDate: string;
};

export const EMPTY_DATE = ""; // the value to use when the date is not set
export const INVALID_DATE = "Invalid Date"; // the string dayjs returns when a date is not valid

export const useFilterDateRangeStore = defineStore("spFilterDateRange", () => {
    const startDate = window.Vue.ref(EMPTY_DATE);
    const endDate = window.Vue.ref(EMPTY_DATE);

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
        // Only add from and to if they are set.
        return {
            date: {
                ...(hasStartDate.value && {
                    from: window.dayjs(startDate.value).format("YYYY-MM-DD"),
                }),
                ...(hasEndDate.value && {
                    to: window.dayjs(endDate.value).format("YYYY-MM-DD"),
                }),
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
