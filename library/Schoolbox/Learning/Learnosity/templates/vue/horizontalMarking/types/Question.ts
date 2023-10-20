/**
 * A question, linked to an Item
 */
export type Question = {
    metadata: {
        sample_answer?: string;
        sheet_reference: string;
    };
    response_id: string;
};
