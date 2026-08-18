/**
 * Types for the AI usage log admin API
 * @see /library/Schoolbox/Admin/OpenAI/Usage/Controller.php
 */
import type { DateRFC3339 } from "@js/util/dateUtils";
import type { PaginationMeta } from "@js/util/sbx/apiUtils";
import type { AgentChatLogEntry } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { AiModerationEvent, AiModerationStatus } from "./moderationEvent";

/**
 * A unique guid for an AI session, used to group related requests such as a chat conversation together
 * Some endpoints have one session ID per request (such as lesson plans) as they don't connect with each other
 */
export type AiSessionId = string;

/**
 * A value for each AI feature.
 * @see /library/Schoolbox/OpenAi/Feature.php
 */
export enum AiFeature {
    CHAT = "chat",
    LESSON_PLAN = "lessonplan",
    IMAGE = "image",
    DIALOG_AGENT = "dialogagent",
    DIFFERENTIATION = "differentiation",
    LEARNING_ACTIVITY_CUSTOMISATION = "learningactivitycustomisation",
    RUBRIC_GENERATION = "rubricgeneration",
    AI_MODERATION_AGENT = "aimoderationagent",
}

/**
 * Each log format represents a different way that log data should be represented
 * @see /library/Schoolbox/Editor/OpenAI/Log/LogFormat.php
 */
export enum AiLogFormat {
    AGENT_CHAT = "agent_chat",
    GUARDRAIL = "guardrail",
}

type AiLogFormatDataMap = {
    [AiLogFormat.AGENT_CHAT]: { input: AgentChatLogEntry; output: AgentChatLogEntry };
    [AiLogFormat.GUARDRAIL]: {
        input: { type: "request"; data: string; createdAt: DateRFC3339 };
        output: { createdAt: DateRFC3339 } & (
            | { type: "error"; data: string }
            | { type: "guardrail"; data: { guardrail: string; flagged: boolean; metadata: object } }
        );
    };
};

/** @see /library/Schoolbox/Editor/OpenAI/Log/Translator/Session/ModelToArray.php */
export type AiSessionLog = {
    id: number;
    sessionId: AiSessionId;
    createdAt: DateRFC3339;
    /** createdBy may be null if the log entry was system-generated */
    createdBy: UserAuthor | null;
} & (
    | { [K in AiLogFormat]: { logFormat: K; data: AiLogFormatDataMap[K] } }[AiLogFormat]
    | { logFormat: null; data: null }
);

/** @see /library/Schoolbox/Editor/OpenAI/Log/Translator/ModelToArray.php */
export type AiSession<TLogFormat extends AiLogFormat = AiLogFormat, TFeature extends AiFeature = AiFeature> = {
    id: AiSessionId;
    feature: TFeature;
    logFormat: TLogFormat | null;
    createdAt: DateRFC3339;
    /** createdBy may be null if the log entry was system-generated */
    createdBy: UserAuthor | null;
    hasError: boolean;
    /** The highest-priority active moderation status for the session, or null when there is no active concern */
    moderationStatus: AiModerationStatus | null;
    _routes: {
        getSessionLogs?: string | null;
    };
};

export type AiUsageLogApiRequest = {
    keyword: string | null;
    feature: string | null;
    user: number | null;
    startDate: string | null;
    endDate: string | null;
    moderationStatus: AiModerationStatus[] | null;
    page?: number;
    limit?: number;
};

export type AiUsageLogApiResponse = {
    data: AiSession[];
    metadata: PaginationMeta;
};

export type AiSessionLogApiResponse<TLogFormat extends AiLogFormat = AiLogFormat> = {
    logs: (AiSessionLog & { logFormat: TLogFormat })[];
    moderationEvents: AiModerationEvent[];
};
