/**
 * Student type
 */

import type { StudentItem } from "./StudentItem";
import type { StudentAttemptComment } from "./StudentAttemptComment";
import type { StudentUrls } from "./StudentUrls";

export type Student = {
    id: number;
    firstname: string;
    lastname: string;
    name: string;
    elementId: string;
    hasReport: boolean;
    isReportLoaded: boolean;
    isExpanded: boolean;
    hasFocus: boolean; // to indicate if the student has focus
    items: StudentItem[];
    attemptResponse: StudentAttemptComment;
    urls: StudentUrls;
    // currentTotalScore?: number; // the total score of the item
    // maxTotalScore?: number; // the total max score of the item
};
