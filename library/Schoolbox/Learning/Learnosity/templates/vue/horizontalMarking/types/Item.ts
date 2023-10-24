/**
 * A item (called a card in sbx), that can contain multiple questions/features
 */

import type { Question } from "./Question";

export type Item = {
    id: string;
    reference: string;
    title: string;
    questions: Question[];
    isFinalReview: boolean;
};
