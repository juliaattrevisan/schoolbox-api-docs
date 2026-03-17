/**
 * A single editable skin configuration option
 */
export type SkinConfigOption = {
    id: number;
    name: string;
    value: string | null;
    title: string | null;
    description: string | null;
    editable: boolean;
    hidden: boolean;
};

/**
 * A skin config subcategory containing a list of options
 */
export type SkinConfigSubcategory = {
    name: string;
    options: SkinConfigOption[];
};

/**
 * The expected payload for the Skin Config app
 */
export type SkinConfigData = Record<string, SkinConfigSubcategory[]>;
