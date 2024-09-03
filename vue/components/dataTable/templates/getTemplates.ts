/**
 * To return any templates used for the columns
 */

import type { ColumnConfig, TemplateConfig, ImageComponentData, LinkComponentData } from "../types/dataTableTypes";

/**
 * return all the templates used for any columns
 * @param columnConfig - the configuration for the columns
 * @returns the templates for the columns
 */
export function getTemplates(columnConfig: ColumnConfig[]): TemplateConfig[] {
    const justTemplates = columnConfig.filter((column) => column.template);
    const templates = justTemplates.map((column) => {
        return {
            key: column.key,
            component: column.template?.component as string,
            componentData: column.template?.componentData as ImageComponentData | LinkComponentData,
        };
    });
    return templates;
}
