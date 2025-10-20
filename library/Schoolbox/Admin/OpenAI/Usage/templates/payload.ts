/**
 * Types for the AI usage log admin API
 * @see /library/Schoolbox/Admin/OpenAI/Usage/Controller.php
 */
import type { PaginationMeta } from "@js/util/sbx/apiUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";

type AiSessionId = string;

export type AiSessionLog = {
    id: number;
    sessionId: AiSessionId;
    feature: string;
    createdAt: string;
    createdBy: UserAuthor;
    // TODO: not a full type yet, need to establish what we need first
};

export type AiSession = {
    id: AiSessionId;
    feature: string;
    createdAt: string;
    createdBy: UserAuthor;
    _routes: {
        getLog?: string | null;
    };
};

export type AiUsageLogApiRequest = {
    keyword: string | null;
    feature: string | null;
    user: number | null;
    after: string | null;
    before: string | null;
    page?: number;
    limit?: number;
};

export type AiUsageLogApiResponse = {
    data: AiSession[];
    metadata: PaginationMeta;
};

export type AiSessionLogApiResponse = AiSessionLog[];
