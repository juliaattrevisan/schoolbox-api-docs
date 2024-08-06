import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";
import type { SubmissionState } from "@schoolboxAssessment/Types/SubmissionState";

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
    submissionStates: SubmissionState[];
};
