/**
 * Store the date range filter
 */

import { defineStore } from "pinia";

export const EMPTY_DATE = ""; // the value to use when the date is not set
export const INVALID_DATE = "Invalid Date"; // the string dayjs returns when a date is not valid

export const useFilterDateRangeStore = defineStore("spFilterDateRange", () => {
    const startDate = window.Vue.ref(window.dayjs().subtract(1, "year").format());
    const endDate = window.Vue.ref(window.dayjs().format());
    const activeStartDate = window.Vue.ref(EMPTY_DATE); // the start date currently applied to the active search
    const activeEndDate = window.Vue.ref(EMPTY_DATE); // the end date currently applied to the active search

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
    const isSelected = window.Vue.computed(() => hasStartDate.value || hasEndDate.value);

    /**
     * Is either the active start or active end date filter set
     * @returns {boolean}
     */
    const isActive = window.Vue.computed(() => activeStartDate.value || activeEndDate.value);

    /**
     * set the active date ranges, linked to the currently active search,
     * so any filter changes are not applied to more result loads
     */
    function setActive() {
        activeStartDate.value = startDate.value;
        activeEndDate.value = endDate.value;
    }

    /**
     * Get the date range filter data
     * @returns {object} - the date range filter data
     */
    function getFormData() {
        // only add from and to if they are set
        return {
            date: {
                ...(hasStartDate.value && { from: activeStartDate.value }),
                ...(hasEndDate.value && { to: activeEndDate.value }),
            },
        };
    }

    /**
     * clear the date filter
     */
    function clear() {
        startDate.value = EMPTY_DATE;
        endDate.value = EMPTY_DATE;
        activeStartDate.value = EMPTY_DATE;
        activeEndDate.value = EMPTY_DATE;
    }

    return {
        startDate,
        endDate,
        hasEndDate,
        hasStartDate,
        hasDateRange,
        isSelected,
        isActive,
        setActive,
        getFormData,
        clear,
    };
});
