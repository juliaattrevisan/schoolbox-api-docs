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
    createdAt: PastoralDate;
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
    subtype: PastoralSubtype;
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
 * Main pastoral report data structure
 */
export type PastoralReportData = {
    results: PastoralRecord[];
    showSeverity?: boolean;
    severityColors: SeverityColors;
    config: PastoralConfig;
    locale: LocaleConfig;
};

/**
 * Pastoral report tab type (from your Vue component)
 */
export type PastoralReportTab = {
    type: string;
    title: string;
};
