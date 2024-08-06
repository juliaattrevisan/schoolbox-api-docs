import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";

/**
 * The mark type data linked to the assessment, currently an
 * object set up specifically for quiz vertical/horizontal marking
 */
export type AssessmentMarkData = {
    type: {
        name: string; // the proper name of the mark type
        slug: string; // the slug of the mark type
    };
    levels?: LetterGradeItem[];
    submissionStates: {
        // the submission states that can be used, set at: https://vagrant.dev.schoolbox.cloud/adminv2/lists/submission-errors
        code: string;
        displayAs: string;
        normaliseAs: number | null;
    };
};
