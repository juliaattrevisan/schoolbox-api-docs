import { assertUnreachable } from "@js/util/errorUtils";
import { MarkTypeSlug } from "@schoolboxAssessment/Types/MarkType";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";

/**
 * Turn a specified value into a percentage of the max marks for a capability or rubric total mark
 * @param value value
 * @param maxValue the max
 * @returns pecentage, or null if value is not a number and can't be converted to a number
 */
export function getPercentage(value: string | number | null, maxValue: number): number | null {
    const floatValue = parseFloat(String(value)); // yikes.
    if (Number.isNaN(floatValue)) {
        return null;
    }

    let percentage = maxValue > 0 ? (floatValue / maxValue) * 100 : 0;

    if (percentage % 1 !== 0) {
        // only round and show 2dp when necessary
        percentage = parseFloat(percentage.toFixed(2));
    }

    return percentage;
}

/**
 * Determine the text form that a mark should display as based on the mark scheme
 * @param value scaled mark value
 * @param maxValue the max value for the mark (irrelevant for no mark scheme)
 * @param markScheme the mark scheme to use
 * @returns the human readable form of the mark to display
 */
export function applyMarkScheme(
    value: string | number | null,
    maxValue: number | null,
    markScheme: MarkScheme,
): {
    value: string | null;
    humanReadableMark: string | null;
} {
    if (markScheme.mode === MarkTypeSlug.NONE) {
        return { value: null, humanReadableMark: null };
    }

    if (markScheme.mode === MarkTypeSlug.TOTAL) {
        const stringValue = value !== null ? String(value) : null;
        return {
            value: stringValue,
            humanReadableMark: `${stringValue} / ${maxValue}`,
        };
    }

    // turn whatever the value currently is into a percentage
    const percentage = getPercentage(value, maxValue ?? 0);

    if (markScheme.mode === MarkTypeSlug.PERCENT) {
        return {
            value: percentage !== null ? percentage.toString() : null,
            humanReadableMark: percentage !== null ? `${percentage}%` : null,
        };
    }

    if (markScheme.mode === MarkTypeSlug.LETTER) {
        const grades = markScheme.conf;
        if (percentage === null) {
            return { value: null, humanReadableMark: null };
        }

        if (grades.length === 0) {
            // some strange stuff going on here from the old logic, not really sure if it matters anymore
            return { value: "", humanReadableMark: "" };
        }

        // TODO: (from old logic) assumes values are always descending which might not be the case?
        // default to the last grade if nothing matches
        const defaultGrade = grades[grades.length - 1];
        const relevantGrade = grades.find((grade) => percentage >= grade.lowerBound) ?? defaultGrade;
        return {
            value: relevantGrade.caption,
            humanReadableMark: relevantGrade.caption,
        };
    }

    return assertUnreachable(markScheme);
}

/**
 * Derive a scaled mark from a custom mark (essentially the inverse of applyMarkScheme)
 * @param value the custom mark value
 * @param maxValue the max value for the mark (irrelevant for no mark scheme)
 * @param markScheme the mark scheme to use
 * @returns the scaled mark value
 */
export function deriveScaledMark(
    value: string | number | null,
    maxValue: number | null,
    markScheme: MarkScheme,
): number | null {
    if (markScheme.mode === MarkTypeSlug.NONE) {
        return null;
    }

    if (markScheme.mode === MarkTypeSlug.TOTAL) {
        const floatValue = parseFloat(String(value));
        if (Number.isNaN(floatValue)) {
            return null;
        }
        return floatValue;
    }

    if (markScheme.mode === MarkTypeSlug.PERCENT) {
        const floatValue = parseFloat(String(value));
        if (Number.isNaN(floatValue)) {
            return null;
        }

        return (floatValue * (maxValue ?? 0)) / 100;
    }

    if (markScheme.mode === MarkTypeSlug.LETTER) {
        const grades = markScheme.conf;
        const relevantGrade = grades.find((grade) => grade.caption === value);
        if (!relevantGrade) {
            return null;
        }

        return (relevantGrade.rawGrade * (maxValue ?? 0)) / 100;
    }

    return assertUnreachable(markScheme);
}
