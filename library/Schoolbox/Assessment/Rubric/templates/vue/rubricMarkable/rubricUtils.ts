import { assertUnreachable } from "@js/util/errorUtils";
import type { MarkScheme } from "@schoolboxLibrary/Learning/MarkScheme/templates/ts/markScheme";

/**
 * Turn a specified value into a percentage of the max marks for a capability or rubric total mark
 * @param value value
 * @param maxValue the max
 * @returns pecentage
 */
function getPercentage(value: string | number | null, maxValue: number): string | null {
    const floatValue = parseFloat(String(value)); // yikes.
    if (Number.isNaN(floatValue)) {
        return null;
    }

    let percentage = maxValue > 0 ? (floatValue / maxValue) * 100 : 0;

    if (percentage % 1 !== 0) {
        // only round and show 2dp when necessary
        percentage = parseFloat(percentage.toFixed(2));
    }

    return percentage.toString();
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
    if (markScheme.template === "") {
        return { value: null, humanReadableMark: null };
    }

    if (markScheme.template === "TotalMarkFractional") {
        const stringValue = value !== null ? String(value) : null;
        return {
            value: stringValue,
            humanReadableMark: `${stringValue} / ${maxValue}`,
        };
    }

    // turn whatever the value currently is into a percentage
    const percentage = getPercentage(value, maxValue ?? 0);

    if (markScheme.template === "Percentage") {
        return {
            value: percentage,
            humanReadableMark: `${percentage}%`,
        };
    }

    if (markScheme.template === "LetterGrade") {
        const grades = markScheme.context.conf;
        if (percentage === null || percentage === "") {
            return { value: null, humanReadableMark: null };
        }

        if (grades.length === 0) {
            // some strange stuff going on here from the old logic, not really sure if it matters anymore
            return { value: "", humanReadableMark: "" };
        }

        const numericValue = parseFloat(percentage); // yikes.

        // TODO: (from old logic) assumes values are always descending which might not be the case?
        // default to the last grade if nothing matches
        const defaultGrade = grades[grades.length - 1];
        const relevantGrade = grades.find((grade) => numericValue >= grade.lowerBound) ?? defaultGrade;
        return {
            value: relevantGrade.caption,
            humanReadableMark: relevantGrade.caption,
        };
    }

    return assertUnreachable(markScheme);
}
