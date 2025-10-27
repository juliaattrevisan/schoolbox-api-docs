import type { User } from "@schoolboxLibrary/types/User";

/**
 * Differentiation Profile header configuration
 */
export type MDPHeader = {
    id: number;
    name: string;
    module: string;
    title: string;
    terminology: string;
    description: string;
    is_enabled: boolean;
    sequence: number;
};

export type Header = {
    id: number;
    title: string;
    width: number;
    isFixed: boolean;
};

/**
 * Adjustment type configuration
 */
export type AdjustmentType = {
    id: number;
    name: string;
};

/**
 * Differentiation profile data
 */
export type Profile = {
    id: number | null;
    student: User;
    adjustment_category: unknown | null;
    adjustment_level: unknown | null;
    overview: string | null;
    interests_strengths: string | null;
    needs_challenges: string | null;
    strategies_adjustments: string | null;
    goals: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
};
