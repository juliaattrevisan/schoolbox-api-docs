/**
 * To provide an overall attempt response for a student
 */
export type StudentAttemptResponse = {
    learnosityReference: string;
    studentId: number; // used to link to the student as loaded from the server
    comment?: string;
    mark?: string; // used to override the mark dynamically generated or provide a submission state like 'submitted' or 'absent'
    responseId?: string | null; // to link to the response id for the attempt
};
