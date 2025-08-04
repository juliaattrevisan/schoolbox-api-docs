/**
 * Retrieve a set of pastoral records based on the provided url.
 */

import type { Ref } from "vue";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { isSignificantUrlChange } from "@js/util/sbx/urlUtils";
import type { RecordsPayload } from "../../js/types/pastoralReportTypes";

/**
 * load the pastoral records from the given url.
 * @param url - the url to load the records from
 * @returns isNewLoading, payload, isEmpty, loadError
 */
export function useLoadRecords(url: Ref<string>): {
    isNewLoad: Ref<boolean>;
    isNewLoading: Ref<boolean>;
    isMoreLoading: Ref<boolean>;
    payload: Ref<RecordsPayload | null>;
    isEmpty: Ref<boolean>;
    loadError: Ref<string | null>;
} {
    const isNewLoad = window.Vue.ref(false); // to distinguish between loading a new results and loading more results of the same set
    const isNewLoading = window.Vue.ref(false); // to notify when a new results is being loaded
    const isMoreLoading = window.Vue.ref(false); // to notify when more of the same results is being loaded
    const payload = window.Vue.ref<RecordsPayload | null>(null); // to store the payload that matches the current query
    const isEmpty = window.Vue.computed(() => payload.value === null && !isNewLoading.value); // To show that no payload match the current report table
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the load
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * search for payload that match the current query
     */
    async function load(): Promise<void> {
        // abort the previous search if it's still running
        if (isNewLoading.value) {
            controller.abort();
        }
        try {
            // to distinguish between loading a new results and loading more results of the same set
            if (isNewLoad.value) {
                isNewLoading.value = true;
            } else {
                isMoreLoading.value = true;
            }
            loadError.value = null;
            controller = new AbortController();
            const response = await apiRequest<RecordsPayload>({
                url: url.value,
                method: "GET",
                signal: controller.signal,
            });
            if (!response.success) {
                throw new Error(response.message || "Failed to load data");
            }
            const newPayload = response.data;
            // check if the response does not have the expected structure
            if (!Array.isArray(newPayload.data.results)) {
                throw new Error("Unexpected response format");
            }
            // reset the payload if it's a new load or append to the existing results if it's a load of more results
            if (isNewLoad.value) {
                payload.value = newPayload;
            } else {
                payload.value?.data.results.push(...newPayload.data.results);
            }
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
            if (isNewLoad.value) {
                isNewLoading.value = false;
            } else {
                isMoreLoading.value = false;
            }
        }
    }

    /**
     * when the url to trigger load changes provided the url is not empty
     * and the new url is different from the old one, load the data
     */
    window.Vue.watch(url, (newUrl, oldUrl) => {
        if (url.value !== "" && newUrl !== oldUrl) {
            isNewLoad.value = isSignificantUrlChange(oldUrl, newUrl, ["cursor"]);
            void load();
        }
    });

    return {
        isNewLoad,
        isNewLoading,
        isMoreLoading,
        payload,
        isEmpty,
        loadError,
    };
}
