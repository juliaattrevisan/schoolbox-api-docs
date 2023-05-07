/**
 * Returns the settings for the tables
 *
 * @returns { object } - the table settings
 */
export function getTableSettings() {
    return {
        table: {
            // the table controls provided: https://ckeditor.com/docs/ckeditor5/latest/features/table.html
            contentToolbar: ["tableColumn", "tableRow", "mergeTableCells", "tableProperties", "tableCellProperties"],
        },
    };
}

/**
 * When a fixed width isn't set on a table, it should be 100% width by default
 *
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - updated sourceElement content as HTML
 */
export function addFullWidthStyle(sourceElementHTML: HTMLElement) {
    const tables = sourceElementHTML.querySelectorAll("table");
    tables.forEach((table) => {
        if (!table.style.width.includes("px")) {
            table.style.width = "100%";
        }
    });
    return sourceElementHTML;
}

/**
 * When the table isn't aligned, it should be left aligned by default
 *
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } -
 */
export function addAlignLeftStyle(sourceElementHTML: HTMLElement) {
    const tables = sourceElementHTML.querySelectorAll("table");
    tables.forEach((table) => {
        if (table.style.float === "") {
            table.style.float = "left";
        }
        // CKEditor4 tables use the now deprecated align attribute
        if (table.align !== "") {
            table.style.float = table.align;
        }
        // CKEditor5 tables use the parent figure element to align the table
        if (
            table.parentNode &&
            table.parentNode.nodeName.toLowerCase() === "figure" &&
            (table.parentNode as HTMLElement).style.float === ""
        ) {
            (table.parentNode as HTMLElement).style.float = "left";
        }
    });
    return sourceElementHTML;
}
