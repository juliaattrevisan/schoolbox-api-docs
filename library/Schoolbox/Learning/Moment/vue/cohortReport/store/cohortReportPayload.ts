import { buildApiRequest } from "@js/util/sbx/apiUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { EvidenceStatus, LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export type CohortReportEntry = LearningMoment & {
    blurb: string;
    showReadMore: boolean;
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
