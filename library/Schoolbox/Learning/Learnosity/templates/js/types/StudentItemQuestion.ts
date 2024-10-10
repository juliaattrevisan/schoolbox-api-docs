/**
 * A question, linked to an Item
 */

import type { StudentItemQuestionResponse } from "./StudentItemQuestionResponse";

export type StudentItemQuestion = {
    metadata: {
        sample_answer?: string;
        sheet_reference: string;
    };
    response_id: string;
    response?: StudentItemQuestionResponse;
};
