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

/**
 * Human-readable labels for each moderation status
 */
export const STATUS_LABELS: Record<AiModerationStatus, string> = {
    [AiModerationStatus.NO_ACTION_REQUIRED]: "No action required",
    [AiModerationStatus.REQUIRES_ATTENTION]: "Requires attention",
    [AiModerationStatus.FURTHER_ACTION_REQUIRED]: "Further action required",
    [AiModerationStatus.FURTHER_ACTION_TAKEN]: "Further action taken",
};
