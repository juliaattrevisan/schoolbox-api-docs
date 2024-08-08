/**
 * Represents a letter grade item, used in all grade scales
 * when assessment is set as Mark Type: Letter Grade
 */
export type LetterGradeItem = {
    lowerBound: number;
    rawGrade: number;
    caption: string;
};
