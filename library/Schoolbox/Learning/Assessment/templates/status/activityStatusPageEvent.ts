/**
 * The different events that can be dispatched for the activity status page
 */

export const ACTIVITY_STATUS_PAGE_INIT = "ACTIVITY_STATUS_PAGE_INIT";

export type ActivityStatusPageInitEventDetail = {
    // The number of items to show per page
    pageItemLimit: number;
    // How many *more* items beyond pageItemLimit before it starts actually paginating
    // This means we can set a higher limit for one page (cover all normal class sizes) but still
    // have a smoother experience swapping between pages with a smaller list
    pageItemLimitOffset: number;
    // Selectors for relevant elements on the page
    selectors: {
        // The element that wraps where the paginator exists, if not provided then no pagination will occur
        // A <ul class="pagination"> element must exist within this element
        paginator: string | null;
        // The empty state to show when no results are found
        filterEmptyState: string;
        // The button to reset the search/filters
        filterReset: string;
        // The search input element, if not provided then no search functionality exists
        participantSearchInput: string | null;
        // The filter checkboxes (i.e. incomplete/self-assessed/teacher-assessed)
        participantFilters: string;
        // The loader element to use when switching tabs
        participantLoader: string;
        // The element that wraps the participant rows
        participantContainer: string;
        // The <li> elements that represent each participant row
        participantRows: string;
        // The selector for the participant name inside a participant row
        participantRowName: string;
        // The selector for the feedback comment textarea inside a participant row
        participantRowCommentTextarea: string;
    };
};

export interface ActivityStatusPageDocumentEventHandlerMap {
    [ACTIVITY_STATUS_PAGE_INIT]: CustomEvent<ActivityStatusPageInitEventDetail>;
}
