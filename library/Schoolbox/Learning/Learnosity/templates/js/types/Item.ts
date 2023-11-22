/**
 * A item (called a card in sbx), that can contain multiple questions/features
 */

import { StudentItemQuestion } from "./StudentItemQuestion";

export type Item = {
    id: string;
    reference: string;
    title: string;
    isAttemptComment: boolean;
    questions?: StudentItemQuestion[];
    isMarked?: boolean;
};
