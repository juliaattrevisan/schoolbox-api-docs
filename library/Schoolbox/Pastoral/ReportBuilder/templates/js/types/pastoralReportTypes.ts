import type { User } from "@schoolboxLibrary/types/User";
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
 * Pastoral report tab type (from your Vue component)
 */
export type Tab = {
    type: TabType;
    title: string;
    key: string; // Unique key for the tab, used for routing
    endpoint: string;
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
    getStudentRecord: string;
    getReportTags: string;
    getIndex: string;
    getReportResultsExport: string;
};

/**
 * Defines the type of table headers used in the Pastoral Report Table,
 * which defines what will be displayed in each column of the table.
 */
export enum TableHeaderType {
    USER = "User",
    RECORDS_AND_POINTS = "RecordsAndPoints",
}

/**
 * To define the structure of each table column
 */
export type TableHeader = {
    key: string;
    title: string;
    subTitle?: string;
    type: string | TableHeaderType;
    isSortable?: boolean;
};

/**
 * Displays either records or points in the table cell
 * depending on the current selection - never both at the same time
 */
export type RecordsAndPoints = { records: number; points?: never } | { records?: never; points: number | null };

/**
 * The options for a row in the Pastoral Report Table.
 */
export type RowOptions = string | User | RecordsAndPoints;

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
 * The expected payload for a Pastoral records load.
 */
export type RecordsPayload = {
    data: {
        records: PastoralRecord[];
    };
    metadata: Metadata;
};
