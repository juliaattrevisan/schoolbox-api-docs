import type { LetterGradeItem } from "@schoolboxAssessment/Types/LetterGradeItem";

/**
 * Calculate the letter grade of the value
 * @param {number} value -
 * @param {number} max -
 * @param {LetterGradeItem} items -
 * @returns {string} -
 */
export function getLetterGrade(value: number, max: number, items: LetterGradeItem): string {
    let percentage = parseFloat((value / max) * 100);
    if (Number.isNaN(percentage)) {
        percentage = 0;
    }
    return "A";
}
