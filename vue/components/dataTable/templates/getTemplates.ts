import type { ColumnsConfig } from "../dataTable";

/**
 * return all the templates used for any columns
 * @param {ColumnsConfig[]} columnsConfig - the configuration for the columns
 * @returns {object} the templates for the columns
 */
export function getTemplates(columnsConfig: ColumnsConfig[]) {
    // return the templates for the columns
    const justTemplates = columnsConfig.filter((column) => column.template);
    const templates = justTemplates.map((column) => {
        return { key: column.key, ...column.template };
    });
    return templates;
}
