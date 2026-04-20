import type { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";

type MarkSchemeAbstract = {
    template: string;
    context: {
        mode: MarkTypeSlug;
    };
};

export interface MarkSchemeNone extends MarkSchemeAbstract {
    template: "";
    context: {
        mode: MarkTypeSlug.NONE;
    };
}

export interface MarkSchemeTotalMarkFractional extends MarkSchemeAbstract {
    template: "TotalMarkFractional";
    context: {
        mode: MarkTypeSlug.TOTAL;
        conf: {
            totalMark: number;
        };
    };
}

export interface MarkSchemePercentage extends MarkSchemeAbstract {
    template: "Percentage";
    context: {
        mode: MarkTypeSlug.PERCENT;
    };
}

export interface MarkSchemeLetterGrade extends MarkSchemeAbstract {
    template: "LetterGrade";
    context: {
        mode: MarkTypeSlug.LETTER;
        conf: {
            lowerBound: number;
            rawGrade: number;
            caption: string;
        }[];
    };
}

/** All the states from submission_errors for use in the markScheme inputs */
type SubmissionErrorState = {
    id: number;
    name: string;
    normaliseAs: number;
};
export type SubmissionErrorStates = SubmissionErrorState[];

export type MarkScheme = MarkSchemeNone | MarkSchemeTotalMarkFractional | MarkSchemePercentage | MarkSchemeLetterGrade;
