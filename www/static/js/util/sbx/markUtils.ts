import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";
import { getPercentage } from "../mathUtils";

/**
 * Calculate the letter grade of the value
 * @param {number} value - the value to calculate the letter grade of
 * @param {number} max - the maximum value
 * @param {LetterGradeItem[]} items - the letter grade items
 * @returns {string} - the letter grade or an "-" if invalid
 */
export function getLetterGrade(value: number, max: number, items: LetterGradeItem[]): string {
    const invalidValue = "-";
    const percentage = getPercentage(value, max);
    if (percentage === invalidValue) {
        return invalidValue;
    }
    // get the percentage as a number, slicing off the % sign
    const percentageNum = Number(percentage.slice(0, -1));
    // ensure the percentage is a number
    if (Number.isNaN(percentageNum)) {
        return invalidValue;
    }
    // ensure there's at least one item in the array
    if (items.length === 0) {
        return invalidValue;
    }
    // ensure all items have the required properties
    const isInvalidItem = items.some((grade) => grade.lowerBound === undefined || grade.caption === undefined);
    if (isInvalidItem) {
        return invalidValue;
    }
    // to ensure the items are sorted by lowerBound
    const sortedItems = items.sort((a: LetterGradeItem, b: LetterGradeItem) => a.lowerBound - b.lowerBound);
    // find the first item that the percentage is greater than or equal to the lowerBound
    return sortedItems.reverse().find((grade) => percentageNum >= grade.lowerBound)?.caption || invalidValue;
}
