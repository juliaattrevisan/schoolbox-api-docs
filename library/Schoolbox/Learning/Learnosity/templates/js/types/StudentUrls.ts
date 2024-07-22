/**
 * The urls to be used for the student when updating score, comment, or attempt comment
 * and also the link to the teacher feedback (direct student attempt link)
 */
export type StudentUrls = {
    studentId: number;
    postFeedbackScore: string; // the url to update the individual score of an question
    postFeedbackComment: string; // the url to update the individual comment of an question
    postFeedbackAttemptComment: string; // the url to update the overall attempt comment
    postFeedbackAttemptScore: string; // the url to update the overall attempt score (to override the calculated score, with a new mark, or 'Absent' for example)
    getFeedback: string; // the url to view an individual student attempt, used within horizontal marking
    postFinaliseAttempt: string; // to confirm the attempt is finalised
};
