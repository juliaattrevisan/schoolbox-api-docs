import { ProgressTickClass } from "./progressTick";

/**
 * The possible actions that can be done on a response
 * @see /library/Schoolbox/Learning/Assessment/DialogAgent/Response/Lookup/Status.php
 */
export enum DialogAgentResponseAction {
    ACTION_VIEW = "view",
    ACTION_SOBO = "sobo",
    ACTION_REOPEN = "reopen",
    ACTION_RESOLVE = "resolve",
    ACTION_RESET = "reset", // reset is done per participant, not per response
}

/**
 * A representation of the current status a response should show as,
 * this usually correlates to what the progress tick should show alongside a given response
 * @see /library/Schoolbox/Learning/Assessment/DialogAgent/Response/Lookup/Status.php
 */
export enum DialogAgentResponseStatus {
    INCOMPLETE = "incomplete",
    IN_PROGRESS = "in-progress",
    ASSISTANCE = "requires-assistance",
    SUBMITTED = "submitted",
}

/** The labels associated with each response status */
export const STATUS_LABELS: Record<DialogAgentResponseStatus, string> = {
    [DialogAgentResponseStatus.INCOMPLETE]: "Incomplete",
    [DialogAgentResponseStatus.IN_PROGRESS]: "In Progress",
    [DialogAgentResponseStatus.ASSISTANCE]: "Requires Assistance",
    [DialogAgentResponseStatus.SUBMITTED]: "Submitted",
};

/** The progress tick icons associated with each response status */
export const STATUS_TICKS: Record<DialogAgentResponseStatus, ProgressTickClass> = {
    [DialogAgentResponseStatus.INCOMPLETE]: ProgressTickClass.INCOMPLETE,
    [DialogAgentResponseStatus.IN_PROGRESS]: ProgressTickClass.IN_PROGRESS,
    [DialogAgentResponseStatus.ASSISTANCE]: ProgressTickClass.REQUIRES_ASSISTANCE,
    [DialogAgentResponseStatus.SUBMITTED]: ProgressTickClass.SUBMITTED,
};
