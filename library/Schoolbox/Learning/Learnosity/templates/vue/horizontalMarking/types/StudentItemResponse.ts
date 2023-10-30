/**
 * The response for a question within an item
 */
export type StudentItemResponse = {
    itemReference: string;
    learnosityReference: string;
    score: number;
    maxScore?: number;
    comment?: string;
};
