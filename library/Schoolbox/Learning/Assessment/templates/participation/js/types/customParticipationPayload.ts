import { apiRequest } from "@js/util/sbx/apiUtils";

import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";

type IncludeParticipantApiResponse = {
    hasInteraction?: boolean;
};

type IncludeParticipantApiPayload = {
    includeResponse?: boolean;
};

/**
 * Include a participant in an activity
 * @param postIncludeUrl participant._links.include
 * @param body request body
 * @returns response
 */
export function postIncludeParticipant(
    postIncludeUrl: string,
    body: IncludeParticipantApiPayload,
): Promise<ApiBaseResponse<IncludeParticipantApiResponse>> {
    return apiRequest<IncludeParticipantApiResponse, IncludeParticipantApiPayload>({
        url: postIncludeUrl,
        method: "POST",
        body,
    });
}

type IncludeExistingParticipantApiResponse = {
    hasInteraction?: { [id: number]: boolean };
};

type IncludeExistingParticipantApiPayload = {
    students: number[];
    assessmentId?: number;
    planId?: number;
    includeResponse?: boolean;
};

/**
 * Include participants into a customised activity
 * @param postIncludeExistingUrl props.includeInExistingCustomisation
 * @param body request body
 * @returns response
 */
export function postIncludeExistingParticipant(
    postIncludeExistingUrl: string,
    body: IncludeExistingParticipantApiPayload,
): Promise<ApiBaseResponse<IncludeExistingParticipantApiResponse>> {
    return apiRequest<IncludeExistingParticipantApiResponse, IncludeExistingParticipantApiPayload>({
        url: postIncludeExistingUrl,
        method: "POST",
        body,
    });
}
