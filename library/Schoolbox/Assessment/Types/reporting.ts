import { ProgressTickClass } from "./progressTick";

/**
 * A representation of the current status a response should show as,
 * this usually correlates to what the progress tick should show alongside a given response
 *
 * TODO: currently just used and inferred on the reporting markbook, the backend doesn't
 * deal with these at the moment. See /library/Schoolbox/Learning/Assessment/DialogAgent/Response/Status.php
 * for how this was implemented for dialog agent, if that ever becomes relevant.
 */
export enum ReportingFeedbackStatus {
    PENDING = "pending",
    REVIEWED = "reviewed",
}

/** The labels associated with each response status */
export const STATUS_LABELS: Record<ReportingFeedbackStatus, string> = {
    [ReportingFeedbackStatus.PENDING]: "Marking Required",
    [ReportingFeedbackStatus.REVIEWED]: "Reviewed",
};

/** The progress tick icons associated with each response status */
export const STATUS_TICKS: Record<ReportingFeedbackStatus, ProgressTickClass> = {
    [ReportingFeedbackStatus.PENDING]: ProgressTickClass.PENDING,
    [ReportingFeedbackStatus.REVIEWED]: ProgressTickClass.REVIEWED,
};
