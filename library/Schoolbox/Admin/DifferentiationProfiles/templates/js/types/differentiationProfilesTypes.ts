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
    terminology: string;
    description: string;
    is_enabled: boolean;
    [key: string]: any; // Allow dynamic property access
};

/**
 * A differentiation profile module
 */
export type Module = {
    fields: { [key: string]: Field };
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
    key: string;
};

/**
 * The expected payload for Differentiation Profiles
 */
export type Data = {
    featureToggle: Toggle;
    modules: { [key: string]: Module };
    headers: { [key: string]: Header };
};
