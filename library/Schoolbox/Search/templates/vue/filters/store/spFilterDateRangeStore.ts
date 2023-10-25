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
    /**
     * get the initial date range from the url if it exists
     * @returns {{from: string, to: string}} - the initial date range
     */
    function getInitialDateRange(): { from: string; to: string } {
        const searchParams = new URLSearchParams(document.location.search);
        const filtersJson = searchParams.get("filter") as string;

        let filters;

        try {
            filters = JSON.parse(filtersJson) as FilterData;
        } catch (e) {
            return { from: EMPTY_DATE, to: EMPTY_DATE };
        }

        // If either date not valid, replace with blank
        let fromDate = filters.date.from;
        let toDate = filters.date.to;

        // Note this only checks if date can be parsed by dayjs.
        if (!window.dayjs(fromDate).isValid()) {
            fromDate = EMPTY_DATE;
        }

        // Note this only checks if date can be parsed by dayjs.
        if (!window.dayjs(toDate).isValid()) {
            toDate = EMPTY_DATE;
        }

        return { from: fromDate, to: toDate };
    }

    const initialDateRange = getInitialDateRange();
    const startDate = window.Vue.ref(initialDateRange.from);
    const endDate = window.Vue.ref(initialDateRange.to);

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
                    // Add '00:00:00' time here to ensure date range is inclusive of the start date.
                    from: window.dayjs(startDate.value).startOf("day").format(),
                }),
                ...(hasEndDate.value && {
                    // Add '23:59:59' time here to ensure date range is inclusive of the end date.
                    to: window.dayjs(endDate.value).endOf("day").format(),
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
