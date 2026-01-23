import type { User } from "@schoolboxLibrary/types/User";
import type { Metadata } from "@js/util/sbx/apiUtils";

/**
 * Routes for the Pastoral
 */
export type Routes = {
    getStudentRecord: string; // to get a specific pastoral record for a student
    getReportTags?: string; // to only display the selected tag within the report
};

/**
 * Configuration object for pastoral settings
 */
export type Config = {
    pastoralPointEnabled: boolean;
    pastoralActionEnable: boolean;
    pastoralSeverity: boolean;
};

/**
 * Pastoral event severity
 */
type Severity = {
    id: number;
    name: string;
    color: string;
};

/**
 * Severity colors configuration
 */
export type SeverityColors = Record<number, { back: string }>;

/**
 * Pastoral event type
 */
type PastoralType = {
    name: string;
};

/**
 * Pastoral event subtype
 */
type PastoralSubtype = {
    name?: string;
};

/**
 * Date type with short format, the only date used for now
 */
type PastoralDate = {
    short: string;
};

/**
 * Pastoral tag
 */
type PastoralTag = {
    id: number;
    name: string;
};

/**
 * Pastoral comment
 */
type PastoralComment = {
    owner: User;
    createdAt: string;
    body: string;
    private: boolean;
};

/**
 * Pastoral attachment
 */
type PastoralAttachment = {
    id: number;
    realname: string;
    route: string;
    storage: {
        filename: string;
        mime: string;
        hash: string;
        _links: {
            fetch: string;
            icon: string;
            image?: string;
            stream?: string;
            poster?: string;
        };
    };
};

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
 * Main pastoral record type
 */
export type PastoralRecord = {
    id: number;
    student: User;
    heading: string;
    isArchived: boolean;
    expiresAt?: string;
    severity?: Severity;
    points?: number | null;
    type: PastoralType;
    subType?: PastoralSubtype;
    owner: User;
    createdAt: PastoralDate;
    uncompletedActionCount: number;
    bodyPublic?: string;
    tags: PastoralTag[];
    comments: PastoralComment[];
    attachmentsPublic?: PastoralAttachment[];
};
