/**
 * To return the options for the vue-tables-2 component linked
 * specifically to the <data-table> component implementation
 */

import getDefaultOptions from "@schoolboxVue/components/vueTables2/getDefaultOptions";
import type { ColumnConfig } from "./types/dataTableTypes";

/**
 * to return an object where each heading is the key and the value is the class
 * @param {ColumnConfig[]} columnConfig - the configuration for the columns
 * @returns {object} the columns that have custom classes linked
 */
function getColumnClasses(columnConfig: ColumnConfig[]) {
    const columnClasses = columnConfig.reduce((accumulator: object, column: ColumnConfig) => {
        accumulator[column.key] = column.class;
        return accumulator;
    }, {});
    return { columnClasses };
}

/**
 * to return an object where each heading is the key and the value is the data
 * @param {ColumnConfig[]} columnConfig - the configuration for the columns
 * @returns {object} the columns that have custom classes linked
 */
function getHeadings(columnConfig: ColumnConfig[]) {
    const headings = columnConfig.reduce((accumulator: object, column: ColumnConfig) => {
        accumulator[column.key] = column.heading;
        return accumulator;
    }, {});
    return { headings };
}

/**
 * its assumed all columns are sortable unless isSortable is set to false
 * @param {ColumnConfig[]} columnConfig - the configuration for the columns
 * @returns {string[]} the columns that are sortable
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
 *
 * @param {ColumnConfig} columnConfig - the configuration for the columns
 * @param {object} tableOptions - the options passed to the vue-tables-2 component
 * @returns {object} the configuration for the vue-tables-2 component
 */
export function getOptions(columnConfig: ColumnConfig[], tableOptions: object) {
    return {
        ...getDefaultOptions(),
        ...getColumnClasses(columnConfig),
        ...getHeadings(columnConfig),
        ...tableOptions,
        ...getSortable(columnConfig),
    };
}
