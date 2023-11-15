/**
 * An item (called a card in sbx) linked to a student, so it contains the questions and responses for that student
 */
import type { StudentItemQuestion } from "./StudentItemQuestion";

export type StudentItem = {
    reference: string; // the Learnosity reference id of the item (card in sbx)
    questions: StudentItemQuestion[]; // the questions within this item
    isMarked?: boolean; // whether all questions within the item have been marked
    title?: string; // the title of the item
};
