/**
 * The response for a question within an item
 */
export type StudentItemQuestionResponse = {
    itemReference: string;
    learnosityReference: string;
    score?: number;
    maxScore?: number;
    comment?: string;
};
