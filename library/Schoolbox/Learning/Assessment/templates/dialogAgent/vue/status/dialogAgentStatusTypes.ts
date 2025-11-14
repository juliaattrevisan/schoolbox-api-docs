import type { DateRFC3339 } from "@js/util/dateUtils";
import type { UserCC } from "@schoolboxLibrary/types/User";
import { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";

export type DialogAgentScenarioCriteria = {
    id: string;
    title: string;
};

export type DialogAgentResponse = {
    status: DialogAgentResponseStatus;
    assistanceRequestedAt: DateRFC3339 | null;
    submittedAt: DateRFC3339 | null;
    _routes: {
        view?: string | null;
        sobo?: string | null;
        reopen?: string | null;
        resolved?: string | null;
    };
};

export type DialogAgentParticipant = {
    id: number;
    user: UserCC;
    response: DialogAgentResponse | null;
    feedback: Record<DialogAgentScenarioCriteria["id"], string>;
    _routes: {
        summary?: string | null;
        reset?: string | null;
    };
};

/**
 * Convenience function to retrieve a DialogAgentResponseStatus from an existing response, or fall back to INCOMPLETE if invalid/null
 * @param response The response to get the status from
 * @returns one of the enum values
 */
export function getResponseStatus(response: DialogAgentResponse | null): DialogAgentResponseStatus {
    if (!response) {
        return DialogAgentResponseStatus.INCOMPLETE;
    }

    return response.status;
}
