import type { User } from "@schoolboxLibrary/types/User";

/**
 * Pastoral event severity
 */
type Severity = {
    id: number;
    name: string;
};

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
 * Date type with short format
 */
type PastoralDate = {
    short: string;
    local: string;
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
 * Main pastoral record type
 */
export type PastoralRecord = {
    id: number;
    student: User;
    heading: string;
    isArchived: boolean;
    expiresAt?: string;
    severity?: Severity;
    points?: number;
    type: PastoralType;
    subType: PastoralSubtype;
    owner: User;
    createdAt: PastoralDate;
    uncompletedActionCount: number;
    bodyPublic?: string;
    tags: PastoralTag[];
    comments: PastoralComment[];
};
