/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import { useInputStore } from "./ccInputStore";

export const useStore = defineStore("curriculumCoverage", () => {
    let endpoint = ""; // the endpoint to use for the report

    /**
     * store any passed through properties
     * @param $endpoint - the report api url
     */
    function init($endpoint: string): void {
        endpoint = $endpoint;
    }

    /**
     * To collect all the params to send to the search api
     * @returns - the query params
     */
    function getQueryParams(): URLSearchParams {
        const newQueryParams = new URLSearchParams();
        newQueryParams.append("courseId", useInputStore().selectedCoursesStore.selectedIds.join(","));
        newQueryParams.append("nodeId", (useInputStore().selectedNodesStore.nodesIds as number[]).join(","));
        return newQueryParams;
    }

    /**
     * To collect all the params to send to the search api
     * @returns - the query params
     */
    function getEndpointWithParams(): string {
        const newQueryParams = getQueryParams();
        return `${endpoint}?${newQueryParams.toString()}`;
    }

    return {
        init,
        getEndpointWithParams,
    };
});
