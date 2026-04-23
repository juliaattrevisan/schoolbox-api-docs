/**
 * A registration session for an IB examination period.
 * Title is derived by the backend: "{Qualification Title} - {Examination period} {Academic Year}"
 */
export type RegistrationSession = {
    id: number;
    title: string;
    academicYear: string; // Free text, e.g. "2026" or "2025/2026"
    examSession: "May" | "November";
    createdAt: string; // ISO 8601 timestamp
    createdBy: string | null; // Display name of the user who created the session
};
