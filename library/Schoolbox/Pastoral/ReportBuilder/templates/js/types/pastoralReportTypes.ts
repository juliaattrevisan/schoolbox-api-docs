/**
 * To define the structure of each table column
 */
export type PastoralReportTableHeader = {
    key: string;
    title: string;
    subTitle?: string;
    columnType: string;
    isSortable?: boolean;
};

/**
 * Displays either records or points in the table cell
 */
export type RecordsAndPoints = {
    records: number;
    points: number;
};

/**
 * The options for a row in the Pastoral Report Table.
 */
export type RowOptions = string | User | RecordsAndPoints;

/**
 * The expected payload for a Pastoral Report Table.
 */
export type PastoralReportTablePayload = {
    data: {
        tableHeaders: PastoralReportTableHeader[];
        totalRow: RowOptions[];
        rows: RowOptions[][];
    };
    metadata: {
        count: number;
        cursor: {
            current: string | null;
            next: string | null;
        };
    };
};

/**
 * Base user type for students and owners
 */
export type User = {
    id: number;
    fullName: string;
};

/**
 * Student type extending User
 */
export type Student = User & {
    // Additional student-specific properties can be added here
};

/**
 * Pastoral record owner type extending User
 */
export type Owner = User & {
    // Additional owner-specific properties can be added here
};

/**
 * Date type with short format
 */
export type PastoralDate = {
    short: string;
    local: string;
};

/**
 * Pastoral event severity
 */
export type Severity = {
    id: number;
    name: string;
};

/**
 * Pastoral event type
 */
export type PastoralType = {
    name: string;
};

/**
 * Pastoral event subtype
 */
export type PastoralSubtype = {
    name?: string;
};

/**
 * Pastoral tag
 */
export type PastoralTag = {
    id: number;
    name: string;
};

/**
 * Pastoral comment
 */
export type PastoralComment = {
    owner: Owner;
    createdAt: string;
    body: string;
    private: boolean;
};

/**
 * Main pastoral record type
 */
export type PastoralRecord = {
    id: number;
    student: Student;
    heading: string;
    isArchived: boolean;
    expiresAt?: string;
    severity?: Severity;
    points?: number;
    type: PastoralType;
    subType: PastoralSubtype;
    owner: Owner;
    createdAt: PastoralDate;
    uncompletedActionCount: number;
    bodyPublic?: string;
    tags: PastoralTag[];
    comments: PastoralComment[];
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
export type PastoralConfig = {
    pastoral_point_enabled: boolean;
    pastoral_action_enable: boolean;
};

/**
 * Locale configuration
 */
export type LocaleConfig = {
    pastoral: string;
};

/**
 * Pastoral report tab type (from your Vue component)
 */
export type PastoralReportTab = {
    type: string;
    title: string;
};
