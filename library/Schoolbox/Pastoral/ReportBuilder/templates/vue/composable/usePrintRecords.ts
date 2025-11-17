/**
 * Composable to handle printing all records functionality
 * Handles the logic for fetching all records when needed and triggering print
 * This is a bit of a dirty hack to print 'all' records, pushing the limit to 1000,
 * waiting for all records to load and images to render before calling window.print()
 */

import type { Ref } from "vue";
import { waitForAllImages } from "@js/util/domUtils";
import { ViewUrlParam, type RecordsPayload } from "../../js/types/pastoralReportTypes";

/**
 * @param url - The URL ref for fetching records
 * @param urlParams - URLSearchParams object for managing URL parameters
 * @param payload - The payload ref containing the fetched data and metadata
 * @param containerEl - The container element ref for waiting for images to load
 * @param baseUrl - The base URL to use when constructing the full URL for fetching all records
 * @returns Object with printAllRecords method
 */
export function usePrintRecords(
    url: Ref<string>,
    urlParams: URLSearchParams,
    payload: Ref<RecordsPayload | null>,
    containerEl: Ref<HTMLDivElement | null | undefined>,
    baseUrl?: string,
): {
    printAllRecords: () => void;
    isLoading: Ref<boolean>;
} {
    const isLoading = window.Vue.ref(false);
    const LIMIT = 1000;

    /**
     * Print all records in the list
     * If all records are already loaded, print immediately
     * If not, fetch all records first then print
     */
    function printAllRecords(): void {
        // all records are already loaded when there's no metadata or no next cursor so just print
        if (
            (payload.value && payload.value.metadata === undefined) ||
            (payload.value && payload.value.metadata?.cursor.next === null)
        ) {
            window.print();
            return;
        }
        // from here we assume next cursor exists otherwise we would have printed already
        if (payload.value === null || payload.value.metadata?.cursor.next === null) {
            return;
        }
        // Need to fetch all records first
        window.Vue.watch(
            payload,
            async () => {
                await window.Vue.nextTick();
                if (containerEl.value) {
                    await waitForAllImages(containerEl.value);
                }
                isLoading.value = false;
                await window.Vue.nextTick();
                window.print();
            },
            { deep: true, once: true }, // only watch once as we only need to fetch once
        );
        urlParams.set(ViewUrlParam.CURSOR, payload.value.metadata?.cursor.next as string);
        // Set limit to high number to load all records
        urlParams.set(ViewUrlParam.LIMIT, LIMIT.toString());
        // Use provided base URL or extract from current URL
        const targetBaseUrl = baseUrl || url.value.split("?")[0];
        url.value = `${targetBaseUrl}?${urlParams.toString()}`;
        isLoading.value = true;
    }

    return {
        printAllRecords,
        isLoading,
    };
}
