/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";

export const useExportStore = defineStore("ccExport", () => {
    let endpoint = ""; // the endpoint to use for the report

    /**
     * store any passed through properties
     * @param $endpoint - the report api url
     */
    function init($endpoint: string): void {
        endpoint = $endpoint;
    }

    return {
        init,
    };
});
