/**
 * Returns the table plugins for the editor
 */

import Table from "@ckeditor/ckeditor5-table/src/table";
import TableToolbar from "@ckeditor/ckeditor5-table/src/tabletoolbar";
import TableProperties from "@ckeditor/ckeditor5-table/src/tableproperties";
import TableCellProperties from "@ckeditor/ckeditor5-table/src/tablecellproperties";
import TableColumnResize from "@ckeditor/ckeditor5-table/src/tablecolumnresize";
<<<<<<< HEAD
=======
import TableCaption from "@ckeditor/ckeditor5-table/src/tablecaption";
import TableClass from "@js/editor/plugin/tableClass/TableClass";
>>>>>>> 96aec291239 (CKEditor5: Add custom class to <table> [SA-15330])

/**
 * All the table plugins
 *
 * @returns { object } - the table plugins
 */
export function getTablePlugins() {
<<<<<<< HEAD
    return [Table, TableToolbar, TableProperties, TableCellProperties, TableColumnResize];
=======
    return [Table, TableToolbar, TableProperties, TableCellProperties, TableColumnResize, TableCaption, TableClass];
>>>>>>> 96aec291239 (CKEditor5: Add custom class to <table> [SA-15330])
}
