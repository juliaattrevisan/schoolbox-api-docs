/**
 * Retrieve a set of pastoral records based on the provided url.
 */

import type { Ref } from "vue";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { isSignificantUrlChange } from "@js/util/sbx/urlUtils";
import type { Payload } from "../../js/types/manageDifferentiationProfilesTypes";

/**
 * load the pastoral records from the given url.
 * @param url - the url to load the records from
 * @returns isLoading, payload, isEmpty, loadError
 */
export function useLoadData(url: Ref<string>): {
    isLoading: Ref<boolean>;
    payload: Ref<Payload | null>;
    isEmpty: Ref<boolean>;
    loadError: Ref<string | null>;
} {
    const isLoading = window.Vue.ref(true); // to notify when a new results is being loaded
    const payload = window.Vue.ref<Payload | null>(null); // to store the payload that matches the current query
    const isEmpty = window.Vue.computed(() => payload.value === null && !isLoading.value); // To show that no payload match the current report table
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the load
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * search for payload that match the current query
     */
    async function load(): Promise<void> {
        // abort the previous search if it's still running
        if (isLoading.value && controller) {
            controller.abort();
        }
        try {
            isLoading.value = true;
            loadError.value = null;
            controller = new AbortController();
            const response = await apiRequest<Payload>({
                url: url.value,
                method: "GET",
                signal: controller.signal,
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to load data");
            }
            const newPayload = response.data;
            // check if the response does not have the expected structure
            if (!Array.isArray(newPayload.data.profiles)) {
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
                return;
            }
            loadError.value = "An unexpected error occurred while loading the data.";
            console.error(error);
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * when the url to trigger load changes provided the url is not empty
     * and the new url is different from the old one, load the data
     */
    window.Vue.watch(url, (newUrl, oldUrl) => {
        if (url.value !== "" && newUrl !== oldUrl) {
            void load();
        }
    });

    /**
     * Load on mount if there's already a URL set
     */
    window.Vue.onMounted(() => {
        if (url.value !== "") {
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
