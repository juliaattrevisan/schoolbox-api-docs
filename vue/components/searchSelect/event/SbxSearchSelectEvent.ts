/**
 * The different events that can be dispatched for a search select component
 */

export const ON_FILTER_CHANGE = "SEARCH_SELECT__UPDATE_SELECTION";

export type FiltersChangeEventDetail = {
    elementId: string;
    selection: string;
};

export interface SbxSearchSelectFiltersChangeEventHandlerMap {
    [ON_FILTER_CHANGE]: CustomEvent<FiltersChangeEventDetail>;
}
