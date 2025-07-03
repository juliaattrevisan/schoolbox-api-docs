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
type SubmissionErrorState = {
    id: number;
    name: string;
    normaliseAs: number;
};
export type SubmissionErrorStates = SubmissionErrorState[];

export type MarkScheme = MarkSchemeNone | MarkSchemeTotalMarkFractional | MarkSchemePercentage | MarkSchemeLetterGrade;

/**
 * Convert a human-readable mark for a particular mark scheme into its normalised value
 * This probably isn't as robust as the backend logic is, but this should be sufficient for the frontend
 * to use if it ever needs the normalised value for a mark
 * @param humanReadableMark - The human-readable mark to convert
 * @param markScheme - The mark scheme to convert the mark for
 * @param submissionErrorStates - All the states from submission_errors
 * @returns The normalised value of the mark if the mark scheme is quantitative, otherwise null
 */
export function normaliseHrMark(
    humanReadableMark: string | null,
    markScheme: MarkScheme,
    submissionErrorStates: SubmissionErrorStates,
): number | null {
    if (!humanReadableMark || markScheme.template === "") {
        return null;
    }

    /* is it any of the error states? */
    const errorState = submissionErrorStates.find((state) => state.name === humanReadableMark);
    if (errorState) {
        return errorState.normaliseAs;
    }

    if (markScheme.template === "Percentage") {
        /* regex to be the same as the backend */
        const pattern = /^\s*(-?[0-9.]+)/;

        const match = humanReadableMark.match(pattern);
        const percentage = match ? parseFloat(match[1]) : null;
        if (percentage === null || Number.isNaN(percentage)) {
            return null; // not a valid percentage... i guess just return null for now
        }

        return Math.max(0, Math.min(100, percentage)); // clamp to 0-100
    }

    if (markScheme.template === "TotalMarkFractional") {
        const denominator =
            markScheme.context.conf.totalMark; /* note, this may slightly differ for quiz, need to check */

        const pattern = /^\s*(-?[0-9.]+)/;
        const match = humanReadableMark.match(pattern);
        const numerator = match ? parseFloat(match[1]) : null;

        if (numerator === null || Number.isNaN(numerator) || denominator <= 0) {
            return null; // not a valid total mark... i guess just return null for now
        }

        return Math.max(0, Math.min(100, (numerator / denominator) * 100)); // clamp to 0-100
    }

    if (markScheme.template === "LetterGrade") {
        const gradeLevel = markScheme.context.conf.find((level) => level.caption === humanReadableMark);
        if (!gradeLevel) {
            return null; // not a valid letter grade... i guess just return null for now
        }

        return gradeLevel.rawGrade;
    }

    return ((scheme: never): null => {
        throw new Error(`Unexpected mark scheme: ${String(scheme)}`);
    })(markScheme); // ensure we handle all mark schemes
}
