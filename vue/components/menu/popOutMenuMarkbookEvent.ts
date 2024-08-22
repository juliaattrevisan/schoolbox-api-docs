import type { ActivityType } from "@schoolboxAssessment/Types/assessmentTypes";

/**
 * the routes to load the activity data, as well as links to further actions
 * like 'Update feedback' or 'Exclude Student'
 */
export interface PopOutMenuMarkbookRoutes {
    dataEndpoint: string;
    updateRoute: string;
    reviewRoute: string;
    publishRoute: string;
    excludeRoute: string;
    includeRoute: string;
    historyRoute: string;
}

export interface PopOutMenuMarkbookSelectedEvent {
    target: HTMLTableCellElement;
    routes: PopOutMenuMarkbookRoutes;
    isExcluded: boolean;
    isParticipant: boolean;
    assessmentType: ActivityType;
}
