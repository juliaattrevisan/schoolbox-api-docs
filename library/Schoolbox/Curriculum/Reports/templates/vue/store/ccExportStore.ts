/**
 * To be able to export the report to a csv file
 */

import { defineStore } from "pinia";
import { useQueryStore } from "./ccQueryStore";
import { useFiltersStore } from "./ccFiltersStore";

export const useExportStore = defineStore("ccExport", () => {
    const endpoint = window.Vue.ref(""); // the endpoint to use for the export

    /**
     * store any passed through properties
     * @param $endpoint - the export url
     */
    function init($endpoint: string): void {
        endpoint.value = $endpoint;
    }

    /**
     * Get the endpoint with the current query params
     * @returns - the endpoint with the current query params
     */
    function getEndpoint(): string {
        const newQueryParams = useQueryStore().getQueryParams(); // get the courseId and nodeId
        // get the selected report options
        useFiltersStore().queryParams.forEach((param) => {
            newQueryParams.append(param.name, param.value);
        });
        return `${endpoint.value}?${newQueryParams.toString()}`;
    }

    return {
        init,
        getEndpoint,
    };
});
