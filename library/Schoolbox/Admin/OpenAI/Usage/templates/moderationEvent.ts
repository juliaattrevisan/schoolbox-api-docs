import type { DateRFC3339 } from "@js/util/dateUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";

/**
 * Possible statuses for an AI Interaction Moderation Event.
 * @see /library/Schoolbox/OpenAi/Moderation/Event/Status.php
 */
export enum AiModerationStatus {
    NO_BEHAVIOUR_FLAGGED = "no_behaviour_flagged",
    REQUIRES_ATTENTION = "requires_attention",
    NO_ACTION_REQUIRED = "no_action_required",
    FURTHER_ACTION_REQUIRED = "further_action_required",
    FURTHER_ACTION_TAKEN = "further_action_taken",
}

/**
 * Human-readable labels for each moderation status
 */
export const STATUS_LABELS: Record<AiModerationStatus, string> = {
    [AiModerationStatus.NO_BEHAVIOUR_FLAGGED]: "No behaviour flagged",
    [AiModerationStatus.REQUIRES_ATTENTION]: "Requires attention",
    [AiModerationStatus.NO_ACTION_REQUIRED]: "No action required",
    [AiModerationStatus.FURTHER_ACTION_REQUIRED]: "Further action required",
    [AiModerationStatus.FURTHER_ACTION_TAKEN]: "Further action taken",
};

/**
 * A subset of an AI moderation result's data
 * @see /library/Schoolbox/OpenAi/Moderation/Result/Result.php
 */
type AiModerationEventResult = {
    /** A short plain English summary explaining why the interaction was or was not flagged. */
    summary: string;
    /** A list of detected concern categories. An empty array if no concerns are found. */
    categories: string[];
};

/**
 * Information about a particular AI Interaction Moderation event.
 * @see /library/Schoolbox/OpenAi/Moderation/Event/Translator/ModelToArray.php
 */
export type AiModerationEvent = {
    id: number;
    status: AiModerationStatus;
    result: AiModerationEventResult;
    reviewedBy: UserAuthor | null;
    resolvedBy: UserAuthor | null;
    createdAt: DateRFC3339;
    _routes: {
        postNoActionRequired?: string | null;
        postFurtherActionRequired?: string | null;
        postFurtherActionTaken?: string | null;
    };
};
