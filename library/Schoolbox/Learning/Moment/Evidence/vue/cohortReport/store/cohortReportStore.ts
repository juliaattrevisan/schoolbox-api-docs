import { defineStore } from "pinia";
import { getCohortReport, type CohortReportApiResponse } from "./cohortReportPayload";

export const useCohortReportStore = defineStore("cohortReport", () => {
    let endpoint: string | null = null; // the endpoint to fetch the cohort report data

    const results = window.Vue.ref<CohortReportApiResponse>({});

    const isLoading = window.Vue.ref(true);
    const loadError = window.Vue.ref<string | null>(null);

    /**
     * Load cohort report data
     */
    async function loadData(): Promise<void> {
        if (!endpoint) {
            throw new Error("Cohort report endpoint is not set");
        }

        isLoading.value = true;
        const response = await getCohortReport(endpoint);
        isLoading.value = false;
        if (response.success === false) {
            loadError.value = response.message;
            return;
        }

        // merge with whatever results we already have
        Object.assign(results.value, response.data);
    }

    /**
     * Initialise the store
     * @param $endpoint - getCohortReportPayload
     */
    async function init($endpoint: string): Promise<void> {
        endpoint = $endpoint;
        await loadData();
    }

    return {
        init,
        results,
        isLoading,
        loadError,
    };
});
