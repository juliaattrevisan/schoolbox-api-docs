/**
 * Handles any core properties for the search page
 */

import { defineStore } from "pinia";
import { useQueryStore } from "./spQueryStore";
import { useFiltersStore } from "../filters/store/spFiltersStore";
import { useFilterTypesStore, FilterType } from "../filters/store/spFilterTypesStore";

export const useStore = defineStore("searchPage", () => {
    let endpoint = ""; // the endpoint to use for searching

    const isSingleColumn = window.Vue.ref(true); // whether to use a single column layout

    /**
     * store any passed through properties
     * @param {string} $endpoint - the search api url
     * @param {FilterType[]} $filterTypes - the filter types
     */
    function init($endpoint: string, $filterTypes: FilterType[]) {
        endpoint = $endpoint;
        useFilterTypesStore().allTypes = $filterTypes;
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
        isSingleColumn.value = width < 768;
        console.warn(width);
    }

    return {
        init,
        getQueryParams,
        getEndpointWithParams,
        containerWidthChange,
        isSingleColumn,
    };
});
