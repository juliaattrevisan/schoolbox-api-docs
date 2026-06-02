/**
 * Progress Tick Classes
 * This maps to the various classes that can be used to render a progress tick
 * See /www/static/css/core/ui/_content.scss for the styles associated with each state
 *
 * For most activity types, these represent:
 * - incomplete     The learner has not yet submitted any work (default, no class needed)
 * - pending        The learner has self-assessed the work and it is awaiting instructor review
 * - reviewed       The instructor has assessed the work
 * - failed         Resubmission Required
 *
 * For Dialog Agents, these represent:
 * - incomplete             The learner has not started yet (default, no class needed)
 * - in-progress            The learner has started but not yet submitted
 * - submitted              The response has been submitted
 * - requires-assistance    The learner is requesting assistance
 *
 * For Reporting, these represent:
 * - pending        Marking required
 * - reviewed       Reviewed
 */
export enum ProgressTickClass {
    INCOMPLETE = "incomplete", // default/fallback state
    PENDING = "pending",
    REVIEWED = "reviewed",
    FAILED = "failed",
    IN_PROGRESS = "in-progress",
    SUBMITTED = "submitted",
    REQUIRES_ASSISTANCE = "requires-assistance",
}
