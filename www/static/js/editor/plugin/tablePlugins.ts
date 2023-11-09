/**
 * Returns the table plugins for the editor
 */

import {
    Table,
    TableToolbar,
    TableProperties,
    TableCellProperties,
    TableColumnResize,
} from "@ckeditor/ckeditor5-table";
import TableUpdates from "@js/editor/plugin/tableUpdates/TableUpdates";

/**
 * All the table plugins
 * @returns { object } - the table plugins
 */
export function getTablePlugins() {
    return [Table, TableToolbar, TableProperties, TableCellProperties, TableColumnResize, TableUpdates];
}
