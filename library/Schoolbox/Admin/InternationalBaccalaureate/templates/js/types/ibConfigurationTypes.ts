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

export type IBLanguage = {
    id: number;
    code: string;
    name: string;
};

export type IBLanguageListResponse = {
    data: IBLanguage[];
    metadata?: {
        count?: number;
        cursor?: {
            current?: string | null;
            next?: string | null;
        };
    };
};

export type IBSubject = {
    id: number;
    subject: string;
    level: string;
    languages: {
        languageId: number;
        classCode: string;
    }[];
};

export type IBRoutes = {
    searchLanguages: string | null;
};
