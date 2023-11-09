/**
 * A Student that has made a response
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
    items: StudentItem[];
    attemptComment: StudentAttemptComment;
    urls: StudentUrls;
    isMarked?: boolean;
};
