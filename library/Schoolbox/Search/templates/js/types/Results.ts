import { Result } from "./Result";

/**
 * The returned search data
 */
export type Results = {
    data: Result[];
    metadata?: {
        cursor: {
            current: string;
            next: string;
        };
    };
};
