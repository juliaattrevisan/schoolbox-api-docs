/**
 * Store the date range filter
 */

import { defineStore } from "pinia";

export const useFilterDateRangeStore = defineStore("spFilterDateRange", () => {
    const startDate = window.Vue.ref(null); //
    const endDate = window.Vue.ref(null); //
    const activeStartDate = window.Vue.ref(null); //
    const activeEndDate = window.Vue.ref(null); //

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
     * @returns {string} - the date range filter data
     */
    function getFormData() {
        const from = activeStartDate.value !== null ? activeStartDate.value : "";
        const to = activeEndDate.value !== null ? activeEndDate.value : "";
        return {
            date: {
                from,
                to,
            },
        };
    }

    /**
     * clear the date filter
     */
    function clear() {
        startDate.value = null;
        endDate.value = null;
        activeStartDate.value = null;
        activeEndDate.value = null;
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
