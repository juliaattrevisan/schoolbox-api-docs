/**
 * An item (called a card in sbx), that is also linked to a students response
 */
import type { Question } from "./Question";
import type { StudentItemResponse } from "./StudentItemResponse";

export type StudentItem = {
    reference: string;
    questions: Question[];
    response_ids: string[];
    response: StudentItemResponse;
};
