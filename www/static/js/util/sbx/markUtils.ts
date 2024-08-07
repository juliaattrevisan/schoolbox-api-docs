import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";
import { getPercentage } from "../mathUtils";

/**
 * Calculate the letter grade of the value
 * @param {number} value - the value to calculate the letter grade of
 * @param {number} max - the maximum value
 * @param {LetterGradeItem[]} items - the letter grade items
 * @returns {string} - the letter grade or an "-" if invalid
 */
export function getLetterGrade(value: number, max: number, items: LetterGradeItem[]): string | undefined {
    const percentage = getPercentage(value, max);
    if (percentage === undefined) {
        return undefined;
    }
    // get the percentage as a number, slicing off the % sign
    const percentageNum = Number(percentage.slice(0, -1));
    // ensure the percentage is a number
    if (Number.isNaN(percentageNum)) {
        return undefined;
    }
    // ensure there's at least one item in the array
    if (items.length === 0) {
        return undefined;
    }
    // ensure all items have the required properties
    const isInvalidItem = items.some((grade) => grade.lowerBound === undefined || grade.caption === undefined);
    if (isInvalidItem) {
        return undefined;
    }
    // to ensure the items are sorted by lowerBound
    const sortedItems = items.sort((a: LetterGradeItem, b: LetterGradeItem) => a.lowerBound - b.lowerBound);
    // find the first item that the percentage is greater than or equal to the lowerBound
    return sortedItems.reverse().find((grade) => percentageNum >= grade.lowerBound)?.caption || undefined;
}

/**
 * Get the lower bound of a letter grade, it's assumed that the caption is within the items
 * @param {string} caption - the caption of the letter grade
 * @param {LetterGradeItem[]} items - the letter grade items
 * @returns {number | undefined} - the lower bound or undefined if not found
 */
export function getLowerBound(caption: string, items: LetterGradeItem[]) {
    // ensure the caption is a string
    if (typeof caption !== "string") {
        return undefined;
    }
    // ensure there's at least one item in the array
    if (items.length === 0) {
        return undefined;
    }
    // ensure all items have the required properties
    const isInvalidItem = items.some((grade) => grade.lowerBound === undefined || grade.caption === undefined);
    if (isInvalidItem) {
        return undefined;
    }
    return items.find((grade) => grade.caption === caption)?.lowerBound;
}
