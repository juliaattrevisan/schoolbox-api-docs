export enum ColumnType {
    UNIT = "unit",
    OVERVIEW = "overview",
    ACTIVITIES = "activities",
    RUBRIC_CAPABILITIES = "rubric-capabilities",
    CURRICULUM_OVERVIEW = "curriculum-overview",
}

interface ColumnConfig {
    label: string | null; // If null, the label must be set by some other condition
    cellPadding: boolean;
    fixedLeft: boolean;
}
interface TabConfig {
    id: string;
    label: string;
    columnTypeIDs: ColumnType[];
}

interface Column extends ColumnConfig {
    id: number;
    type: ColumnType;
    label: string;
}

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
// - Whether cells in that column should have additional padding
// - Whether the column should be fixed to the left of the table
const columnConfig: Record<ColumnType, ColumnConfig> = {
    [ColumnType.UNIT]: { label: "Unit", cellPadding: true, fixedLeft: true },
    [ColumnType.OVERVIEW]: { label: null, cellPadding: true, fixedLeft: false }, // The label is set by the textbox keys
    [ColumnType.ACTIVITIES]: { label: "Learning Activities", cellPadding: false, fixedLeft: false },
    [ColumnType.RUBRIC_CAPABILITIES]: { label: "Rubric Capabilities", cellPadding: false, fixedLeft: false },
    [ColumnType.CURRICULUM_OVERVIEW]: { label: "Curriculum Overview", cellPadding: false, fixedLeft: false },
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
                id: tabColumns.length,
                type: columnTypeID,
                label: columnConfig[columnTypeID].label || columnTypeID,
            };

            if (columnTypeID === ColumnType.OVERVIEW) {
                allTextboxKeys.forEach((textboxKey) => {
                    tabColumns.push({
                        ...newColumnBase,
                        id: tabColumns.length,
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
