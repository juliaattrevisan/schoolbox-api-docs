/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";
import { useInputStore } from "./ccInputStore";

export const useQueryStore = defineStore("ccQuery", () => {
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
        newQueryParams.append("courseId", useInputStore().coursesValue);
        newQueryParams.append("nodeId", useInputStore().nodesValue);
        return newQueryParams;
    }

    /**
     * To update the url with the current query params
     * @param simulateRedirect - whether to simulate a redirect
     */
    function setQueryParams(simulateRedirect: boolean = false): void {
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
     * Returns a list of query urls split by each course in an attempt to
     * reduce the demand on the server
     * @returns - the query urls
     */
    function getQueryUrls(): string[] {
        const inputStore = useInputStore();
        return inputStore.courseIds.map((courseId: number) => {
            const newQueryParams = new URLSearchParams();
            newQueryParams.append("courseId", courseId.toString());
            newQueryParams.append("nodeId", useInputStore().nodesValue);
            return `${endpoint}?${newQueryParams.toString()}`;
        });
    }

    return {
        init,
        setQueryParams,
        getEndpointWithParams,
        getQueryUrls,
    };
});
