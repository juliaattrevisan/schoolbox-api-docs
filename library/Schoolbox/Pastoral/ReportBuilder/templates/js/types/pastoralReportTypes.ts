import type { XrangePointOptionsObject } from "highcharts";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";

/**
 * Pastoral report tab types
 */
export enum TabType {
    LIST = "list",
    TABLE = "table",
}

/**
 * Pastoral report tab titles
 */
export enum TabTitle {
    LIST = "List",
    STUDENT_BY_TYPE = "Student by Type/Sub-Type",
    TYPE_BY_MONTH = "Type/Sub-Type by Month",
    TYPE_BY_WEEK = "Type/Sub-Type by Week",
}

/**
 * Pastoral report tab type (from your Vue component)
 */
export type Tab = {
    type: TabType;
    title: TabTitle;
    key: string; // Unique key for the tab, used for routing
    _links: {
        view: string; // URL to view the tab
        export?: string; // URL to export the results of the tab
        scope?: string; // to view a specific result set within the tab
        scopeExport?: string; // to export the specific result set within the tab
    };
};

/**
 * Severity colors configuration
 */
export type SeverityColors = {
    [severityId: number]: {
        back: string;
    };
};

/**
 * Configuration object for pastoral settings
 */
export type Config = {
    pastoralPointEnabled: boolean;
    pastoralActionEnable: boolean;
};

/**
 * Routes for the Pastoral Report Builder
 */
export type Routes = {
    getStudentRecord: string; // to get a specific pastoral record for a student
    getReportTags: string; // to only display the selected tag within the report
    getIndex: string; // to the pastoral report builder
};

/**
 * Defines the type of table headers used in the Pastoral Report Table,
 * which defines what will be displayed in each column of the table.
 */
export enum TableHeaderType {
    USER = "User",
    RECORDS_AND_POINTS = "RecordsAndPoints",
    TYPE_SUB_TYPE = "TypeSubtype",
    TREND = "Trend",
}

/**
 * To define the structure of each table column
 */
export type TableHeader = {
    key: string;
    title: string;
    subTitle?: string;
    type: TableHeaderType;
    isSortable?: boolean;
};

/**
 * Displays either records or points in the table cell
 * depending on the current selection - never both at the same time
 */
export type RecordsAndPoints = { records: number; points?: never } | { records?: never; points: number | null };

/**
 * Displays the type and subtype in the table cell
 */
export type TypeSubType = {
    type: string;
    typeId: number;
    subtype: string | null;
    subtypeId: number | null;
};

/**
 * Displays a trend line in the table cell
 */
export type Trend = {
    line: XrangePointOptionsObject[];
    tooltips: string[];
};

/**
 * The options for a row in the Pastoral Report Table.
 */
export type RowOptions = User | RecordsAndPoints | TypeSubType | Trend;

/**
 * The expected payload for a Pastoral Report Table.
 */
export type TablePayload = {
    data: {
        tableHeaders: TableHeader[];
        totalRow: { [key: string]: RowOptions };
        rows: { [key: string]: RowOptions }[];
    };
    metadata: Metadata;
};

/**
 * Params available to the tab._links.view URL.
 */
export enum ViewUrlParam {
    SELECTED = "selected",
    SORT_BY = "sortBy",
    ORDER = "order",
    LIMIT = "limit",
    CURSOR = "cursor",
}

/**
 * The expected payload for a Pastoral records load.
 */
export type RecordsPayload = {
    data: {
        results: PastoralRecord[];
    };
    metadata: Metadata;
};

/**
 * Params available to the tab._links.scope URL.
 */
export enum ScopeUrlParam {
    COLUMN = "column",
    ROW = "row",
    CURSOR = "cursor",
}

/**
 * Keys that can be passed to identify a specific row in the table
 */
export type RowKeys = {
    "[student_id]"?: number;
    "[type][type_id]"?: number;
    "[type][subtype_id]"?: number;
};

/**
 * When a cell is selected in the Pastoral Report Table,
 * it will only contain the header when the total row is selected,
 * or both the header and rowId when a specific row is selected.
 */
export type SelectedCell = {
    header: TableHeader;
    meta: string[];
    rowKeys?: RowKeys;
};
