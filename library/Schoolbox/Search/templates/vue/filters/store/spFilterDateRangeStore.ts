/**
 * Store the date range filter
 */

import { defineStore } from "pinia";
import type { FiltersFormData } from "../../../js/types/FiltersFormData";

export type FilterDateRange = {
    startDate: string;
    endDate: string;
};

export const EMPTY_DATE = ""; // the value to use when the date is not set
export const INVALID_DATE = "Invalid Date"; // the string dayjs returns when a date is not valid

export const useFilterDateRangeStore = defineStore("spFilterDateRange", () => {
    const startDate = window.Vue.ref(EMPTY_DATE);
    const endDate = window.Vue.ref(EMPTY_DATE);
    const activeStartDate = window.Vue.ref(EMPTY_DATE);
    const activeEndDate = window.Vue.ref(EMPTY_DATE);

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
     * @returns - the date range filter data
     */
    function getFormData(): Pick<FiltersFormData, "date"> {
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
     * search has been triggered so set the active date range filter
     */
    function setActive(): void {
        activeStartDate.value = startDate.value;
        activeEndDate.value = endDate.value;
    }

    /**
     * Whether a search can be performed, can be if there's a change of query or filters
     * @returns {boolean} - whether there's been a change in the date range
     */
    const canSearch = window.Vue.computed(() => {
        return activeStartDate.value !== startDate.value || activeEndDate.value !== endDate.value;
    });

    /**
     * clear the date filter
     */
    function clear(): void {
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
        setActive,
        canSearch,
        clear,
    };
});
