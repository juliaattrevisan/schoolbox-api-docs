/**
 * Returns the table plugins for the editor
 */

import Table from "@ckeditor/ckeditor5-table/src/table";
import TableToolbar from "@ckeditor/ckeditor5-table/src/tabletoolbar";
import TableProperties from "@ckeditor/ckeditor5-table/src/tableproperties";
import TableCellProperties from "@ckeditor/ckeditor5-table/src/tablecellproperties";
import TableColumnResize from "@ckeditor/ckeditor5-table/src/tablecolumnresize";
import TableClass from "@js/editor/plugin/tableClass/TableClass";

/**
 * All the table plugins
 *
 * @returns { object } - the table plugins
 */
export function getTablePlugins() {
    return [Table, TableToolbar, TableProperties, TableCellProperties, TableColumnResize, TableClass];
}
