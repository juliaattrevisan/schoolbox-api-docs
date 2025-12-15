/**
 * Manages the loading of row data as well as the current height states of each row and cell
 * and their expanded/collapsed states.
 */

import { defineStore } from "pinia";
import type { Nested } from "@js/util/queryUtils";
import { SearchParam } from "@js/util/sbx/apiUtils";
import { getKeyValuePairs } from "@js/util/queryUtils";
import { ON_FILTER_CHANGE } from "../../js/MDPEvents";
import { useLoadData } from "../composable/useLoadData";

export const ANIMATION_DURATION = 0.5; // duration for animations in seconds

export const useRowsStore = defineStore(`rowsStore`, () => {
    const route = window.Vue.ref<string>("");
    const url = window.Vue.ref<string>("");
    const limit = 20; // the current set limit for the number of records to fetch at once
    const filters = window.Vue.ref<Nested>({});
    const { isNewLoading, isMoreLoading, payload, isEmpty, hasRows, loadError } = useLoadData(url);

    const unexpandedRowHeight: number = 250; // The default unexpanded height of a row, in px
    const rowContentHeights = window.Vue.ref<Record<number, Record<string, number>>>({});
    const viewMoreIDs = window.Vue.ref<number[]>([]);
    const focusedCells = window.Vue.ref<Record<number, string[]>>({});

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
     * Toggle the view more (expanded) state of a profile row
     * @param $id - the id of the profile to toggle
     */
    function toggleViewMore($id: number): void {
        if (viewMoreIDs.value.includes($id)) {
            viewMoreIDs.value = viewMoreIDs.value.filter((id) => id !== $id);
        } else {
            viewMoreIDs.value.push($id);
        }
    }

    /**
     * As content is loaded, we need to keep track of the height so we can correctly
     * size other cells from that profile when it is expanded.
     * @param $id - the id of the cell to set the content height for
     * @param headerKey - the header key to set the content height for
     * @param height - the height to set
     */
    function setContentHeight($id: number, headerKey: string, height: number): void {
        if (rowContentHeights.value[$id] === undefined) {
            rowContentHeights.value[$id] = {};
        }
        rowContentHeights.value[$id][headerKey] = Math.max(height, unexpandedRowHeight);
    }

    /**
     * Updates the focus state of a profile+header combination
     * @param $id - the profile to update the focus state for
     * @param headerKey - the header key to update the focus state for
     * @param isFocused - whether the cell is focused
     */
    function updateFocusedCell($id: number, headerKey: string, isFocused: boolean): void {
        if (isFocused) {
            if (focusedCells.value[$id] === undefined) {
                focusedCells.value[$id] = [];
            }
            focusedCells.value[$id].push(headerKey);
        } else {
            if (focusedCells.value[$id] === undefined) {
                return;
            }
            focusedCells.value[$id] = focusedCells.value[$id].filter((h) => h !== headerKey);
            if (focusedCells.value[$id].length === 0) {
                delete focusedCells.value[$id];
            }
        }
    }

    /**
     * The minimum height at which each cell should be rendered, based on:
     * - The height of the content in the cell
     * - The unexpanded height of the profile row
     * - Whether the cell is focused
     * - Whether the profile row is in a "View More" state
     * @returns { Record<number, Record<string, number>> } - a record of profile ID to a record of header key to minimum display height
     */
    const cellMinDisplayHeights = window.Vue.computed<Record<number, Record<string, number>>>(() => {
        const cellMinHeights: Record<number, Record<string, number>> = {};
        if (payload.value?.data?.profiles) {
            payload.value.data.profiles.forEach((profile) => {
                const isViewMore = viewMoreIDs.value.includes(profile.student.id);
                cellMinHeights[profile.student.id] = Object.fromEntries(
                    Object.entries(rowContentHeights.value[profile.student.id] || {}).map(
                        ([headerKey, contentHeight]) => {
                            if (isViewMore) return [headerKey, contentHeight];
                            if (focusedCells.value[profile.student.id]?.includes(headerKey))
                                return [headerKey, contentHeight];
                            return [headerKey, unexpandedRowHeight];
                        },
                    ),
                );
            });
        }
        return cellMinHeights;
    });

    /**
     * The display height of each profile row, based on the maximum of the minimum display heights of each cell inside
     * @returns { Record<number, number> } - a record of profile ID to display height
     */
    const rowDisplayHeights = window.Vue.computed<Record<number, number>>(() => {
        return Object.fromEntries(
            Object.entries(cellMinDisplayHeights.value).map(([$id, cellHeights]) => {
                const maxCellHeight = Math.max(...Object.values(cellHeights));
                if (Object.values(cellHeights).length === 0) {
                    return [$id, unexpandedRowHeight];
                }
                return [$id, maxCellHeight];
            }),
        );
    });

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
        unexpandedRowHeight,
        rowContentHeights,
        viewMoreIDs,
        focusedCells,
        toggleViewMore,
        setContentHeight,
        updateFocusedCell,
        rowDisplayHeights,
    };
});
