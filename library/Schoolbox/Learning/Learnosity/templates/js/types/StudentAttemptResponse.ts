/**
 * To provide an overall attempt response for a student
 */
export type StudentAttemptResponse = {
    learnosityReference: string;
    studentId: number; // used to link to the student as loaded from the server
    comment?: string;
};
