/**
 * Manages the height states of each row and cell and their expanded/collapsed states
 * for the lazy grid component.
 */

import { defineStore } from "pinia";

export const ANIMATION_DURATION = 0.5; // duration for animations in seconds

export const useLazyGridRowsStore = defineStore(`lazyGridRowsStore`, () => {
    const unexpandedRowHeight: number = 250; // The default unexpanded height of a row, in px
    const rowContentHeights = window.Vue.ref<Record<number, Record<string, number>>>({});
    const viewMoreIDs = window.Vue.ref<number[]>([]);
    const focusedCells = window.Vue.ref<Record<number, string[]>>({});

    /**
     * Toggle the view more (expanded) state of a row
     * @param $id - the id of the row to toggle
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
     * size other cells from that row when it is expanded.
     * @param $id - the id of the row to set the content height for
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
     * Updates the focus state of a row+header combination
     * @param $id - the row to update the focus state for
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
     * Set of active row IDs based on rows that have content heights recorded
     * @returns {Set<number>} - set of row IDs
     */
    const activeRowIDs = window.Vue.computed<Set<number>>(() => {
        return new Set(Object.keys(rowContentHeights.value).map(Number));
    });

    /**
     * The minimum height at which each cell should be rendered, based on:
     * - The height of the content in the cell
     * - The unexpanded height of the row
     * - Whether the cell is focused
     * - Whether the row is in a "View More" state
     * @returns { Record<number, Record<string, number>> } - a record of row ID to a record of header key to minimum display height
     */
    const cellMinDisplayHeights = window.Vue.computed<Record<number, Record<string, number>>>(() => {
        const cellMinHeights: Record<number, Record<string, number>> = {};

        activeRowIDs.value.forEach((rowId) => {
            const isViewMore = viewMoreIDs.value.includes(rowId);
            cellMinHeights[rowId] = Object.fromEntries(
                Object.entries(rowContentHeights.value[rowId] || {}).map(([headerKey, contentHeight]) => {
                    if (isViewMore) return [headerKey, contentHeight];
                    if (focusedCells.value[rowId]?.includes(headerKey)) return [headerKey, contentHeight];
                    return [headerKey, unexpandedRowHeight];
                }),
            );
        });

        return cellMinHeights;
    });

    /**
     * The display height of each row, based on the maximum of the minimum display heights of each cell inside
     * @returns { Record<number, number> } - a record of row ID to display height
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
