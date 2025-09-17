import { buildApiRequest } from "@js/util/sbx/apiUtils";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { StorageFile } from "@js/resource/resourceUtils";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import type { EvidenceStatus } from "@schoolboxLearningMomentEvidence/evidenceTypes";

export type CohortReportEntry = {
    id: number;
    body: string;
    blurb: string;
    showReadMore: boolean;
    files: StorageFile[];
    recipient: User;
    creator: User;
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
    _labels: {
        publish: string;
    }
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
