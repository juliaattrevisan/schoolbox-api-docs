import { buildApiRequest } from "@js/util/sbx/apiUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { StorageFile } from "@js/resource/resourceUtils";
import type { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export type CohortReportEntry = {
    id: number;
    body: string;
    blurb: string;
    showReadMore: boolean;
    files: StorageFile[];
    recipient: {
        id: number;
        externalId: string | null;
        fullname: string;
    };
    creator: {
        id: number;
        externalId: string | null;
        fullname: string;
    };
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
    _links: {
        submit: null;
        publish: string | null;
        reject: string | null;
        modifyForm: string | null;
        delete: string | null;
    };
};

type CohortReportApiPayload = {
    userIds?: number[];
    fromDate?: string;
    toDate?: string;
    authorIds?: number[];
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
