/**
 * A single subject entry with its name and associated language.
 */
export type SubjectEntry = {
    groupSubjectId: number;
    subject: string;
    language: string | null;
    languageId: number | null;
};

/**
 * A student's subjects grouped by level: Higher Level, Standard Level, and Core.
 */
export type SubjectGroup = {
    hl: SubjectEntry[];
    sl: SubjectEntry[];
    core: SubjectEntry[];
};

/**
 * Whether a student's candidate details have been confirmed or are still unconfirmed.
 */
export type CandidateDetailsStatus = "confirmed" | "unconfirmed";

/**
 * A student row used in the Preparation, Unconfirmed, and Confirmed sections.
 */
export type StudentRow = {
    id: number;
    name: string;
    userId: number;
    candidateDetailsStatus: CandidateDetailsStatus;
    subjects: SubjectGroup;
    updateLanguageEndpoint: string | null;
    renotifyEndpoint: string | null;
};

/**
 * A student row used in the Failed Registrations section, extending StudentRow with an error message.
 */
export type FailedRow = StudentRow & {
    errorMessage?: string;
};

/**
 * A student row used in the In Progress section, with an IBIS submission timestamp instead of a candidate status.
 */
export type InProgressRow = {
    id: number;
    name: string;
    ibisSubmittedAt?: string;
    subjects: SubjectGroup;
};

/**
 * A student row used in the Registered section, extending StudentRow with an IB candidate number.
 */
export type RegisteredRow = StudentRow & {
    ibCandidateNumber?: string;
};

// ---------------------------------------------------------------------------
// Raw API types — shape returned by the candidates endpoint
// ---------------------------------------------------------------------------

/**
 * Raw language entry returned per subject from the candidates API.
 */
export type ApiSubjectLanguage = {
    languageId: number | null;
    languageName: string;
    classCode: string | null;
};

/**
 * Raw subject entry returned from the candidates API.
 */
export type ApiSubject = {
    id: number;
    groupId: number;
    subject: string;
    level: string;
    languages: ApiSubjectLanguage[];
};

/**
 * Raw student entry returned from the candidates API.
 */
export type ApiStudent = {
    userId: number;
    registrationSessionStudentId: number | null;
    status: number;
    userName: string;
    hasMissingDetails: boolean;
    missingFields: string[];
    _routes: {
        updateLanguages: string | null;
    };
    ibGroupSubjects: {
        HL: ApiSubject[];
        SL: ApiSubject[];
        Core: ApiSubject[];
    };
};

/**
 * Status descriptor returned from the candidates API.
 */
export type ApiStatus = {
    value: number;
    key: string;
    label: string;
};

/**
 * Raw response from the candidates endpoint.
 */
export type ApiCandidatesResponse = {
    success: true;
    students: Record<string, ApiStudent[]>;
    statuses: ApiStatus[];
};

/**
 * Raw response from the renotify endpoint.
 */
export type ApiRenotifyResponse = {
    success: boolean;
    partial: boolean;
    message?: string;
    renotified: number[];
    failed: {
        studentId: number;
        message: string;
    }[];
};
