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
