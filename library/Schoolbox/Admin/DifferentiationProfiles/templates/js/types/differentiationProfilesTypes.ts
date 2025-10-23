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
};

/**
 * A differentiation profile module
 */
export type Module = {
    fields: Field[];
    isSortable?: boolean;
};

/**
 * Whether the settings are enabled
 */
export type Toggle = {
    enabled: boolean;
    required: boolean;
    title: string;
    description: string;
};

/**
 * To identify a column group
 */
export type Header = {
    title?: string;
    maxLength?: number;
    type: "text" | "toggle";
    colSpan: number;
    key: string;
};

/**
 * The expected payload for Differentiation Profiles
 */
export type Data = {
    featureToggle: Toggle;
    modules: Module[];
    headers: { [key: string]: Header };
};
