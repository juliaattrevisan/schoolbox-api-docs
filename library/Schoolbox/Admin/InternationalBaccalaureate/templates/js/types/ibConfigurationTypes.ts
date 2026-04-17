/**
 * A configuration category for IB DP subjects.
 */
export type IBCategory = {
    id: number;
    name: string;
    title: string;
};

/**
 * A single configuration entry for an IB DP subject category.
 */
export type IBConfiguration = {
    id: number;
    key: string;
    value: string;
    categoryId: number;
    label: string;
    description?: string;
    type: string;
};

export type IBLanguages = {
    id: number;
    code: string;
    name: string;
};
