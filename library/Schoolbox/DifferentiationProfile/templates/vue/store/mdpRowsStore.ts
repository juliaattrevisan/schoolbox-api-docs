/**
 * To hold the row data for the MDP editor
 */

import { defineStore } from "pinia";
import type { User } from "@schoolboxLibrary/types/User";
import type { Nested } from "@js/util/queryUtils";
import { SearchParam } from "@js/util/sbx/apiUtils";
import { getKeyValuePairs } from "@js/util/queryUtils";
import { ON_FILTER_CHANGE } from "../../js/MDPEvents";
import type { Profile, ProfileTextKeys } from "../../js/types/manageDifferentiationProfilesTypes";
import { useLoadData } from "../composable/useLoadData";

export const useMdpRowsStore = defineStore(`mdpRowsStore`, () => {
    const route = window.Vue.ref<string>("");
    const url = window.Vue.ref<string>("");
    const limit = 20; // the current set limit for the number of records to fetch at once
    const filters = window.Vue.ref<Nested>({});
    const { isNewLoading, isMoreLoading, payload, isEmpty, hasRows, loadError } = useLoadData(url);

    // MDP Grid cell height management - adapted from unitPlannerStore
    const unexpandedRowHeight: number = 250; // The default unexpanded height of a row, in px
    const profileContentHeights = window.Vue.ref<Record<number, Record<string, number>>>({});
    const viewMoreProfileIDs = window.Vue.ref<number[]>([]);
    const focusedProfileCells = window.Vue.ref<Record<number, string[]>>({});

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

    /**
     * Toggle the view more (expanded) state of a profile row
     * @param profileId - the id of the profile to toggle
     */
    function toggleViewMore(profileId: number): void {
        if (viewMoreProfileIDs.value.includes(profileId)) {
            viewMoreProfileIDs.value = viewMoreProfileIDs.value.filter((id) => id !== profileId);
        } else {
            viewMoreProfileIDs.value.push(profileId);
        }
    }

    /**
     * As content is loaded, we need to keep track of the height so we can correctly
     * size other cells from that profile when it is expanded.
     * @param profile - the profile to set the content height for
     * @param headerKey - the header key to set the content height for
     * @param height - the height to set
     */
    function setProfileContentHeight(profile: Profile, headerKey: ProfileTextKeys, height: number): void {
        if (profileContentHeights.value[profile.student.id] === undefined) {
            profileContentHeights.value[profile.student.id] = {};
        }

        profileContentHeights.value[profile.student.id][headerKey] = Math.max(height, unexpandedRowHeight);
    }

    /**
     * Updates the focus state of a profile+header combination
     * @param profileId - the profile to update the focus state for
     * @param headerKey - the header key to update the focus state for
     * @param isFocused - whether the cell is focused
     */
    function updateFocusedProfileCell(profileId: number, headerKey: ProfileTextKeys, isFocused: boolean): void {
        if (isFocused) {
            if (focusedProfileCells.value[profileId] === undefined) {
                focusedProfileCells.value[profileId] = [];
            }

            focusedProfileCells.value[profileId].push(headerKey);
        } else {
            if (focusedProfileCells.value[profileId] === undefined) {
                return;
            }

            focusedProfileCells.value[profileId] = focusedProfileCells.value[profileId].filter((h) => h !== headerKey);
            if (focusedProfileCells.value[profileId].length === 0) {
                delete focusedProfileCells.value[profileId];
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
    const profileCellMinDisplayHeights = window.Vue.computed<Record<number, Record<string, number>>>(() => {
        const cellMinHeights: Record<number, Record<string, number>> = {};

        if (payload.value?.data?.profiles) {
            payload.value.data.profiles.forEach((profile) => {
                const isViewMore = viewMoreProfileIDs.value.includes(profile.student.id);

                cellMinHeights[profile.student.id] = Object.fromEntries(
                    Object.entries(profileContentHeights.value[profile.student.id] || {}).map(
                        ([headerKey, contentHeight]) => {
                            if (isViewMore) return [headerKey, contentHeight];
                            if (focusedProfileCells.value[profile.student.id]?.includes(headerKey))
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
    const profileRowDisplayHeights = window.Vue.computed<Record<number, number>>(() => {
        return Object.fromEntries(
            Object.entries(profileCellMinDisplayHeights.value).map(([profileId, cellHeights]) => {
                const maxCellHeight = Math.max(...Object.values(cellHeights));

                if (maxCellHeight < 0) {
                    return [profileId, unexpandedRowHeight];
                }
                return [profileId, maxCellHeight];
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
        getRow,
        setRow,
        unexpandedRowHeight,
        profileContentHeights,
        viewMoreProfileIDs,
        focusedProfileCells,
        toggleViewMore,
        setProfileContentHeight,
        updateFocusedProfileCell,
        profileRowDisplayHeights,
    };
});
