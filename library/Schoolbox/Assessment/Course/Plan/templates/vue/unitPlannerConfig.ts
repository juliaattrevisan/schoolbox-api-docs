import type { UnitTemplate, UnitTemplateComponent } from "./unitPlannerTypes";

// All types of content that can be shown in a column
export enum ColumnType {
    UNIT = "unit",
    OVERVIEW = "overview",
    ACTIVITIES = "activities",
    RUBRIC_CAPABILITIES = "rubric-capabilities",
    CURRICULUM_OVERVIEW = "curriculum-overview",
}

// For linking a column type to specific configuration options for that column
// Saves if-else logic in the component. This is mainly options needed when initially iterating over the columns
interface ColumnConfig {
    label: string | null; // If null, the label must be set by some other condition
    fixedLeft: boolean; // Whether the column should be fixed to the left of the grid
}

// Configuration options for a tab
interface TabConfig {
    id: string;
    label: string;
    columnTypeIDs: ColumnType[]; // The column types, in order, to display in the tab
}

// An implemented column as given to the unit planner under a tab.
// This is the merged configuration of the column type, along width the relevant label and ID
export interface Column extends ColumnConfig {
    id: string; // Vue requires unique keys for v-for loops, but they must be the same across tabs for the same column
    type: ColumnType;
    label: string;
}

// An implemented tab as given to the unit planner, linking the tab with implemented columns to display
interface Tab extends TabConfig {
    columns: Column[];
}

// The configuration for the tabs in the unit planner. Specifies:
// - The id of the tab
// - The label of the tab
// - The column types to display in the tab
const tabConfig: TabConfig[] = [
    { id: "all", label: "All", columnTypeIDs: Object.values(ColumnType) },
    { id: "overview", label: "Overview", columnTypeIDs: [ColumnType.UNIT, ColumnType.OVERVIEW] },
    { id: "activities", label: "Learning Activities", columnTypeIDs: [ColumnType.UNIT, ColumnType.ACTIVITIES] },
    {
        id: "rubric-capabilities",
        label: "Rubric Capabilities",
        columnTypeIDs: [ColumnType.UNIT, ColumnType.RUBRIC_CAPABILITIES],
    },
    {
        id: "curriculum-overview",
        label: "Curriculum Overview",
        columnTypeIDs: [ColumnType.UNIT, ColumnType.CURRICULUM_OVERVIEW],
    },
];

/**
 * If there are no textboxes or curriculum components assigned to the unit template,
 * remove the relevant tabs
 * @param {TabConfig[]} $tabConfig - the current tab config
 * @param {UnitTemplate} template - The template for the unit
 * @returns {TabConfig[]} - The modified tab config
 */
function modifyTabConfig($tabConfig: TabConfig[], template: UnitTemplate): TabConfig[] {
    let modifiedTabConfig = $tabConfig;
    if (template.textboxes.length === 0) {
        // remove the overview column from the all tab
        const allTab = modifiedTabConfig.find((tab) => tab.id === "all") as TabConfig;
        allTab.columnTypeIDs = allTab?.columnTypeIDs.filter((columnType) => columnType !== ColumnType.OVERVIEW);
        // as well as the entire overview tab
        modifiedTabConfig = tabConfig.filter((tab) => tab.id !== "overview");
    }
    if (template.curriculum.length === 0) {
        // remove the curriculum overview column from the all tab
        const allTab = modifiedTabConfig.find((tab) => tab.id === "all") as TabConfig;
        allTab.columnTypeIDs = allTab?.columnTypeIDs.filter(
            (columnType) => columnType !== ColumnType.CURRICULUM_OVERVIEW,
        );
        // as well as the entire curriculum overview tab
        modifiedTabConfig = tabConfig.filter((tab) => tab.id !== "curriculum-overview");
    }
    return modifiedTabConfig;
}

// The configuration for the columns in the unit planner. Specifies:
// - The label of the column
// - Whether the column should be fixed to the left of the table
const columnConfig: Record<ColumnType, ColumnConfig> = {
    [ColumnType.UNIT]: { label: "Unit", fixedLeft: true },
    [ColumnType.OVERVIEW]: { label: null, fixedLeft: false }, // The label is set by the textbox keys
    [ColumnType.ACTIVITIES]: { label: "Learning Activities", fixedLeft: false },
    [ColumnType.RUBRIC_CAPABILITIES]: { label: "Rubric Capabilities", fixedLeft: false },
    [ColumnType.CURRICULUM_OVERVIEW]: { label: "Curriculum Overview", fixedLeft: false },
};

/**
 * If there are no textboxes or curriculum components assigned to the unit template,
 * remove the relevant columns
 * @param {TabConfig[]} $columnConfig - the current column config
 * @param {UnitTemplate} template - The template for the unit
 * @returns {Record<ColumnType, ColumnConfig>} - The modified column config
 */
function modifyColumnConfig(
    $columnConfig: Record<ColumnType, ColumnConfig>,
    template: UnitTemplate,
): Record<ColumnType, ColumnConfig> {
    const modifiedColumnConfig = { ...$columnConfig };
    if (template.textboxes.length === 0) {
        delete modifiedColumnConfig[ColumnType.OVERVIEW as keyof typeof modifiedColumnConfig];
    }
    if (template.curriculum.length === 0) {
        delete modifiedColumnConfig[ColumnType.CURRICULUM_OVERVIEW as keyof typeof modifiedColumnConfig];
    }
    return modifiedColumnConfig;
}

/**
 * Get all columns for a particular Column Type
 * For example a unit template could have multiple textboxes, so we need to add a column for each
 * @param {Tab["columns"]} tabColumns - The columns for the tab
 * @param {ColumnConfig} newColumnBase - The base configuration for the new columns
 * @param {ColumnType} columnTypeID - The type of column
 * @param {string[]} components - The heading to use for each column
 */
function getColumns(
    tabColumns: Tab["columns"],
    newColumnBase: ColumnConfig,
    columnTypeID: ColumnType,
    components: UnitTemplateComponent[],
) {
    let columnId = 0;
    components.forEach((component) => {
        columnId += 1;
        tabColumns.push({
            ...newColumnBase,
            id: `${columnTypeID}-${columnId}`,
            type: columnTypeID, // Add the missing 'type' property
            label: component.heading,
        });
    });
}

/**
 * Create the tabs for the unit planner
 * @param {UnitTemplate[]} template - The template for the unit
 * @returns {Tab[]} - The tabs for the unit planner
 */
export function createTabs(template: UnitTemplate) {
    const modifiedTabConfig = modifyTabConfig(tabConfig, template);
    const modifiedColumnConfig = modifyColumnConfig(columnConfig, template);

    return modifiedTabConfig.map((tabInfo) => {
        const tabColumns: Tab["columns"] = [];
        tabInfo.columnTypeIDs.forEach((columnTypeID) => {
            const newColumnBase = {
                ...modifiedColumnConfig[columnTypeID],
                id: `${columnTypeID}-1`,
                type: columnTypeID,
                label: modifiedColumnConfig[columnTypeID].label || columnTypeID,
            };

            if (columnTypeID === ColumnType.OVERVIEW) {
                getColumns(tabColumns, newColumnBase, columnTypeID, template.textboxes);
                return;
            }
            if (columnTypeID === ColumnType.CURRICULUM_OVERVIEW) {
                getColumns(tabColumns, newColumnBase, columnTypeID, template.curriculum);
                return;
            }
            tabColumns.push(newColumnBase);
        });
        return {
            ...tabInfo,
            columns: tabColumns,
        };
    });
}
