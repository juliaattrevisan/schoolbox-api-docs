import type { DateRFC3339 } from "@js/util/dateUtils";
import type { UserCC } from "@schoolboxLibrary/types/User";
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";

export type DialogAgentScenarioCriteria = {
    id: string;
    title: string;
};

export type DialogAgentParticipant = {
    id: number;
    user: UserCC;
    status: DialogAgentResponseStatus;
    assistanceRequestedAt: DateRFC3339 | null;
    submittedAt: DateRFC3339 | null;
    feedback: Record<DialogAgentScenarioCriteria["id"], string>;
    _routes: {
        summary?: string | null;
        view?: string | null;
        sobo?: string | null;
        reset?: string | null;
        reopen?: string | null;
        resolved?: string | null;
    };
};
