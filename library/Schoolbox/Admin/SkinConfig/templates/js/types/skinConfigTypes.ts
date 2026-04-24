/**
 * Routes used by the Skin Config app
 */
export type Routes = {
    post: string;
    preview: string;
};

export type SkinConfigSelectOption = {
    name: string;
    value: string;
};

/**
 * A single editable skin configuration option
 */
export type SkinConfigOption = {
    id: number;
    name: string;
    value: string | null;
    title: string | null;
    description: string | null;
    customProperty: string | null;
    contrast: string | null;
    type: "checkbox" | "colour" | "select" | "textarea";
    selectOptions?: SkinConfigSelectOption[];
    editable: boolean;
    hidden: boolean;
};

export type SkinConfigOptionErrorMap = Partial<Record<number, string>>;

export type SkinConfigContrastToken = {
    label: string;
    value: string;
};

/**
 * A skin config subcategory containing a list of options
 */
export type SkinConfigSubcategory = {
    name: string | null;
    options: SkinConfigOption[];
};

/**
 * Config section groups keyed by display category.
 */
export type SkinConfigSection = Record<string, SkinConfigSubcategory[]>;

/**
 * Toggle metadata for enabling the advanced user interface.
 */
export type SkinConfigToggle = {
    enabled: boolean;
    title: string | null;
    description: string | null;
};

/**
 * The expected payload for the Skin Config app
 */
export type SkinConfigData = {
    contrastTokens: Record<string, SkinConfigContrastToken>;
    toggle: SkinConfigToggle;
    existing: SkinConfigSection;
    sonar: SkinConfigSection;
};
