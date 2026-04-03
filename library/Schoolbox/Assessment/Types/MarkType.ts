/**
 * The different mark types that can currently be set on an assessment
 */

export enum MarkTypeSlug {
    PERCENT = "percent",
    LETTER = "letter",
    TOTAL = "raw",
    NONE = "none",
}

export type MarkType = {
    name: string; // the proper name of the mark type
    slug: MarkTypeSlug; // the slug of the mark type
};
