import type { UserCC } from "@schoolboxLibrary/types/User";
import type { Header as LazyGridHeader } from "@schoolboxVue/components/grid/lazyGrid/types/lazyGridTypes";
import type { Metadata } from "@js/util/sbx/apiUtils";
import type { Nested } from "@js/util/queryUtils";

/**
 * Routes configuration
 */
export type Routes = {
    getData: string;
    genAi: string;
};

/**
 * Differentiation Profile header configuration
 */
export type MDPHeader = {
    id: number;
    key: string;
    type: HeaderType;
    name: string;
    module: string;
    title: string;
    terminology: string;
    description: string;
    is_enabled: boolean;
    sequence: number;
};

/**
 * Defines the type of  headers used,
 * which defines what will be displayed in each column.
 */
export enum HeaderType {
    USER = "User",
    ADJUSTMENT = "Adjustment",
    TEXT = "Text",
    DATE = "Date",
}

/**
 * To define the structure of each column
 */
export type Header = LazyGridHeader & {
    type: HeaderType;
};

/**
 * Adjustment type configuration
 */
export type AdjustmentType = {
    id: number;
    name: string;
};

/**
 * Contains user/AI generated text data
 */
export type AdjustmentText = string | null;

/**
 * The status options for a differentiation profile
 */
export enum ProfileStatus {
    ACTIVE = "active",
    EXPIRED = "expired",
    OUT_OF_DATE = "outOfDate",
    UNPOPULATED = "unpopulated",
}

/**
 * Differentiation profile data
 */
export type Profile = {
    id: number | null;
    student: UserCC;
    adjustment_category: AdjustmentType[] | null;
    adjustment_level: AdjustmentType[] | null;
    overview: string | null;
    interests_strengths: string | null;
    needs_challenges: string | null;
    strategies_adjustments: string | null;
    goals: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    status: ProfileStatus[];
    _routes: {
        linkedRecords?: string;
        customisations?: string;
        edit?: string;
        create?: string;
    };
};

/**
 * Type for keys that correspond to editable adjustment fields
 */
export type ProfileAdjustmentKeys = keyof Pick<Profile, "adjustment_category" | "adjustment_level">;

/**
 * Type for keys that correspond to editable text fields
 */
export type ProfileTextKeys = keyof Pick<
    Profile,
    "overview" | "interests_strengths" | "needs_challenges" | "strategies_adjustments" | "goals"
>;

/**
 * Any of the profile data that can be updated
 */
export type ProfileKeys = keyof Pick<Profile, "id" | "updated_at"> | ProfileAdjustmentKeys | ProfileTextKeys;

/**
 * The expected query parameters
 */
export type QueryParams = {
    filter_selection?: Nested;
    limit?: string;
    cursor?: string;
};

/**
 * The expected payload
 */
export type Payload = {
    data: {
        profiles: Profile[];
    };
    metadata: Metadata;
};

/**
 * Type for individual AI response item
 */
export type AiResponseItem = {
    response: string | null;
    title: string;
    key: ProfileTextKeys;
};
