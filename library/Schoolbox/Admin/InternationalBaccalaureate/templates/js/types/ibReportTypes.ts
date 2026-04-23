export type SubjectEntry = {
    subject: string;
    language: string | null;
};

export type SubjectGroup = {
    hl: SubjectEntry[];
    sl: SubjectEntry[];
    core: SubjectEntry[];
};

export type CandidateDetailsStatus = "confirmed" | "unconfirmed";

export type StudentRow = {
    id: number;
    name: string;
    candidateDetailsStatus: CandidateDetailsStatus;
    subjects: SubjectGroup;
};

export type FailedRow = StudentRow & {
    errorMessage: string;
};

export type InProgressRow = {
    id: number;
    name: string;
    ibisSubmittedAt: string; // ISO 8601 timestamp
    subjects: SubjectGroup;
};

export type RegisteredRow = StudentRow & {
    ibCandidateNumber: string;
};

export type ReportResponse = {
    sessionTitle: string;
    preparation: StudentRow[];
    unconfirmed: StudentRow[];
    confirmed: StudentRow[];
    inProgress: InProgressRow[];
    failed: FailedRow[];
    registered: RegisteredRow[];
};
