/**
 * The different events that can be dispatched for a lazy grid
 */

import type { Nested } from "@js/util/queryUtils";

export const ON_FILTER_CHANGE = "lazy-grid__onFilterChange";

export type FiltersChangeEventDetail = {
    selectedFilters: Nested;
};

export interface FiltersChangeEventHandlerMap {
    [ON_FILTER_CHANGE]: CustomEvent<FiltersChangeEventDetail>;
}
