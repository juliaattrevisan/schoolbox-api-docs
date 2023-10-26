/**
 * Handles any core properties for the search page
 */

import { defineStore } from "pinia";
import { useQueryStore } from "./spQueryStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";
import { useFilterTypesStore, FilterType } from "../filters/store/spFilterTypesStore";
import { useFilterDateRangeStore, FilterDateRange, EMPTY_DATE } from "../filters/store/spFilterDateRangeStore";

const SINGLE_COLUMN_BREAKPOINT = 800;

export const useStore = defineStore("searchPage", () => {
    let endpoint = ""; // the endpoint to use for searching

    const isSingleColumn = window.Vue.ref(true); // whether to use a single column layout
    const filterDateRangeStore = useFilterDateRangeStore();

    /**
     * store any passed through properties
     * @param {string} $endpoint - the search api url
     * @param {FilterType[]} $filterTypes - the filter types
     * @param {FilterDateRange} $defaultFilterDateRange - the default date range filter
     */
    function init($endpoint: string, $filterTypes: FilterType[], $defaultFilterDateRange: FilterDateRange) {
        endpoint = $endpoint;
        useFilterTypesStore().allTypes = $filterTypes;

        // Only set dates to default if neither date was specified in the URL.
        if (filterDateRangeStore.startDate === EMPTY_DATE && filterDateRangeStore.endDate === EMPTY_DATE) {
            filterDateRangeStore.startDate = $defaultFilterDateRange.startDate;
            filterDateRangeStore.endDate = $defaultFilterDateRange.endDate;
        }
    }

    /**
     * To collect all the params to send to the search api
     * @returns {string} - the query params
     */
    function getQueryParams() {
        const newQueryParams = new URLSearchParams();
        newQueryParams.append("keyword", useQueryStore().activeQuery);
        newQueryParams.append("filter", useFiltersStore().getFormData());
        return newQueryParams;
    }

    /**
     * To update the url with the current query params
     */
    function setQueryParams() {
        const newQueryParams = getQueryParams();
        window.history.replaceState({}, "", `${window.location.pathname}?${newQueryParams.toString()}`);
    }

    /**
     * To collect all the params to send to the search api
     * @returns {string} - the query params
     */
    function getEndpointWithParams() {
        const newQueryParams = getQueryParams();
        const endpointWithParams = `${endpoint}?${newQueryParams.toString()}`;
        return endpointWithParams;
    }

    /**
     * To handle the container width changing
     * @param {number} width - the width of the container
     */
    function containerWidthChange(width: number) {
        isSingleColumn.value = width < SINGLE_COLUMN_BREAKPOINT;
    }

    return {
        init,
        getQueryParams,
        setQueryParams,
        getEndpointWithParams,
        containerWidthChange,
        isSingleColumn,
    };
});
