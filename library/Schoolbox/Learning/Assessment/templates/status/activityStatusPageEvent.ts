/**
 * The different events that can be dispatched for the activity status page
 */

export const ACTIVITY_STATUS_PAGE_INIT = "ACTIVITY_STATUS_PAGE_INIT";

export type ActivityStatusPageInitEventDetail = {
    // The number of items to show per page
    pageItemLimit: number;
};

export interface ActivityStatusPageDocumentEventHandlerMap {
    [ACTIVITY_STATUS_PAGE_INIT]: CustomEvent<ActivityStatusPageInitEventDetail>;
}
