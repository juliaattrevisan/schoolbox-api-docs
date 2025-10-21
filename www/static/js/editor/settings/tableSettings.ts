import type { TableConfig } from "ckeditor5";
import { tableBorderColourSwatches, tableBackgroundColourSwatches } from "./colourSettings";

/**
 * Returns the settings for the tables
 * @returns - the table settings
 */
export function getTableSettings(): { table: TableConfig } {
    return {
        table: {
            // the table controls provided: https://ckeditor.com/docs/ckeditor5/latest/features/table.html
            contentToolbar: ["tableColumn", "tableRow", "mergeTableCells", "tableProperties", "tableCellProperties"],
            /**
             * The .defaultProperties match those found in www/static/css/core/components/editor/custom-styles.scss
             * and are applied when a new table is created. When different styles are applied to a table, these
             * default styles are not applied, so the table will keep the styles it has been given.
             * The defaultProperties are also applied when a table is loaded into the editor that does not have
             * any styles set on it.
             */
            tableProperties: {
                borderColors: tableBorderColourSwatches,
                backgroundColors: tableBackgroundColourSwatches,
                defaultProperties: {
                    alignment: "left",
                    borderColor: "#b3b3b3",
                    borderStyle: "double",
                    borderWidth: "1px",
                    width: "100%",
                },
            },

            tableCellProperties: {
                borderColors: tableBorderColourSwatches,
                backgroundColors: tableBackgroundColourSwatches,
                defaultProperties: {
                    borderColor: "#b3b3b3",
                    borderStyle: "solid",
                    borderWidth: "1px",
                },
            },
        },
    };
}

/**
 * When a fixed width isn't set on a table, it should be 100% width by default
 * @param sourceElementHTML - the sourceElement content as HTML
 * @returns - updated sourceElement content as HTML
 */
export function addFullWidthStyle(sourceElementHTML: HTMLElement): HTMLElement {
    const tables = sourceElementHTML.querySelectorAll("table");
    tables.forEach((table) => {
        if (!table.style.width.includes("px")) {
            table.style.width = "100%";
        }
    });
    return sourceElementHTML;
}

/**
 * CKEditor4 tables use the now deprecated align attribute,
 * so we need to add the float style to the table as an alternative
 * @param sourceElementHTML - the sourceElement content as HTML
 * @returns -
 */
export function addAlignLeftStyle(sourceElementHTML: HTMLElement): HTMLElement {
    const tables = sourceElementHTML.querySelectorAll("table");
    tables.forEach((table) => {
        if (table.align === "" && table.parentElement && !table.parentElement.hasAttribute("data-is-ckeditor5-table")) {
            table.style.float = "left";
        } else if (
            table.align !== "" &&
            table.parentElement &&
            !table.parentElement.hasAttribute("data-is-ckeditor5-table")
        ) {
            table.style.float = table.align;
        }
    });
    return sourceElementHTML;
}
