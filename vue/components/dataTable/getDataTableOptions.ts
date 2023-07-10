/**
 *
 */

import getDefaultOptions from "@schoolboxVue/components/vueTables2/getDefaultOptions";
import type { ColumnsConfig } from "./dataTable";

/**
 * to return an object where each heading is the key and the value is the class
 * @param {ColumnsConfig[]} columnsConfig - the configuration for the columns
 * @returns {object} the columns that have custom classes linked
 */
function getColumnClasses(columnsConfig: ColumnsConfig[]) {
    const columnClasses = columnsConfig.reduce((accumulator: object, column: ColumnsConfig) => {
        accumulator[column.key] = column.class;
        return accumulator;
    }, {});
    return { columnClasses };
}

/**
 * to return an object where each heading is the key and the value is the data
 * @param {ColumnsConfig[]} columnsConfig - the configuration for the columns
 * @returns {object} the columns that have custom classes linked
 */
function getHeadings(columnsConfig: ColumnsConfig[]) {
    const headings = columnsConfig.reduce((accumulator: object, column: ColumnsConfig) => {
        accumulator[column.key] = column.heading;
        return accumulator;
    }, {});
    return { headings };
}

/**
 * its assumed all columns are sortable unless isSortable is set to false
 * @param {ColumnsConfig[]} columnsConfig - the configuration for the columns
 * @returns {string[]} the columns that are sortable
 */
function getSortable(columnsConfig: ColumnsConfig[]) {
    const sortable: string[] = [];
    columnsConfig.forEach((column) => {
        if (!Object.prototype.hasOwnProperty.call(column, "isSortable")) {
            sortable.push(column.key);
        }
    });
    return { sortable };
}

/**
 *
 * @param {ColumnsConfig} columnsConfig - the configuration for the columns
 * @param {object} tableOptions - the options passed to the vue-tables-2 component
 * @returns {object} the configuration for the vue-tables-2 component
 */
export function getOptions(columnsConfig: ColumnsConfig[], tableOptions: object) {
    return {
        ...getDefaultOptions(),
        ...getColumnClasses(columnsConfig),
        ...getHeadings(columnsConfig),
        ...tableOptions,
        ...getSortable(columnsConfig),
    };
}
