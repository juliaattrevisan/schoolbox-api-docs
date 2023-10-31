/**
 * A item (called a card in sbx), that can contain multiple questions/features
 */

export type Item = {
    id: string;
    reference: string;
    title: string;
    isAttemptComment: boolean;
};
