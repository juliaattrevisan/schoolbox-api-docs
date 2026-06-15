/**
 * Possible statuses for an AI Interaction Moderation Event.
 * @see /library/Schoolbox/OpenAi/Moderation/Event/Status.php
 */
export enum AiModerationStatus {
    NO_ACTION_REQUIRED = "no_action_required",
    REQUIRES_ATTENTION = "requires_attention",
    FURTHER_ACTION_REQUIRED = "further_action_required",
    FURTHER_ACTION_TAKEN = "further_action_taken",
}
