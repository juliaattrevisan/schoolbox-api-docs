/**
 * To hold the row data for the MDP editor
 */

import { defineStore } from "pinia";
import type { User } from "@schoolboxLibrary/types/User";
import type { Nested } from "@js/util/queryUtils";
import { SearchParam } from "@js/util/sbx/apiUtils";
import { getKeyValuePairs } from "@js/util/queryUtils";
import { ON_FILTER_CHANGE } from "../../js/MDPEvents";
import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";
import { useLoadData } from "../composable/useLoadData";

export const useMdpRowsStore = defineStore(`mdpRowsStore`, () => {
    const route = window.Vue.ref<string>("");
    const url = window.Vue.ref<string>("");
    const limit = 20; // the current set limit for the number of records to fetch at once
    const filters = window.Vue.ref<Nested>({});
    const { isNewLoading, isMoreLoading, payload, isEmpty, hasRows, loadError } = useLoadData(url);

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
     * @param $route - the route to load the rows from
     */
    function init($route: string): void {
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

    /**
     * Get the row for the given student
     * @param student - the student to get the row for
     */
    function getRow(student: User): Profile | undefined {
        if (payload.value === null || payload.value.data.profiles.length === 0) {
            return undefined;
        }
        return payload.value.data.profiles.find((profile) => profile.student.id === student.id) || undefined;
    }

    /**
     * Update the row for the given student
     * @param student
     * @param row
     * @returns
     */
    function setRow(student: User, row: Profile): void {
        if (payload.value === null || payload.value.data.profiles.length === 0) {
            return;
        }
        const index = payload.value.data.profiles.findIndex((profile) => profile.student.id === student.id);
        if (index !== -1) {
            payload.value.data.profiles[index] = row;
        }
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
        getRow,
        setRow,
    };
});
