import { buildApiRequest } from "@js/util/sbx/apiUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { EvidenceStatus, LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export type CohortReportEntry = Omit<LearningMoment, "createdAt" | "updatedAt"> & {
    blurb: string;
    showReadMore: boolean;
    createdAt: {
        timestamp: number;
        displayAs: string;
        rfc: string;
    };
    updatedAt: {
        timestamp: number;
        displayAs: string;
        rfc: string;
    };
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
