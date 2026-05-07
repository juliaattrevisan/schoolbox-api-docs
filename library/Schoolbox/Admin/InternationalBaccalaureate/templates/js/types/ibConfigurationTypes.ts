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

export type IBSubjectLanguage = {
    languageId: number;
    languageName: string | null;
    classCode: string;
    systemPurposeId: number | null;
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

export type IBClassCode = {
    id: string;
    code: string;
    name: string;
};

export type IBSubject = {
    id: number;
    subject: string;
    level: string;
    languages: IBSubjectLanguage[];
};

export type IBRoutes = {
    searchLanguages: string | null;
    searchClassCodes: string | null;
    studentCount: string | null;
};
