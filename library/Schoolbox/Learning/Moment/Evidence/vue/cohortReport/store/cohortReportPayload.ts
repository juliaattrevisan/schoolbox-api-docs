import { apiRequest } from "@js/util/sbx/apiUtils";
import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";
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
        firstname: string;
        lastname: string;
        role: string;
    };
    creator: {
        id: number;
        externalId: string | null;
        fullname: string;
        firstname: string;
        lastname: string;
    };
    createdAt: {
        timestamp: number;
        displayAs: string;
    };
    createdBy: {
        id: number;
        externalId: string | null;
        fullname: string;
        firstname: string;
        lastname: string;
    } | null;
    updatedAt: {
        timestamp: number;
        displayAs: string;
    };
    hasComments: boolean;
    currentState: EvidenceStatus;
    _links: {
        submit: null;
        publish: string | null;
        reject: string | null;
        modifyForm: string | null;
        delete: string | null;
    };
};

export type CohortReportApiResponse = {
    status: 200;
    content: CohortReportEntry[];
};

/**
 * Fetch the cohort report data from the API
 * TODO: Add date range
 * @param url - The endpoint to fetch
 * @returns The response from the API
 */
export async function getCohortReport(url: string): Promise<ApiBaseResponse<CohortReportApiResponse>> {
    return apiRequest<CohortReportApiResponse>({
        url: `${url}/2020-01-01/2025-12-31`,
        method: "GET",
    });
}
