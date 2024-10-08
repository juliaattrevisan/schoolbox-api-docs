/**
 * Handles any core properties for the search page
 */

import { defineStore } from "pinia";
import { useQueryStore } from "./spQueryStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";
import type { FilterType } from "../filters/store/spFilterTypesStore";
import { useFilterTypesStore } from "../filters/store/spFilterTypesStore";
import type { FilterDateRange } from "../filters/store/spFilterDateRangeStore";
import { EMPTY_DATE, useFilterDateRangeStore } from "../filters/store/spFilterDateRangeStore";
import type { Creator } from "../filters/store/spFilterCreatorsStore";
import { useFilterCreatorsStore } from "../filters/store/spFilterCreatorsStore";

const SINGLE_COLUMN_BREAKPOINT = 800;

export const useStore = defineStore("searchPage", () => {
    let endpoint = ""; // the endpoint to use for searching

    const isSingleColumn = window.Vue.ref(true); // whether to use a single column layout
    const filterCreatorsStore = useFilterCreatorsStore();
    const filterDateRangeStore = useFilterDateRangeStore();
    const filterTypesStore = useFilterTypesStore();

    /**
     * store any passed through properties
     * @param $endpoint - the search api url
     * @param $selectedFilterDateRange - the selected date range filter
     * @param $creator - the selected creator
     * @param $filterTypesAvailable - the available filter types
     * @param $filterTypesSelected - the selected filter types
     */
    function init(
        $endpoint: string,
        $selectedFilterDateRange: FilterDateRange,
        $creator: Creator | null,
        $filterTypesAvailable: FilterType[],
        $filterTypesSelected: string[],
    ): void {
        endpoint = $endpoint;

        if ($creator !== null) {
            filterCreatorsStore.creator = [$creator];
        }

        // Empty dates are null at the server, and EMPTY_DATE at the client.  So we must convert.
        if ($selectedFilterDateRange.startDate === null) {
            $selectedFilterDateRange.startDate = EMPTY_DATE;
        }

        // Empty dates are null at the server, and EMPTY_DATE at the client.  So we must convert.
        if ($selectedFilterDateRange.endDate === null) {
            $selectedFilterDateRange.endDate = EMPTY_DATE;
        }

        filterDateRangeStore.startDate = $selectedFilterDateRange.startDate;
        filterDateRangeStore.endDate = $selectedFilterDateRange.endDate;
        filterTypesStore.allTypes = $filterTypesAvailable;
        filterTypesStore.selectedTypes = $filterTypesSelected;
    }

    /**
     * To collect all the params to send to the search api
     * @returns - the query params
     */
    function getQueryParams(): URLSearchParams {
        const newQueryParams = new URLSearchParams();
        newQueryParams.append("keyword", useQueryStore().activeQuery);
        newQueryParams.append("filter", useFiltersStore().getFormData());
        return newQueryParams;
    }

    /**
     * To update the url with the current query params
     * @param simulateRedirect - whether to simulate a redirect
     */
    function setQueryParams(simulateRedirect: boolean): void {
        if (simulateRedirect) {
            window.history.pushState({}, "", window.location.href);
        }

        const newQueryParams = getQueryParams();
        window.history.replaceState({}, "", `${window.location.pathname}?${newQueryParams.toString()}`);
    }

    /**
     * To collect all the params to send to the search api
     * @returns - the query params
     */
    function getEndpointWithParams(): string {
        const newQueryParams = getQueryParams();
        return `${endpoint}?${newQueryParams.toString()}`;
    }

    /**
     * To handle the container width changing
     * @param width - the width of the container
     */
    function containerWidthChange(width: number): void {
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
