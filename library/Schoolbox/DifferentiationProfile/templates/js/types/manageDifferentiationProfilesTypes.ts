import type { User } from "@schoolboxLibrary/types/User";

/**
 * Differentiation Profile header configuration
 */
export type MDPHeader = {
    id: number;
    key: string;
    type: string;
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
export type Header = {
    id: number;
    key: string;
    type: HeaderType;
    title: string;
    width: number;
    isFixed: boolean;
    isSortable: boolean;
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
 * Differentiation profile data
 */
export type Profile = {
    id: number | null;
    student: User;
    adjustment_category: AdjustmentType[] | null;
    adjustment_level: AdjustmentType[] | null;
    overview: AdjustmentText;
    interests_strengths: AdjustmentText;
    needs_challenges: AdjustmentText;
    strategies_adjustments: AdjustmentText;
    goals: AdjustmentText;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
};
