/**
 * An item (called a card in sbx), that is also linked to a students response
 */
import type { Question } from "./Question";
import type { StudentItemResponse } from "./StudentItemResponse";

export type StudentItem = {
    id: string;
    reference: string;
    title: string;
    questions: Question[];
    isFinalReview: boolean;
    response?: StudentItemResponse;
};
