/**
 * The submission states that can be used to override the mark on an assessment,
 * set at: https://vagrant.dev.schoolbox.cloud/adminv2/lists/submission-errors
 */
export type SubmissionState = {
    code: string;
    displayAs: string;
    normaliseAs: number | null;
};
