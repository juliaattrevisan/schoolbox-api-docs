/**
 * Retrieve a set of table payload data based on the provided endpoint.
 */

import type { Ref } from "vue";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import type { TablePayload } from "../../js/types/pastoralReportTypes";

/**
 * search for payload that match the current query
 * @param endpoint - the endpoint to load the table data
 * @returns isLoading, payload, isEmpty, loadError
 */
export function useLoadPrTable(endpoint: Ref<string>): {
    isLoading: Ref<boolean>;
    payload: Ref<TablePayload | null>;
    isEmpty: Ref<boolean>;
    loadError: Ref<string | null>;
} {
    const isLoading = window.Vue.ref(false); // to notify that an action is taking place,
    const payload = window.Vue.ref<TablePayload | null>(null); // to store the payload that matches the current query
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
            const responseJson = (await response.json()) as TablePayload;
            // check if the response does not have the expected structure
            if (
                responseJson === null ||
                responseJson.data === undefined ||
                !Array.isArray(responseJson.data.tableHeaders)
            ) {
                throw new Error("Unexpected response format");
            }
            payload.value = responseJson;
            console.warn(responseJson);
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
     * when the endpoint changes trigger a new load
     */
    window.Vue.watch(endpoint, () => {
        if (endpoint.value !== "") {
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
