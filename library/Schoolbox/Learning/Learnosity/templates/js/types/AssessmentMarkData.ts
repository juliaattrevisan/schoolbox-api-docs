import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";
import type { SubmissionState } from "@schoolboxAssessment/Types/SubmissionState";
import type { MarkType } from "@schoolboxAssessment/Types/MarkType";

/**
 * The mark type data linked to the assessment, currently an
 * object set up specifically for quiz vertical/horizontal marking
 */
export type AssessmentMarkData = {
    type: {
        name: string; // the proper name of the mark type
        slug: MarkType; // the slug of the mark type
    };
    levels?: LetterGradeItem[];
    submissionStates: SubmissionState[];
};
