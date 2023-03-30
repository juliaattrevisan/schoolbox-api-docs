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
