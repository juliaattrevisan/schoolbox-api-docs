/**
 * Student type
 */

import type { StudentItem } from "./StudentItem";
import type { StudentAttemptResponse } from "./StudentAttemptResponse";

export type Student = {
    id: string;
    firstname: string;
    lastname: string;
    name: string;
    elementId: string;
    hasReport: boolean;
    isReportLoaded: boolean;
    isExpanded: boolean;
    items: StudentItem[];
    attemptResponse: StudentAttemptResponse;
};
