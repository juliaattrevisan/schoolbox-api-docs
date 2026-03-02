/**
 * A switch toggle for enabling/disabling a feature
 */
export type Toggle = {
    enabled: boolean;
    required: boolean;
    title: string;
    description: string;
};

/**
 * A field in a differentiation profile module
 */
export type Field = {
    id: string | number;
    name: string;
    title: string;
    terminology: string | null;
    description: string | null;
    is_enabled: boolean;
    sequence: number; // The position/order of the field within its module
};

/**
 * A differentiation profile module
 */
export type Module = {
    fields: Record<string, Field>;
    isSortable?: boolean;
};

/**
 * To identify a column group and corresponding module structure
 */
export type Header = {
    title?: string;
    maxLength?: number;
    type: "text" | "checkbox";
    colSpan: number;
    key: keyof Field;
};

/**
 * The expected payload for Differentiation Profiles
 */
export type Data = {
    featureToggle: Toggle;
    modules: Record<string, Module>;
    headers: Record<string, Header>;
};
