/**
 * To manage the state of the curriculum coverage report, handling the load of
 */

import { defineStore } from "pinia";

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
     *
     */
    async function exportReport(): Promise<void> {
        await fetch(endpoint.value, {
            method: "GET",
            // signal: controller.signal,
            // headers: {
            //     Accept: "application/json",
            // },
        });
    }

    return {
        init,
        endpoint,
        exportReport,
    };
});
