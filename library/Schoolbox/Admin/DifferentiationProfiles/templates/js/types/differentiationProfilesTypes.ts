/**
 *
 */
export type Field = { [key: string]: string | boolean };

/**
 *
 */
export type Module = {
    fields: {
        [key: string]: Field[];
    };
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
    headers: Header[];
};
