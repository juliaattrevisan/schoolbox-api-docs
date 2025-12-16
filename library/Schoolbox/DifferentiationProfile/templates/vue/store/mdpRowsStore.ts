/**
 * Manages the loading of row data for the MDP grid.
 */

import { defineStore } from "pinia";
import type { Nested } from "@js/util/queryUtils";
import { SearchParam } from "@js/util/sbx/apiUtils";
import { getKeyValuePairs } from "@js/util/queryUtils";
import type { Header } from "@schoolboxVue/components/grid/lazyGrid/types/lazyGridTypes";
import { ON_FILTER_CHANGE } from "../../js/MDPEvents";
import { useLoadData } from "../composable/useLoadData";

export const useRowsStore = defineStore(`rowsStore`, () => {
    const header = window.Vue.ref<Header[]>([]);
    const route = window.Vue.ref<string>("");
    const url = window.Vue.ref<string>("");
    const limit = 20; // the current set limit for the number of records to fetch at once
    const filters = window.Vue.ref<Nested>({});
    const { isNewLoading, isMoreLoading, payload, isEmpty, hasRows, loadError } = useLoadData(header, url);

    /**
     * Adds filter search parameters to the given URLSearchParams object.
     * @param $searchParams - The URLSearchParams object to modify.
     * @param $filters - The filters to apply.
     */
    function addFilterSearchParams($searchParams: URLSearchParams, $filters: Nested): void {
        const keyValuePairs = getKeyValuePairs({ filter_selection: $filters });
        const map = new Map(Object.entries(keyValuePairs));
        map.forEach((value, key) => {
            $searchParams.append(key, value);
        });
    }

    /**
     * Listen for filter changes and reload data accordingly
     */
    function initFilterListener(): void {
        document.addEventListener(ON_FILTER_CHANGE, (event) => {
            filters.value = event.detail.selectedFilters;
            const newUrl = new URL(route.value, window.location.origin);
            const searchParams = new URLSearchParams();
            addFilterSearchParams(searchParams, filters.value);
            searchParams.set("limit", limit.toString());
            newUrl.search = searchParams.toString();
            url.value = newUrl.toString();
        });
    }

    /**
     * store any passed through properties
     * @param $header - the headers for the grid
     * @param $route - the route to load the rows from
     */
    function init($header: Header[], $route: string): void {
        header.value = $header;
        route.value = $route;
        initFilterListener();
    }

    /**
     * Handle previous/next page button clicks
     * @param cursor - the cursor to use for the previous/next page
     */
    function onPaginate(cursor: string): void {
        const newUrl = new URL(route.value, window.location.origin);
        const searchParams = new URLSearchParams();
        addFilterSearchParams(searchParams, filters.value);
        searchParams.set("limit", limit.toString());
        searchParams.set(SearchParam.CURSOR as string, cursor);
        newUrl.search = searchParams.toString();
        url.value = newUrl.toString();
    }

    return {
        url,
        limit,
        isNewLoading,
        isMoreLoading,
        payload,
        isEmpty,
        hasRows,
        loadError,
        init,
        onPaginate,
    };
});
