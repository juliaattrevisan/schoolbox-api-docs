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
    elementId: string; // this will be the HTML element that comes from the Learnosity API that contains the report
    isReportLoaded: boolean; // whether the report has been loaded from the Learnosity API
    isExpanded?: boolean; // whether the student is expanded in the UI
    items: StudentItem[];
    attemptComment: StudentAttemptComment;
    urls?: StudentUrls;
    isMarked?: boolean; // whether all items (and all questions within each item) have been marked
    showMarked?: boolean; // whether to show the marked items
    isReviewed?: boolean; // whether the student has been reviewed by the teacher
};
