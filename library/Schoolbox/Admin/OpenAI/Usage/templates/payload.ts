/**
 * Types for the AI usage log admin API
 * @see /library/Schoolbox/Admin/OpenAI/Usage/Controller.php
 */
import type { UserAuthor } from "@schoolboxLibrary/types/User";

export type AiSessionEntry = {
    id: number;
    sessionId: string;
    feature: string;
    createdAt: string;
    createdBy: UserAuthor;
    // TODO: not a full type yet, need to establish what we need first
};

export type AiSession = {
    id: string;
    entries: AiSessionEntry[];
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
    cursor: string | null;
};

export type AiUsageLogApiResponse = {
    data: AiSession[];
    cursor: {
        total: number;
        cursor: number; // TODO: this isn't the normal way we do cursors...
    };
};

export type AiSessionLogApiResponse = AiSessionEntry[];
