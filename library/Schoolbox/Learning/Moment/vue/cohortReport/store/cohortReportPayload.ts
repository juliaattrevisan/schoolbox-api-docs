import { buildApiRequest } from "@js/util/sbx/apiUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { StorageFile } from "@js/resource/resourceUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { EvidenceStatus, LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export type CohortReportEntry = Pick<LearningMoment, "id" | "body" | "currentState" | "tags" | "_links" | "_labels"> & {
    blurb: string;
    showReadMore: boolean;
    files: StorageFile[];
    recipient: UserAuthor;
    creator: UserAuthor;
    createdAt: {
        timestamp: number;
        displayAs: string;
    };
    updatedAt: {
        timestamp: number;
        displayAs: string;
    };
    hasComments: boolean;
};

export type CohortReportApiPayload = {
    cursor?: string | null;
    userIds?: number[];
    from?: string;
    to?: string;
    authorIds?: number[];
    folderIds?: number[];
    tagIds?: number[];
    states?: EvidenceStatus[];
};

type CohortReportApiResponse = {
    data: CohortReportEntry[];
    metadata: Metadata;
};

export const getCohortReport = buildApiRequest<CohortReportApiResponse, CohortReportApiPayload>({
    method: "GET",
    url: null, // forces the URL to be set when calling the function
});
