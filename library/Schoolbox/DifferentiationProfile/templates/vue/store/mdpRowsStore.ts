/**
 * Manages the loading of row data for the MDP grid.
 */

import { defineStore } from "pinia";
import type { Nested } from "@js/util/queryUtils";
import { SearchParam } from "@js/util/sbx/apiUtils";
import { getKeyValuePairs } from "@js/util/queryUtils";
import type { Header } from "@schoolboxVue/components/grid/lazyGrid/types/lazyGridTypes";
import { ON_FILTER_CHANGE } from "@schoolboxVue/components/grid/lazyGrid/event/LazyGridEvent";
import { useLoadData } from "../composable/useLoadData";

export const useRowsStore = defineStore(`rowsStore`, () => {
    const header = window.Vue.ref<Header[]>([]);
    const route = window.Vue.ref<string>("");
    const url = window.Vue.ref<string>("");
    const limit = 20; // the current set limit for the number of records to fetch at once
    const order = window.Vue.ref<"ASC" | "DESC">("ASC"); // the current sort order
    const filters = window.Vue.ref<Nested>({});
    const { isNewLoading, isMoreLoading, payload, isEmpty, hasRows, loadError, sortBy } = useLoadData(header, url);

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
            searchParams.set(SearchParam.LIMIT, limit.toString());
            searchParams.set(SearchParam.SORT_BY, sortBy.value);
            searchParams.set(SearchParam.ORDER, order.value);
            searchParams.set(SearchParam.CURSOR, ""); // Reset cursor on sort change
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
        searchParams.set(SearchParam.LIMIT, limit.toString());
        searchParams.set(SearchParam.SORT_BY, sortBy.value);
        searchParams.set(SearchParam.ORDER, order.value);
        searchParams.set(SearchParam.CURSOR, cursor);
        newUrl.search = searchParams.toString();
        url.value = newUrl.toString();
    }

    /**
     * Handle sort button click
     * @param headerKey - the key of the header that was clicked for sorting
     */
    function onSort(headerKey: string): void {
        // If clicking the same header, toggle sort order
        if (sortBy.value === headerKey) {
            order.value = order.value === "ASC" ? "DESC" : "ASC";
        } else {
            // New header selected, set to ascending and update sortBy
            sortBy.value = headerKey;
            order.value = "ASC";
        }
        const newUrl = new URL(route.value, window.location.origin);
        const searchParams = new URLSearchParams();
        addFilterSearchParams(searchParams, filters.value);
        searchParams.set(SearchParam.LIMIT, limit.toString());
        searchParams.set(SearchParam.SORT_BY, sortBy.value);
        searchParams.set(SearchParam.ORDER, order.value);
        searchParams.set(SearchParam.CURSOR, ""); // Reset cursor on sort change
        newUrl.search = searchParams.toString();
        url.value = newUrl.toString();
    }

    return {
        url,
        limit,
        order,
        sortBy,
        isNewLoading,
        isMoreLoading,
        payload,
        isEmpty,
        hasRows,
        loadError,
        init,
        onPaginate,
        onSort,
    };
});
