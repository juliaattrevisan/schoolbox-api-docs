import { Result } from "./Result";

/**
 * The returned search data
 */
export type Search = {
    data: Result[];
    metadata?: {
        cursor: {
            current: string;
            next: string;
        };
    };
};
