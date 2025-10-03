import { apiRequest } from "@js/util/sbx/apiUtils";

import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";

type IncludeParticipantApiResponse = {
    hasInteraction?: boolean;
};

type IncludeParticipantApiPayload = {
    includeResponse?: boolean;
};

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
