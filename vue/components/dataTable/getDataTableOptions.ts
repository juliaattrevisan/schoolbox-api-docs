/**
 * To return the options for the vue-tables-2 component linked
 * specifically to the <data-table> component implementation
 */

import getDefaultOptions from "@schoolboxVue/components/vueTables2/getDefaultOptions";
import type { TableOptions } from "@schoolboxVue/components/vueTables2/types/vueTables2Types";
import type { ColumnConfig } from "./types/dataTableTypes";

/**
 * to return an object where each heading is the key and the value is the class if set
 * @param {ColumnConfig[]} columnConfig - the configuration for the columns
 * @returns {TableOptions} the columns that have custom classes linked
 */
function getColumnsClasses(columnConfig: ColumnConfig[]) {
    const columnsClasses = columnConfig.reduce((accumulator: Record<string, string>, column: ColumnConfig) => {
        accumulator[column.key] = column.class ?? "";
        return accumulator;
    }, {});
    return { columnsClasses };
}

/**
 * to return an object where each heading is the key and the value is the data
 * @param {ColumnConfig[]} columnConfig - the configuration for the columns
 * @returns {TableOptions} the columns that have custom classes linked
 */
function getHeadings(columnConfig: ColumnConfig[]) {
    const headings = columnConfig.reduce((accumulator: Record<string, string>, column: ColumnConfig) => {
        accumulator[column.key] = column.heading;
        return accumulator;
    }, {});
    return { headings };
}

/**
 * its assumed all columns are sortable unless isSortable is set to false
 * @param {ColumnConfig[]} columnConfig - the configuration for the columns
 * @returns {TableOptions} the columns that are sortable
 */
function getSortable(columnConfig: ColumnConfig[]) {
    const sortable: string[] = [];
    columnConfig.forEach((column) => {
        if (!Object.prototype.hasOwnProperty.call(column, "isSortable")) {
            sortable.push(column.key);
        }
    });
    return { sortable };
}

/**
 * any hidden columns, typically used to add extra search data to the table
 * @param {ColumnConfig[]} columnConfig - the configuration for the columns
 * @returns {TableOptions} the columns that are sortable
 */
function getHiddenColumns(columnConfig: ColumnConfig[]) {
    const hiddenColumns: string[] = [];
    columnConfig.forEach((column) => {
        if (column.isHidden) {
            hiddenColumns.push(column.key);
        }
    });
    return { hiddenColumns };
}

/**
 *
 * @param {ColumnConfig} columnConfig - the configuration for the columns
 * @param {tableOptions} tableOptions - the options passed to the vue-tables-2 component
 * @returns {TableOptions} the configuration for the vue-tables-2 component
 */
export function getOptions(columnConfig: ColumnConfig[], tableOptions: TableOptions) {
    return {
        ...getDefaultOptions(),
        ...getColumnsClasses(columnConfig),
        ...getHeadings(columnConfig),
        ...getSortable(columnConfig),
        ...getHiddenColumns(columnConfig),
        ...tableOptions,
    };
}
