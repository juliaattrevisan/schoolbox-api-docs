import { apiRequest } from "@js/util/sbx/apiUtils";
import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";

export type CohortReportEntry = {
    id: number;
    // TODO
};

export type CohortReportApiResponse = {
    status: 200;
    content: Record<CohortReportEntry["id"], CohortReportEntry>;
};

/**
 * Fetch the cohort report data from the API
 * TODO: Add date range
 * @param url - The endpoint to fetch
 * @returns The response from the API
 */
export async function getCohortReport(url: string): Promise<ApiBaseResponse<CohortReportApiResponse>> {
    return apiRequest<CohortReportApiResponse>({
        url,
        method: "GET",
    });
}
