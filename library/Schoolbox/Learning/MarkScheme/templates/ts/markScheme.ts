type MarkSchemeAbstract = {
    template: string;
    context: {
        mode: string;
    };
};

export interface MarkSchemeNone extends MarkSchemeAbstract {
    template: "";
    context: {
        mode: "none";
    };
}

export interface MarkSchemeTotalMarkFractional extends MarkSchemeAbstract {
    template: "TotalMarkFractional";
    context: {
        mode: "raw";
        conf: {
            totalMark: number;
        };
    };
}

export interface MarkSchemePercentage extends MarkSchemeAbstract {
    template: "Percentage";
    context: {
        mode: "percent";
    };
}

export interface MarkSchemeLetterGrade extends MarkSchemeAbstract {
    template: "LetterGrade";
    context: {
        mode: "letter";
        conf: {
            lowerBound: number;
            rawGrade: number;
            caption: string;
        }[];
    };
}

/** All the states from submission_errors for use in the markScheme inputs */
export type SubmissionErrorStates = string[];

export type MarkScheme = MarkSchemeNone | MarkSchemeTotalMarkFractional | MarkSchemePercentage | MarkSchemeLetterGrade;
