/**
 * Retrieve a set of table payload data based on the provided url.
 */

import type { Ref } from "vue";
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { isNewUrl } from "@js/util/queryUtils";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Header, Payload } from "@schoolboxVue/components/grid/lazyGrid/types/lazyGridTypes";

/**
 * load the table data from the given url.
 * @param header - the headers for the grid
 * @param url - the url to load the table data
 * @returns - isNewLoading, isMoreLoading, payload, sortBy, isEmpty, hasRows, loadError
 */
export function useLoadData(
    header: Ref<Header[]>,
    url: Ref<string>,
): {
    isNewLoading: Ref<boolean>;
    isMoreLoading: Ref<boolean>;
    payload: Ref<Payload | null>;
    sortBy: Ref<string>;
    isEmpty: Ref<boolean>;
    hasRows: Ref<boolean>;
    loadError: Ref<string | null>;
} {
    const isNewLoad = window.Vue.ref(false); // to distinguish between loading a new table and loading data for the same table
    const isNewLoading = window.Vue.ref(false); // to notify when a new table is being loaded
    const isMoreLoading = window.Vue.ref(false); // to notify when the data linked to the same table is being loaded
    const payload = window.Vue.ref<Payload | null>(null); // to store the payload that matches the current query
    const sortBy = window.Vue.ref<string>(""); // the current sort by field - this should update when a new payload is loaded
    const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the load
    const isEmpty = window.Vue.computed(
        () =>
            !isNewLoading.value &&
            !isMoreLoading.value &&
            loadError.value === null &&
            payload.value !== null &&
            payload.value.metadata.count === 0,
    ); // To show that no payload match the current report table
    const hasRows = window.Vue.computed(
        () => payload.value !== null && payload.value.data.length > 0 && payload.value.metadata.count > 0,
    ); // To show that the report table has rows
    let controller: AbortController; // to allow aborting the previous search if a new one is started

    /**
     * get the default sortBy field based on the first sortable header in header structure
     * @returns the default sortBy field
     */
    function getDefaultSortBy(): string {
        // we assume that the first tableHeader is isSortable true is the default sortBy
        if (header.value && header.value.length > 0) {
            const firstSortableHeader = header.value.find(($header) => $header.isSortable);
            return firstSortableHeader ? firstSortableHeader.key : "";
        }
        return "";
    }

    /**
     * search for payload that match the current query
     */
    async function load(): Promise<void> {
        // abort the previous search if it's still running
        if (isNewLoading.value || isMoreLoading.value) {
            controller.abort();
        }
        try {
            // to distinguish between loading a new table and loading data for the same table
            // so the entire table doesn't need to be re-rendered to just load new rows
            if (isNewLoad.value) {
                isNewLoading.value = true;
            } else {
                isMoreLoading.value = true;
            }
            loadError.value = null;
            controller = new AbortController();
            const response = await apiRequest<Payload>({
                url: url.value,
                method: "GET",
                signal: controller.signal,
            });
            if (!response.success) {
                if ("detail" in response && response.detail && response.detail instanceof Error) {
                    throw response.detail;
                }
                throw new Error(response.message || "Failed to load data");
            }
            const newPayload = response.data;
            // check if the response does not have the expected structure
            if (!Array.isArray(newPayload.data)) {
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
            if (isNewLoad.value) {
                sortBy.value = getDefaultSortBy();
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
            isNewLoad.value = isNewUrl(oldUrl, newUrl);
            void load();
        }
    });

    /**
     * Set the default sortBy for the first load
     */
    window.Vue.onMounted(() => {
        sortBy.value = getDefaultSortBy();
    });

    return {
        isNewLoading,
        isMoreLoading,
        payload,
        sortBy,
        isEmpty,
        hasRows,
        loadError,
    };
}
