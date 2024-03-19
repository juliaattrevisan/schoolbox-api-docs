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
 * Create the tabs for the unit planner
 * @param {string[]} allTextboxKeys - The keys (labels) of all the textboxes
 * @returns {Tab[]} - The tabs for the unit planner
 */
export function createTabs(allTextboxKeys: string[]) {
    return tabConfig.map((tabInfo) => {
        const tabColumns: Tab["columns"] = [];

        tabInfo.columnTypeIDs.forEach((columnTypeID) => {
            const newColumnBase = {
                ...columnConfig[columnTypeID],
                id: `${columnTypeID}-1`,
                type: columnTypeID,
                label: columnConfig[columnTypeID].label || columnTypeID,
            };

            if (columnTypeID === ColumnType.OVERVIEW) {
                let columnId = 0;
                allTextboxKeys.forEach((textboxKey) => {
                    columnId += 1;
                    tabColumns.push({
                        ...newColumnBase,
                        id: `${columnTypeID}-${columnId}`,
                        label: textboxKey,
                    });
                });
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
