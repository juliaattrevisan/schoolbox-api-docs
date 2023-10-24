/**
 * An item (called a card in sbx), that is also linked to a students response
 */

import type { Question } from "./Question";

export type StudentItem = {
    id: string;
    reference: string;
    title: string;
    questions: Question[];
    isFinalReview: boolean;
    response?: string; // feedback for the student
};
