/**
 * The different mark types that can currently be set on an assessment
 */

export const MARK_TYPE_TOTAL_MARK = "raw";
export const MARK_TYPE_PERCENTAGE = "percent";
export const MARK_TYPE_LETTER_GRADE = "letter";
export const MARK_TYPE_NONE = "none";

export type MarkType =
    | typeof MARK_TYPE_TOTAL_MARK
    | typeof MARK_TYPE_PERCENTAGE
    | typeof MARK_TYPE_LETTER_GRADE
    | typeof MARK_TYPE_NONE;
