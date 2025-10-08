import { buildApiRequest } from "@js/util/sbx/apiUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { StorageFile } from "@js/resource/resourceUtils";
import type { UserAuthor } from "@schoolboxLibrary/types/User";
import type { EvidenceStatus, LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export type CohortReportEntry = {
    id: number;
    body: string;
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
    currentState: EvidenceStatus;
    tags: {
        id: number;
        name: string;
    }[];
    _links: LearningMoment["_links"];
    _labels: LearningMoment["_labels"];
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
