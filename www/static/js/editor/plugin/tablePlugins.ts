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
 * @returns - the table plugins
 */
export function getTablePlugins(): (
    | typeof Table
    | typeof TableToolbar
    | typeof TableProperties
    | typeof TableCellProperties
    | typeof TableColumnResize
    | typeof TableUpdates
)[] {
    return [Table, TableToolbar, TableProperties, TableCellProperties, TableColumnResize, TableUpdates];
}
