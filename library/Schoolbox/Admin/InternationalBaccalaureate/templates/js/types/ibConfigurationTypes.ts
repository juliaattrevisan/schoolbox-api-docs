/**
 * A configuration category for IB DP subjects.
 */
export type IBCategory = {
    id: number;
    name: string;
    title: string;
};

/**
 * Configuration data for an IB category.
 */
export type IBConfiguration = {
    id: number;
    categoryId: number;
    key: string;
    value: string;
    description?: string;
    type: string;
    label: string;
};