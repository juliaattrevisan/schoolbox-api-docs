import { ProgressTickClass } from "./progressTick";

/**
 * A representation of the current status a response should show as,
 * this usually correlates to what the progress tick should show alongside a given response
 */
export enum DialogAgentResponseStatus {
    INCOMPLETE = "incomplete",
    IN_PROGRESS = "in-progress",
    ASSISTANCE = "requires-assistance",
    SUBMITTED = "submitted",
}

/** The labels associated with each response status */
export const STATUS_LABELS: Record<DialogAgentResponseStatus, string> = {
    [DialogAgentResponseStatus.INCOMPLETE]: "Pending", // yep, it's different here
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
