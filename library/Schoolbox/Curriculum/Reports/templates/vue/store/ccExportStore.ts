/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import { useQueryStore } from "./ccQueryStore";
import { useFiltersStore } from "./ccFiltersStore";

export const useExportStore = defineStore("ccExport", () => {
    const endpoint = window.Vue.ref(""); // the endpoint to use for the report

    /**
     * store any passed through properties
     * @param $endpoint - the report api url
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

    /**
     *
     */
    async function exportReport(): Promise<void> {
        const newQueryParams = useQueryStore().getQueryParams();
        await fetch(`${endpoint.value}?${newQueryParams.toString()}`, {
            method: "GET",
            // signal: controller.signal,
            // headers: {
            //     Accept: "application/json",
            // },
        });
    }

    return {
        init,
        getEndpoint,
        exportReport,
    };
});
