/**
 * Retrieve a set of pastoral records based on the provided endpoint.
 */

import type { Ref } from "vue";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { RecordsPayload } from "../../js/types/pastoralReportTypes";

/**
 * load the pastoral records from the given endpoint.
 * @param endpoint - the endpoint to load the records from
 * @returns isLoading, payload, isEmpty, loadError
 */
export function useLoadRecords(endpoint: Ref<string>): {
    isLoading: Ref<boolean>;
    payload: Ref<RecordsPayload | null>;
    isEmpty: Ref<boolean>;
    loadError: Ref<string | null>;
} {
    const isLoading = window.Vue.ref(false); // to notify when a new table is being loaded
    const payload = window.Vue.ref<RecordsPayload | null>(null); // to store the payload that matches the current query
    const isEmpty = window.Vue.computed(() => payload.value === null && !isLoading.value); // To show that no payload match the current report table
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the load
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * search for payload that match the current query
     */
    async function load(): Promise<void> {
        // abort the previous search if it's still running
        if (isLoading.value) {
            controller.abort();
        }
        try {
            isLoading.value = true;
            loadError.value = null;
            controller = new AbortController();
            const response = await fetch(endpoint.value, {
                method: "GET",
                signal: controller.signal,
                headers: {
                    "Content-Type": "application/json",
                },
            });
            if (!response.ok) {
                const errorMessage = await extractErrorMessage(response);
                throw new Error(errorMessage);
            }
            const newPayload = (await response.json()) as RecordsPayload;
            // check if the response does not have the expected structure
            if (newPayload === null || newPayload.data === undefined || !Array.isArray(newPayload.data.records)) {
                throw new Error("Unexpected response format");
            }
            payload.value = newPayload;
        } catch (error) {
            // ignore abort errors, as the previous request was cancelled
            if (isAbortError(error)) {
                return;
            }
            if (error instanceof Error && hasErrorMessage(error)) {
                loadError.value = error.message;
            }
            console.error(error);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * when the endpoint to trigger load changes provided the endpoint is not empty
     * and the new endpoint is different from the old one, load the data
     */
    window.Vue.watch(endpoint, (newEndpoint, oldEndpoint) => {
        if (endpoint.value !== "" && newEndpoint !== oldEndpoint) {
            void load();
        }
    });

    return {
        isLoading,
        payload,
        isEmpty,
        loadError,
    };
}
