import { apiRequest } from "@js/util/sbx/apiUtils";

import type { ApiBaseResponse } from "@js/util/sbx/apiUtils";
import type { Assessable, CustomisedPlan, Participant, Plan, User } from "./customParticipationTypes";


type includeParticipantApiResponse = {
    hasInteraction?: boolean;
}

type includeParticipantApiPayload = {
    includeResponse?: boolean;
}

export function postIncludeParticipant(
    postIncludeUrl: string,
    body: includeParticipantApiPayload,
): Promise<ApiBaseResponse<includeParticipantApiResponse>> {
    return apiRequest<includeParticipantApiResponse, includeParticipantApiPayload>({
        url: postIncludeUrl,
        method: "POST",
        body,
    });
}
