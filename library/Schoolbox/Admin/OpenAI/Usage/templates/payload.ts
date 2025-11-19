/**
 * Types for the AI usage log admin API
 * @see /library/Schoolbox/Admin/OpenAI/Usage/Controller.php
 */
import type { PaginationMeta } from "@js/util/sbx/apiUtils";
import type { AiLogAgentChatData } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { UserAuthor } from "@schoolboxLibrary/types/User";

type AiSessionId = string;

export enum AiFeature {
    CHAT = "chat",
    LESSON_PLAN = "lessonplan",
    IMAGE = "image",
    DIALOG_AGENT = "dialogagent",
    DIFFERENTIATION = "differentiation",
}

export type AiLogFeatureData<TInputText, TOutputText> = {
    request: TInputText;
    response: TOutputText;
};

type AiLogFeatureDataMap = {
    [AiFeature.CHAT]: null;
    [AiFeature.LESSON_PLAN]: null;
    [AiFeature.IMAGE]: AiLogAgentChatData;
    [AiFeature.DIALOG_AGENT]: null; // TODO define when implemented
    [AiFeature.DIFFERENTIATION]: null;
};

export type AiSessionLog = {
    [K in AiFeature]: {
        id: number;
        sessionId: AiSessionId;
        createdAt: string;
        createdBy: UserAuthor;
        feature: K;
        data: AiLogFeatureDataMap[K];
    };
}[AiFeature];

export type AiSession = {
    id: AiSessionId;
    feature: string;
    sessionId: AiSessionId;
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

export type AiSessionLogApiResponse = AiSessionLog[];
