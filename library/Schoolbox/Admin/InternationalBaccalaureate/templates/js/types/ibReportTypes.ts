/**
 * A single subject entry with its name and associated language.
 */
export type SubjectEntry = {
    subject: string;
    language: string | null;
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
    candidateDetailsStatus: CandidateDetailsStatus;
    subjects: SubjectGroup;
};

/**
 * A student row used in the Failed Registrations section, extending StudentRow with an error message.
 */
export type FailedRow = StudentRow & {
    errorMessage: string;
};

/**
 * A student row used in the In Progress section, with an IBIS submission timestamp instead of a candidate status.
 */
export type InProgressRow = {
    id: number;
    name: string;
    ibisSubmittedAt: string; // ISO 8601 timestamp
    subjects: SubjectGroup;
};

/**
 * A student row used in the Registered section, extending StudentRow with an IB candidate number.
 */
export type RegisteredRow = StudentRow & {
    ibCandidateNumber: string;
};

/**
 * The full API response shape for the Diploma Programme Year 2 registration report.
 */
export type ReportResponse = {
    sessionTitle: string;
    preparation: StudentRow[];
    unconfirmed: StudentRow[];
    confirmed: StudentRow[];
    inProgress: InProgressRow[];
    failed: FailedRow[];
    registered: RegisteredRow[];
};
