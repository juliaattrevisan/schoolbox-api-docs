/**
 * The urls to be used for the student when updating score, comment, or attempt comment
 * and also the link to the teacher feedback (direct student attempt link)
 */
export type StudentUrls = {
    studentId: number;
    score: string; // the url to update the individual score of an item
    comment: string; // the url to update the individual comment of an item
    attemptComment: string; // the url to update the overall attempt comment
    attemptScore: string; // the url to update the overall attempt score (to override the calculated score, with a new mark, or 'Absent' for example)
    teacherFeedback: string;
};
