/**
 * Types for the AI usage log admin API
 * @see /library/Schoolbox/Admin/OpenAI/Usage/Controller.php
 */
import type { PaginationMeta } from "@js/util/sbx/apiUtils";
import type { AiLogAgentChatData } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { UserAuthor } from "@schoolboxLibrary/types/User";

/**
 * A unique guid for an AI session, used to group related requests such as a chat conversation together
 * Some endpoints have one session ID per request (such as lesson plans) as they don't connect with each other
 */
export type AiSessionId = string;

export enum AiFeature {
    CHAT = "chat",
    LESSON_PLAN = "lessonplan",
    IMAGE = "image",
    DIALOG_AGENT = "dialogagent",
    DIFFERENTIATION = "differentiation",
    LEARNING_ACTIVITY_CUSTOMISATION = "learningactivitycustomisation",
}

/**
 * Each log type represents a different way that log data should be represented
 * @see /library/Schoolbox/Editor/OpenAI/Log/LogType.php
 */
export enum AiLogType {
    AGENT_CHAT = "agent_chat",
}

type AiLogTypeDataMap = {
    [AiLogType.AGENT_CHAT]: AiLogAgentChatData;
};

export type AiLogFeatureData<TInputText, TOutputText> = {
    request: TInputText;
    response: TOutputText;
};

/** @see /library/Schoolbox/Editor/OpenAI/Log/Translator/Session/ModelToArray.php */
export type AiSessionLog = {
    id: number;
    sessionId: AiSessionId;
    createdAt: string;
    createdBy: UserAuthor;
    feature: AiFeature;
} & ({ [K in AiLogType]: { logType: K; data: AiLogTypeDataMap[K] } }[AiLogType] | { logType: null; data: null });

/** @see /library/Schoolbox/Editor/OpenAI/Log/Translator/ModelToArray.php */
export type AiSession<TLogType extends AiLogType = AiLogType, TFeature extends AiFeature = AiFeature> = {
    /** The session ID can be null for legacy records which never had a session ID before we added those */
    id: AiSessionId | null;
    feature: TFeature;
    logType: TLogType | null;
    createdAt: string;
    createdBy: UserAuthor;
    returnCode: number | null; // HTTP return code from OpenAI API
    _routes: {
        getLog?: string | null;
    };
};

export type AiUsageLogApiRequest = {
    keyword: string | null;
    feature: string | null;
    user: number | null;
    startDate: string | null;
    endDate: string | null;
    page?: number;
    limit?: number;
};

export type AiUsageLogApiResponse = {
    data: AiSession[];
    metadata: PaginationMeta;
};

export type AiSessionLogApiResponse<
    TLogType extends AiLogType = AiLogType,
    TFeature extends AiFeature = AiFeature,
> = (AiSessionLog & { logType: TLogType; feature: TFeature })[];
