import type { User } from "@schoolboxLibrary/types/User";
import type { MarkbookActivity } from "./payload";

export enum MarkbookType {
    COURSE = "course",
    CLASS = "class",
    REPORTING = "reporting",
}

export enum MarkbookGridCellType {
    /** A cell showing a single mark value (most common type) */
    MARK = "mark",
    /** A cell showing a comment, currently only used on the reporting markbook */
    COMMENT = "comment",
}

export type MarkbookPopoutSelectedCellInfo = {
    classImplId: number;
    studentId: User["id"];
    activityId: MarkbookActivity["id"];
    referenceElement: HTMLTableCellElement;
    cellType: MarkbookGridCellType;
};

export type MarkbookFeedbackSelectedCellInfo = {
    classImplId: number;
    studentId: User["id"];
    activityId: MarkbookActivity["id"];
};
