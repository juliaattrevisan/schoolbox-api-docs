/**
 * The different events that can be dispatched for the activity status page
 */

import { Nested } from "@js/util/queryUtils";

export const ON_FILTER_CHANGE = "manage-differentiation-profiles__onFilterChange";

export type FiltersChangeEventDetail = {
    selectedFilters: Nested;
};

export interface FiltersChangeEventHandlerMap {
    [ON_FILTER_CHANGE]: CustomEvent<FiltersChangeEventDetail>;
}
